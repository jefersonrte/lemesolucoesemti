<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
$config = require __DIR__ . '/private/runtime.php';

function tenderFetchApi(string $url, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL não está disponível.');
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-API-KEY: ' . $apiKey,
            'User-Agent: LemeSolucoesLicitacoes/2.0',
        ],
    ]);
    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $curlError !== '') {
        throw new RuntimeException('Falha de comunicação com a API.');
    }
    if ($status !== 200) {
        throw new RuntimeException('A API respondeu com HTTP ' . $status . '.');
    }
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['ok'] ?? false) !== true) {
        throw new RuntimeException('A API retornou uma resposta inesperada.');
    }
    return $payload;
}

function tenderH($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tenderDate($value, string $fallback = 'Não informado'): string
{
    if (!is_string($value) || trim($value) === '') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $fallback : date('d/m/Y', $timestamp) . ' às ' . date('H:i', $timestamp);
}

function tenderMoney($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function tenderCategoryLabel(string $category): string
{
    return match ($category) {
        'produtos' => 'Produtos',
        'servicos' => 'Serviços',
        default => 'Outros',
    };
}

function tenderPageUrl(int $page, array $filters): string
{
    $query = $filters;
    $query['pagina'] = max(1, $page);
    return '/gov/licitacoes.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

$search = mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 100);
$city = (string) ($_GET['cidade'] ?? '');
if (!in_array($city, ['', 'florianopolis', 'sao-jose'], true)) {
    $city = '';
}
$situation = (string) ($_GET['situacao'] ?? 'andamento');
if (!in_array($situation, ['andamento', 'encerradas', 'todas'], true)) {
    $situation = 'andamento';
}
$category = (string) ($_GET['categoria'] ?? '');
if (!in_array($category, ['', 'produtos', 'servicos', 'outros'], true)) {
    $category = '';
}
$page = max(1, (int) ($_GET['pagina'] ?? 1));
$filters = [
    'busca' => $search,
    'cidade' => $city,
    'situacao' => $situation,
    'categoria' => $category,
];
$procurements = [];
$meta = [
    'total' => 0,
    'pagina' => 1,
    'totalPaginas' => 1,
    'totais' => [
        'todos' => 0,
        'emAndamento' => 0,
        'florianopolis' => 0,
        'saoJose' => 0,
        'florianopolisEmAndamento' => 0,
        'saoJoseEmAndamento' => 0,
    ],
    'atualizadoEm' => null,
];
$error = null;

try {
    $apiQuery = array_merge(['colecao' => 'licitacoes', 'limite' => 48, 'pagina' => $page], $filters);
    $separator = strpos($config['api_url'], '?') === false ? '?' : '&';
    $payload = tenderFetchApi(
        $config['api_url'] . $separator . http_build_query($apiQuery, '', '&', PHP_QUERY_RFC3986),
        $config['api_key']
    );
    $procurements = is_array($payload['dados'] ?? null) ? $payload['dados'] : [];
    $meta = array_replace_recursive($meta, is_array($payload['meta'] ?? null) ? $payload['meta'] : []);
} catch (Throwable $exception) {
    $error = 'O espelho local está temporariamente indisponível. Use os portais oficiais abaixo.';
    error_log('[GOV LICITACOES VIEW] ' . $exception->getMessage());
}

$totals = $meta['totais'];
$currentPage = max(1, (int) ($meta['pagina'] ?? 1));
$totalPages = max(1, (int) ($meta['totalPaginas'] ?? 1));
$firstResult = (int) ($meta['total'] ?? 0) > 0 ? (($currentPage - 1) * 48) + 1 : 0;
$lastResult = min($currentPage * 48, (int) ($meta['total'] ?? 0));
$updatedAt = tenderDate($meta['atualizadoEm'] ?? null, 'aguardando a primeira sincronização');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Espelho das licitações municipais de Florianópolis e São José, com busca por produtos, serviços e situação.">
  <meta name="theme-color" content="#061710">
  <title>Licitações municipais | Leme Soluções</title>
  <style>
    :root{color-scheme:dark;--bg:#04130d;--panel:#092219;--panel2:#071b14;--line:#1d5b44;--green:#35e89d;--green2:#bfffe1;--text:#effff8;--muted:#95b9ab;--orange:#ffca73;--red:#ffaaaa}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;min-height:100vh;background:radial-gradient(circle at 78% -5%,#0e4933 0,transparent 34%),linear-gradient(135deg,#03100b,#071b13 72%);color:var(--text);font:15px/1.55 Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(1240px,calc(100% - 32px));margin:auto;padding:30px 0 70px}.nav{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:52px}.brand{color:#dffff0;font-weight:900;letter-spacing:-.02em}.brand span{color:var(--green)}a{color:inherit}.nav-links{display:flex;align-items:center;gap:12px}.nav a{color:#b9ddce;text-decoration:none;font-size:12px}.button-link{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;border:1px solid var(--line);border-radius:999px;background:rgba(8,31,22,.75);color:var(--green2)!important;font-weight:800}.hero{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:28px}.eyebrow,.tag{color:var(--green);font:800 11px ui-monospace,monospace;letter-spacing:.15em}.hero h1{max-width:900px;margin:9px 0 14px;font-size:clamp(42px,7vw,78px);line-height:.95;letter-spacing:-.068em}.hero p{max-width:820px;margin:0;color:var(--muted);font-size:16px}.live{display:flex;align-items:center;gap:9px;min-width:205px;padding:18px;border-left:1px solid var(--line);color:var(--green2);font:800 12px ui-monospace,monospace}.live:before{content:"";width:9px;height:9px;border-radius:50%;background:var(--green);box-shadow:0 0 0 6px rgba(53,232,157,.12)}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:34px 0 50px}.metric{padding:18px;border:1px solid var(--line);border-radius:14px;background:rgba(8,30,22,.78)}.metric strong{display:block;color:var(--green);font-size:28px;line-height:1.15}.metric span{color:var(--muted);font-size:12px}.section{margin-top:50px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:22px;margin-bottom:19px}.section h2{margin:5px 0 4px;font-size:clamp(28px,4vw,43px);letter-spacing:-.045em}.section-head p{max-width:760px;margin:0;color:var(--muted)}.portals{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.portal{display:flex;flex-direction:column;min-height:210px;padding:21px;border:1px solid var(--line);border-radius:16px;background:linear-gradient(145deg,rgba(10,40,29,.92),rgba(6,24,17,.94))}.portal-top{display:flex;align-items:center;justify-content:space-between;gap:14px}.portal h3{margin:16px 0 5px;font-size:23px}.portal p{margin:0;color:var(--muted)}.portal .count{display:grid;place-items:center;min-width:50px;height:44px;padding:0 9px;border:1px solid #287155;border-radius:12px;color:var(--green);font-weight:900}.portal a{margin-top:auto;padding-top:18px;color:var(--green2);font-weight:800;text-decoration:none}.notice,.error{margin:20px 0 0;padding:15px 17px;border:1px solid #705828;border-radius:12px;background:rgba(74,52,14,.28);color:#ffe4af}.error{border-color:#713b3b;background:rgba(82,28,28,.24);color:#ffd4d4}.tools{display:grid;grid-template-columns:minmax(280px,1fr) 180px 180px 180px auto;gap:10px;margin:20px 0}.tools input,.tools select,.tools button,.page-link{min-height:48px;border:1px solid var(--line);border-radius:11px;background:#071b14;color:#fff;padding:10px 13px;font:inherit}.tools input:focus,.tools select:focus{outline:2px solid rgba(53,232,157,.22);border-color:var(--green)}.tools button{background:var(--green);border-color:var(--green);color:#042016;font-weight:900;cursor:pointer}.summary{display:flex;justify-content:space-between;gap:20px;margin-bottom:14px;color:var(--muted);font-size:12px}.summary strong{color:var(--green2)}.tenders{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.tender{display:flex;flex-direction:column;min-height:380px;padding:20px;border:1px solid var(--line);border-radius:16px;background:rgba(8,30,22,.88);transition:transform .2s,border-color .2s}.tender:hover{transform:translateY(-2px);border-color:#2a9c70}.tender.closed{border-color:#374b44;opacity:.88}.tender-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.badges{display:flex;flex-wrap:wrap;gap:7px}.badge{padding:5px 8px;border:1px solid #2a7658;border-radius:999px;color:var(--green2);font:800 10px ui-monospace,monospace;letter-spacing:.05em}.badge.category{border-color:#6b562e;color:var(--orange)}.status{max-width:180px;color:var(--green);font:800 11px ui-monospace,monospace;text-align:right}.closed .status{color:var(--muted)}.tender h3{margin:18px 0 8px;font-size:18px;line-height:1.42}.agency{margin:0;color:var(--muted);font-size:12px}.details{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0 0;padding-top:15px;border-top:1px solid #164532}.details dt{color:#719b8a;font-size:9px;text-transform:uppercase;letter-spacing:.08em}.details dd{margin:2px 0 0;color:#d8f9eb;font-size:12px}.actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:auto;padding-top:19px}.actions a{display:inline-flex;align-items:center;justify-content:center;min-height:39px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;color:var(--green2);font-size:12px;font-weight:800;text-decoration:none}.actions a.primary{background:var(--green);border-color:var(--green);color:#042016}.empty{grid-column:1/-1;padding:40px;border:1px dashed #2a684f;border-radius:15px;text-align:center;color:var(--muted)}.empty strong{display:block;margin-bottom:5px;color:var(--green2);font-size:18px}.pagination{display:flex;align-items:center;justify-content:center;gap:9px;margin-top:22px}.page-link{display:inline-flex;align-items:center;justify-content:center;min-width:110px;text-decoration:none;font-weight:800}.page-link.disabled{opacity:.35;pointer-events:none}.page-current{color:var(--muted);font-size:12px}.footnote{margin-top:15px;color:#719b8a;font-size:11px}.footer{display:flex;justify-content:space-between;gap:22px;margin-top:58px;padding-top:18px;border-top:1px solid #164532;color:var(--muted);font-size:12px}.footer a{color:var(--green2)}@media(max-width:1050px){.tools{grid-template-columns:1fr 1fr 1fr}.tools input{grid-column:1/-1}.metrics{grid-template-columns:repeat(2,1fr)}}@media(max-width:850px){.tenders{grid-template-columns:1fr}}@media(max-width:650px){.wrap{width:min(100% - 20px,1240px);padding-top:20px}.nav{align-items:flex-start;margin-bottom:40px}.nav-links{align-items:flex-end;flex-direction:column}.hero{grid-template-columns:1fr}.live{border-left:0;border-top:1px solid var(--line);padding:14px 0}.metrics,.portals,.tools{grid-template-columns:1fr}.tools input{grid-column:auto}.section-head{align-items:flex-start;flex-direction:column}.details{grid-template-columns:1fr 1fr}.summary,.footer{flex-direction:column}}
  </style>
</head>
<body>
  <main class="wrap">
    <nav class="nav" aria-label="Navegação principal">
      <div class="brand">LEME <span>SOLUÇÕES</span></div>
      <div class="nav-links">
        <a href="/gov/">← Dados públicos</a>
        <a class="button-link" href="#licitacoes">Pesquisar licitações</a>
      </div>
    </nav>

    <header class="hero">
      <div>
        <span class="eyebrow">SC / ESPELHO DOS PORTAIS MUNICIPAIS</span>
        <h1>Licitações de produtos e serviços</h1>
        <p>Os processos de Florianópolis e São José são baixados dos portais municipais e armazenados neste site. Consulte as licitações em andamento ou pesquise todo o histórico sem sair daqui.</p>
      </div>
      <div class="live">CÓPIA LOCAL ATUALIZADA</div>
    </header>

    <?php if ($error): ?><div class="error" role="alert"><?= tenderH($error) ?></div><?php endif; ?>

    <section class="metrics" aria-label="Resumo">
      <div class="metric"><strong><?= number_format((int) ($totals['todos'] ?? 0), 0, ',', '.') ?></strong><span>processos armazenados</span></div>
      <div class="metric"><strong><?= number_format((int) ($totals['emAndamento'] ?? 0), 0, ',', '.') ?></strong><span>marcados como em andamento</span></div>
      <div class="metric"><strong><?= number_format((int) ($totals['florianopolis'] ?? 0), 0, ',', '.') ?></strong><span>registros de Florianópolis</span></div>
      <div class="metric"><strong><?= number_format((int) ($totals['saoJose'] ?? 0), 0, ',', '.') ?></strong><span>registros de São José</span></div>
    </section>

    <section class="section" id="portais">
      <div class="section-head">
        <div><span class="tag">FONTES OFICIAIS</span><h2>Dois portais, uma consulta</h2><p>A listagem abaixo reúne os dados dos sistemas municipais. Para anexos, impugnações e participação, abra o processo na fonte oficial.</p></div>
      </div>
      <div class="portals">
        <article class="portal">
          <div class="portal-top"><span class="tag">PREFEITURA MUNICIPAL</span><span class="count"><?= number_format((int) ($totals['florianopolisEmAndamento'] ?? 0), 0, ',', '.') ?></span></div>
          <h3>Florianópolis</h3>
          <p><?= number_format((int) ($totals['florianopolis'] ?? 0), 0, ',', '.') ?> processos copiados; <?= number_format((int) ($totals['florianopolisEmAndamento'] ?? 0), 0, ',', '.') ?> aparecem como em andamento no mural.</p>
          <a href="https://wbc.pmf.sc.gov.br/portal/Mural.aspx?nNmTela=E" target="_blank" rel="noopener noreferrer">Abrir portal de Florianópolis ↗</a>
        </article>
        <article class="portal">
          <div class="portal-top"><span class="tag">PREFEITURA MUNICIPAL</span><span class="count"><?= number_format((int) ($totals['saoJoseEmAndamento'] ?? 0), 0, ',', '.') ?></span></div>
          <h3>São José</h3>
          <p><?= number_format((int) ($totals['saoJose'] ?? 0), 0, ',', '.') ?> processos copiados; <?= number_format((int) ($totals['saoJoseEmAndamento'] ?? 0), 0, ',', '.') ?> aparecem como em andamento no mural.</p>
          <a href="https://egov.paradigmabs.com.br/saojose/portal/Mural.aspx?nNmTela=E" target="_blank" rel="noopener noreferrer">Abrir portal de São José ↗</a>
        </article>
      </div>
    </section>

    <section class="section" id="licitacoes">
      <div class="section-head">
        <div><span class="tag">BASE LOCAL / BUSCA COMPLETA</span><h2>Licitações encontradas</h2><p>Por padrão são exibidos somente os processos que o portal de origem classifica como em andamento. Troque a situação para consultar todos os registros baixados.</p></div>
      </div>

      <form class="tools" method="get" action="/gov/licitacoes.php#licitacoes" role="search">
        <input name="busca" value="<?= tenderH($search) ?>" type="search" placeholder="Objeto, órgão, edital, processo ou modalidade" aria-label="Buscar licitações">
        <select name="cidade" aria-label="Filtrar por cidade">
          <option value=""<?= $city === '' ? ' selected' : '' ?>>Todas as cidades</option>
          <option value="florianopolis"<?= $city === 'florianopolis' ? ' selected' : '' ?>>Florianópolis</option>
          <option value="sao-jose"<?= $city === 'sao-jose' ? ' selected' : '' ?>>São José</option>
        </select>
        <select name="situacao" aria-label="Filtrar por situação">
          <option value="andamento"<?= $situation === 'andamento' ? ' selected' : '' ?>>Em andamento</option>
          <option value="encerradas"<?= $situation === 'encerradas' ? ' selected' : '' ?>>Fora de andamento</option>
          <option value="todas"<?= $situation === 'todas' ? ' selected' : '' ?>>Todas as situações</option>
        </select>
        <select name="categoria" aria-label="Filtrar por tipo">
          <option value=""<?= $category === '' ? ' selected' : '' ?>>Produtos e serviços</option>
          <option value="produtos"<?= $category === 'produtos' ? ' selected' : '' ?>>Produtos</option>
          <option value="servicos"<?= $category === 'servicos' ? ' selected' : '' ?>>Serviços</option>
          <option value="outros"<?= $category === 'outros' ? ' selected' : '' ?>>Outros</option>
        </select>
        <button type="submit">PESQUISAR</button>
      </form>
      <div class="summary">
        <span><strong><?= number_format((int) ($meta['total'] ?? 0), 0, ',', '.') ?></strong> resultados; mostrando <?= number_format($firstResult, 0, ',', '.') ?>–<?= number_format($lastResult, 0, ',', '.') ?></span>
        <span>Última sincronização: <?= tenderH($updatedAt) ?></span>
      </div>

      <div class="tenders">
        <?php foreach ($procurements as $procurement):
            $isOngoing = (int) ($procurement['emAndamento'] ?? 0) === 1;
            $estimatedValue = (float) ($procurement['valorEstimado'] ?? 0);
        ?>
          <article class="tender<?= $isOngoing ? '' : ' closed' ?>">
            <div class="tender-top">
              <div class="badges"><span class="badge"><?= tenderH($procurement['cidade'] ?? '') ?></span><span class="badge category"><?= tenderH(tenderCategoryLabel((string) ($procurement['categoria'] ?? 'outros'))) ?></span></div>
              <span class="status"><?= $isOngoing ? 'EM ANDAMENTO' : tenderH($procurement['situacao'] ?? 'FORA DE ANDAMENTO') ?></span>
            </div>
            <h3><?= tenderH($procurement['objeto'] ?? 'Objeto não informado pelo portal de origem.') ?></h3>
            <p class="agency"><?= tenderH($procurement['orgao'] ?? 'Órgão não informado') ?><?php if (!empty($procurement['unidade'])): ?> · <?= tenderH($procurement['unidade']) ?><?php endif; ?></p>
            <dl class="details">
              <div><dt>Situação oficial</dt><dd><?= tenderH($procurement['situacao'] ?? 'Não informada') ?></dd></div>
              <div><dt>Modalidade</dt><dd><?= tenderH($procurement['modalidade'] ?? 'Não informada') ?></dd></div>
              <div><dt>Edital</dt><dd><?= tenderH($procurement['numeroEdital'] ?: 'Não informado') ?></dd></div>
              <div><dt>Processo</dt><dd><?= tenderH($procurement['numeroProcesso'] ?: 'Não informado') ?></dd></div>
              <div><dt>Início</dt><dd><?= tenderH(tenderDate($procurement['dataInicio'] ?? null)) ?></dd></div>
              <div><dt>Fim</dt><dd><?= tenderH(tenderDate($procurement['dataFim'] ?? null)) ?></dd></div>
              <div><dt>Tipo</dt><dd><?= tenderH($procurement['tipoModalidade'] ?? 'Não informado') ?></dd></div>
              <div><dt>Valor estimado</dt><dd><?= $estimatedValue > 0 ? tenderH(tenderMoney($estimatedValue)) : 'Não informado' ?></dd></div>
            </dl>
            <div class="actions">
              <a class="primary" href="<?= tenderH($procurement['urlProcesso'] ?? '#portais') ?>" target="_blank" rel="noopener noreferrer">Abrir processo oficial ↗</a>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$procurements): ?><div class="empty"><strong>Nenhuma licitação encontrada</strong><span>Altere os filtros ou consulte os portais oficiais acima.</span></div><?php endif; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Paginação">
          <a class="page-link<?= $currentPage <= 1 ? ' disabled' : '' ?>" href="<?= tenderH(tenderPageUrl($currentPage - 1, $filters)) ?>#licitacoes">← Anterior</a>
          <span class="page-current">Página <?= number_format($currentPage, 0, ',', '.') ?> de <?= number_format($totalPages, 0, ',', '.') ?></span>
          <a class="page-link<?= $currentPage >= $totalPages ? ' disabled' : '' ?>" href="<?= tenderH(tenderPageUrl($currentPage + 1, $filters)) ?>#licitacoes">Próxima →</a>
        </nav>
      <?php endif; ?>
      <p class="footnote">A categoria “Produtos” ou “Serviços” é uma classificação indicativa baseada no texto do objeto. A situação “Em andamento” reproduz a visão vigente dos portais WBC. Confirme sempre o edital e os anexos na fonte oficial.</p>
    </section>

    <footer class="footer"><span>Fontes: portais municipais de compras de Florianópolis e São José.</span><span>Dados armazenados no site; participação e documentos permanecem na fonte oficial.</span></footer>
  </main>
</body>
</html>
