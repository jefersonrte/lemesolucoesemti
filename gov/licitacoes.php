<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
$config = require __DIR__ . '/private/runtime.php';

function tenderFetchApi(string $url): array
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
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: LemeSolucoesLicitacoes/3.0'],
    ]);
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($body === false || $error !== '' || $status !== 200) {
        throw new RuntimeException('Falha de comunicação com a API pública.');
    }
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload['ok'] ?? false) !== true) {
        throw new RuntimeException('A API pública retornou uma resposta inesperada.');
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

function tenderSectorLabel(string $sector): string
{
    return match ($sector) {
        'ti' => 'TI',
        'obras' => 'Obras',
        'saude' => 'Saúde',
        'seguranca' => 'Segurança',
        default => 'Outros',
    };
}

function tenderPageUrl(int $page, array $filters): string
{
    $filters['pagina'] = max(1, $page);
    return '/gov/licitacoes.php?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
}

$fallbackCities = [
    ['slug' => 'florianopolis', 'nome' => 'Florianópolis', 'portal' => 'https://wbc.pmf.sc.gov.br/portal/Mural.aspx?nNmTela=E'],
    ['slug' => 'sao-jose', 'nome' => 'São José', 'portal' => 'https://egov.paradigmabs.com.br/saojose/portal/Mural.aspx?nNmTela=E'],
    ['slug' => 'palhoca', 'nome' => 'Palhoça', 'portal' => 'https://palhoca.atende.net/autoatendimento/servicos/consulta-de-licitacoes'],
    ['slug' => 'biguacu', 'nome' => 'Biguaçu', 'portal' => 'https://www.bigua.sc.gov.br/estrutura/pagina-11463/pagina-11463-lic/'],
    ['slug' => 'governador-celso-ramos', 'nome' => 'Governador Celso Ramos', 'portal' => 'https://governadorcelsoramos.sc.gov.br/licitacoes/'],
    ['slug' => 'tijucas', 'nome' => 'Tijucas', 'portal' => 'https://tijucas.atende.net/transparencia/item/licitacoes-gerais'],
    ['slug' => 'santo-amaro-da-imperatriz', 'nome' => 'Santo Amaro da Imperatriz', 'portal' => 'https://www.santoamaro.sc.gov.br/licitacoes/2'],
    ['slug' => 'antonio-carlos', 'nome' => 'Antônio Carlos', 'portal' => 'https://antoniocarlos.sc.gov.br/licitacoes/'],
];
$allowedCities = array_column($fallbackCities, 'slug');
$search = mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 100);
$city = (string) ($_GET['cidade'] ?? '');
if ($city !== '' && !in_array($city, $allowedCities, true)) {
    $city = '';
}
$situation = (string) ($_GET['situacao'] ?? 'andamento');
if (!in_array($situation, ['andamento', 'encerradas', 'todas'], true)) {
    $situation = 'andamento';
}
$sector = (string) ($_GET['setor'] ?? '');
if (!in_array($sector, ['', 'ti', 'obras', 'saude', 'seguranca', 'outros'], true)) {
    $sector = '';
}
$page = max(1, (int) ($_GET['pagina'] ?? 1));
$filters = ['busca' => $search, 'cidade' => $city, 'situacao' => $situation, 'setor' => $sector];
$procurements = [];
$meta = [
    'total' => 0,
    'pagina' => 1,
    'totalPaginas' => 1,
    'totais' => ['todos' => 0, 'emAndamento' => 0, 'ti' => 0, 'obras' => 0, 'saude' => 0, 'seguranca' => 0],
    'cidades' => $fallbackCities,
    'atualizadoEm' => null,
];
$error = null;

try {
    $apiQuery = array_merge(['colecao' => 'licitacoes', 'limite' => 48, 'pagina' => $page], $filters);
    $separator = strpos($config['api_url'], '?') === false ? '?' : '&';
    $payload = tenderFetchApi($config['api_url'] . $separator . http_build_query($apiQuery, '', '&', PHP_QUERY_RFC3986));
    $procurements = is_array($payload['dados'] ?? null) ? $payload['dados'] : [];
    $meta = array_replace_recursive($meta, is_array($payload['meta'] ?? null) ? $payload['meta'] : []);
} catch (Throwable $exception) {
    $error = 'O espelho local está temporariamente indisponível. Use os portais oficiais abaixo.';
    error_log('[GOV LICITACOES VIEW] ' . $exception->getMessage());
}

$totals = $meta['totais'];
$cities = is_array($meta['cidades'] ?? null) ? $meta['cidades'] : $fallbackCities;
$currentPage = max(1, (int) ($meta['pagina'] ?? 1));
$totalPages = max(1, (int) ($meta['totalPaginas'] ?? 1));
$firstResult = (int) ($meta['total'] ?? 0) > 0 ? (($currentPage - 1) * 48) + 1 : 0;
$lastResult = min($currentPage * 48, (int) ($meta['total'] ?? 0));
$updatedAt = tenderDate($meta['atualizadoEm'] ?? null, 'aguardando sincronização');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Licitações da Grande Florianópolis, com foco e classificação automática para oportunidades de tecnologia da informação.">
  <meta name="theme-color" content="#061710">
  <title>Licitações da Grande Florianópolis | Foco em TI</title>
  <style>
    :root{color-scheme:dark;--bg:#04130d;--panel:#092219;--panel2:#071b14;--line:#1d5b44;--green:#35e89d;--cyan:#65dfff;--green2:#bfffe1;--text:#effff8;--muted:#95b9ab;--orange:#ffca73}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;min-height:100vh;background:radial-gradient(circle at 78% -5%,#0e4933 0,transparent 34%),linear-gradient(135deg,#03100b,#071b13 72%);color:var(--text);font:15px/1.55 Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(1280px,calc(100% - 32px));margin:auto;padding:30px 0 70px}.nav{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:52px}.brand{color:#dffff0;font-weight:900}.brand span{color:var(--green)}a{color:inherit}.nav a{color:#b9ddce;text-decoration:none;font-size:12px}.button-link{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 14px;border:1px solid var(--line);border-radius:999px;background:#081f16;color:var(--green2)!important;font-weight:800}.hero{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:28px}.eyebrow,.tag{color:var(--green);font:800 11px ui-monospace,monospace;letter-spacing:.15em}.hero h1{max-width:930px;margin:9px 0 14px;font-size:clamp(42px,7vw,78px);line-height:.95;letter-spacing:-.068em}.hero h1 span{color:var(--cyan)}.hero p{max-width:850px;margin:0;color:var(--muted);font-size:16px}.live{display:flex;align-items:center;gap:9px;min-width:210px;padding:18px;border-left:1px solid var(--line);color:var(--green2);font:800 12px ui-monospace,monospace}.live:before{content:"";width:9px;height:9px;border-radius:50%;background:var(--green);box-shadow:0 0 0 6px rgba(53,232,157,.12)}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:34px 0 50px}.metric{padding:18px;border:1px solid var(--line);border-radius:14px;background:#081e16}.metric strong{display:block;color:var(--green);font-size:28px}.metric.ti{border-color:#28768c;background:linear-gradient(145deg,#092a2d,#071b14)}.metric.ti strong{color:var(--cyan)}.metric span{color:var(--muted);font-size:12px}.section{margin-top:50px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:22px;margin-bottom:19px}.section h2{margin:5px 0 4px;font-size:clamp(28px,4vw,43px);letter-spacing:-.045em}.section-head p{max-width:800px;margin:0;color:var(--muted)}.portals{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.portal{display:flex;flex-direction:column;min-height:190px;padding:18px;border:1px solid var(--line);border-radius:15px;background:linear-gradient(145deg,#0a281d,#061811)}.portal-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.portal h3{margin:14px 0 5px;font-size:19px}.portal p{margin:0;color:var(--muted);font-size:12px}.portal .count{display:grid;place-items:center;min-width:42px;height:38px;padding:0 8px;border:1px solid #287155;border-radius:10px;color:var(--green);font-weight:900}.portal a{margin-top:auto;padding-top:14px;color:var(--green2);font-size:12px;font-weight:800;text-decoration:none}.error{margin:20px 0 0;padding:15px 17px;border:1px solid #713b3b;border-radius:12px;background:#321616;color:#ffd4d4}.tools{display:grid;grid-template-columns:minmax(290px,1fr) 210px 190px 190px auto;gap:10px;margin:20px 0}.tools input,.tools select,.tools button,.page-link{min-height:48px;border:1px solid var(--line);border-radius:11px;background:#071b14;color:#fff;padding:10px 13px;font:inherit}.tools input:focus,.tools select:focus{outline:2px solid rgba(53,232,157,.22);border-color:var(--green)}.tools button{background:var(--green);border-color:var(--green);color:#042016;font-weight:900;cursor:pointer}.quick{display:flex;flex-wrap:wrap;gap:8px;margin:-8px 0 18px}.quick a{padding:7px 11px;border:1px solid var(--line);border-radius:999px;color:var(--green2);font-size:11px;font-weight:800;text-decoration:none}.quick a.ti{border-color:#28768c;color:var(--cyan)}.summary{display:flex;justify-content:space-between;gap:20px;margin-bottom:14px;color:var(--muted);font-size:12px}.summary strong{color:var(--green2)}.tenders{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.tender{display:flex;flex-direction:column;min-height:390px;padding:20px;border:1px solid var(--line);border-radius:16px;background:#081e16}.tender.ti{border-color:#28768c;background:linear-gradient(145deg,#09262a,#081e16)}.tender.closed{opacity:.82}.tender-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.badges{display:flex;flex-wrap:wrap;gap:7px}.badge{padding:5px 8px;border:1px solid #2a7658;border-radius:999px;color:var(--green2);font:800 10px ui-monospace,monospace}.badge.sector{border-color:#6b562e;color:var(--orange)}.badge.sector.ti{border-color:#28768c;color:var(--cyan)}.status{max-width:180px;color:var(--green);font:800 11px ui-monospace,monospace;text-align:right}.tender h3{margin:18px 0 8px;font-size:18px;line-height:1.42}.agency{margin:0;color:var(--muted);font-size:12px}.details{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0 0;padding-top:15px;border-top:1px solid #164532}.details dt{color:#719b8a;font-size:9px;text-transform:uppercase}.details dd{margin:2px 0 0;color:#d8f9eb;font-size:12px}.actions{display:flex;gap:9px;margin-top:auto;padding-top:19px}.actions a{display:inline-flex;align-items:center;justify-content:center;min-height:39px;padding:8px 12px;border:1px solid var(--green);border-radius:10px;background:var(--green);color:#042016;font-size:12px;font-weight:800;text-decoration:none}.empty{grid-column:1/-1;padding:40px;border:1px dashed #2a684f;border-radius:15px;text-align:center;color:var(--muted)}.pagination{display:flex;align-items:center;justify-content:center;gap:9px;margin-top:22px}.page-link{display:inline-flex;align-items:center;justify-content:center;min-width:110px;text-decoration:none;font-weight:800}.page-link.disabled{opacity:.35;pointer-events:none}.page-current,.footnote{color:#719b8a;font-size:11px}.footnote{margin-top:15px}.footer{display:flex;justify-content:space-between;gap:22px;margin-top:58px;padding-top:18px;border-top:1px solid #164532;color:var(--muted);font-size:12px}@media(max-width:1100px){.portals{grid-template-columns:repeat(2,1fr)}.tools{grid-template-columns:1fr 1fr 1fr}.tools input{grid-column:1/-1}.metrics{grid-template-columns:repeat(2,1fr)}}@media(max-width:850px){.tenders{grid-template-columns:1fr}}@media(max-width:650px){.wrap{width:min(100% - 20px,1280px);padding-top:20px}.hero{grid-template-columns:1fr}.live{border-left:0;border-top:1px solid var(--line);padding:14px 0}.metrics,.portals,.tools{grid-template-columns:1fr}.tools input{grid-column:auto}.section-head,.summary,.footer{align-items:flex-start;flex-direction:column}.details{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>
  <main class="wrap">
    <nav class="nav"><div class="brand">LEME <span>LICITAÇÕES</span></div><a class="button-link" href="#licitacoes">Pesquisar oportunidades</a></nav>
    <header class="hero">
      <div><span class="eyebrow">GRANDE FLORIANÓPOLIS / MONITOR DE OPORTUNIDADES</span><h1>Licitações com foco em <span>TI</span></h1><p>Consulta pública sem senha. O site reúne editais abertos de oito municípios, classifica automaticamente TI, obras, saúde e segurança e mantém links para as fontes oficiais.</p></div>
      <div class="live">ATUALIZAÇÃO AUTOMÁTICA</div>
    </header>
    <?php if ($error): ?><div class="error" role="alert"><?= tenderH($error) ?></div><?php endif; ?>

    <section class="metrics" aria-label="Resumo">
      <div class="metric"><strong><?= number_format((int) ($totals['todos'] ?? 0), 0, ',', '.') ?></strong><span>licitações armazenadas</span></div>
      <div class="metric"><strong><?= number_format((int) ($totals['emAndamento'] ?? 0), 0, ',', '.') ?></strong><span>em andamento</span></div>
      <div class="metric ti"><strong><?= number_format((int) ($totals['ti'] ?? 0), 0, ',', '.') ?></strong><span>oportunidades classificadas como TI</span></div>
      <div class="metric"><strong><?= count($cities) ?></strong><span>municípios monitorados</span></div>
    </section>

    <section class="section" id="portais">
      <div class="section-head"><div><span class="tag">COBERTURA MUNICIPAL</span><h2>Grande Florianópolis e região</h2><p>Florianópolis e São José usam integração direta com os portais municipais. As demais cidades são atualizadas pela API pública do PNCP, com acesso ao portal local para conferência.</p></div></div>
      <div class="portals">
        <?php foreach ($cities as $municipality): ?>
          <article class="portal">
            <div class="portal-top"><span class="tag"><?= tenderH($municipality['fonte'] ?? 'OFICIAL') ?></span><span class="count"><?= number_format((int) ($municipality['emAndamento'] ?? 0), 0, ',', '.') ?></span></div>
            <h3><?= tenderH($municipality['nome'] ?? '') ?></h3>
            <p><?= number_format((int) ($municipality['tiEmAndamento'] ?? 0), 0, ',', '.') ?> de TI em andamento · <?= number_format((int) ($municipality['total'] ?? 0), 0, ',', '.') ?> registros ativos.</p>
            <a href="<?= tenderH($municipality['portal'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">Abrir fonte oficial ↗</a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="licitacoes">
      <div class="section-head"><div><span class="tag">BUSCA PÚBLICA / SEM LOGIN</span><h2>Oportunidades encontradas</h2><p>Licitações de TI aparecem primeiro. Use o filtro de setor para separar obras, saúde, segurança e demais compras públicas.</p></div></div>
      <form class="tools" method="get" action="/gov/licitacoes.php#licitacoes" role="search">
        <input name="busca" value="<?= tenderH($search) ?>" type="search" placeholder="Software, infraestrutura, suporte, equipamentos..." aria-label="Buscar licitações">
        <select name="cidade" aria-label="Filtrar por cidade">
          <option value="">Todas as cidades</option>
          <?php foreach ($cities as $municipality): $slug = (string) ($municipality['slug'] ?? ''); ?>
            <option value="<?= tenderH($slug) ?>"<?= $city === $slug ? ' selected' : '' ?>><?= tenderH($municipality['nome'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
        <select name="setor" aria-label="Filtrar por setor">
          <option value=""<?= $sector === '' ? ' selected' : '' ?>>Todos os setores</option>
          <option value="ti"<?= $sector === 'ti' ? ' selected' : '' ?>>TI — prioridade</option>
          <option value="obras"<?= $sector === 'obras' ? ' selected' : '' ?>>Obras</option>
          <option value="saude"<?= $sector === 'saude' ? ' selected' : '' ?>>Saúde</option>
          <option value="seguranca"<?= $sector === 'seguranca' ? ' selected' : '' ?>>Segurança</option>
          <option value="outros"<?= $sector === 'outros' ? ' selected' : '' ?>>Outros</option>
        </select>
        <select name="situacao" aria-label="Filtrar por situação">
          <option value="andamento"<?= $situation === 'andamento' ? ' selected' : '' ?>>Em andamento</option>
          <option value="encerradas"<?= $situation === 'encerradas' ? ' selected' : '' ?>>Encerradas / histórico</option>
          <option value="todas"<?= $situation === 'todas' ? ' selected' : '' ?>>Todas as situações</option>
        </select>
        <button type="submit">PESQUISAR</button>
      </form>
      <div class="quick"><a class="ti" href="/gov/licitacoes.php?setor=ti&situacao=andamento#licitacoes">Ver somente TI</a><a href="/gov/licitacoes.php?setor=obras&situacao=andamento#licitacoes">Obras</a><a href="/gov/licitacoes.php?setor=saude&situacao=andamento#licitacoes">Saúde</a><a href="/gov/licitacoes.php?setor=seguranca&situacao=andamento#licitacoes">Segurança</a></div>
      <div class="summary"><span><strong><?= number_format((int) ($meta['total'] ?? 0), 0, ',', '.') ?></strong> resultados; mostrando <?= number_format($firstResult, 0, ',', '.') ?>–<?= number_format($lastResult, 0, ',', '.') ?></span><span>Última atualização: <?= tenderH($updatedAt) ?></span></div>

      <div class="tenders">
        <?php foreach ($procurements as $procurement):
          $isOngoing = (int) ($procurement['emAndamento'] ?? 0) === 1;
          $itemSector = (string) ($procurement['setor'] ?? 'outros');
          $estimatedValue = (float) ($procurement['valorEstimado'] ?? 0);
        ?>
          <article class="tender<?= $itemSector === 'ti' ? ' ti' : '' ?><?= $isOngoing ? '' : ' closed' ?>">
            <div class="tender-top"><div class="badges"><span class="badge"><?= tenderH($procurement['cidade'] ?? '') ?></span><span class="badge sector<?= $itemSector === 'ti' ? ' ti' : '' ?>"><?= tenderH(tenderSectorLabel($itemSector)) ?></span><span class="badge"><?= tenderH(tenderCategoryLabel((string) ($procurement['categoria'] ?? 'outros'))) ?></span></div><span class="status"><?= $isOngoing ? 'EM ANDAMENTO' : tenderH($procurement['situacao'] ?? 'ENCERRADA') ?></span></div>
            <h3><?= tenderH($procurement['objeto'] ?? 'Objeto não informado.') ?></h3>
            <p class="agency"><?= tenderH($procurement['orgao'] ?? 'Órgão não informado') ?><?php if (!empty($procurement['unidade'])): ?> · <?= tenderH($procurement['unidade']) ?><?php endif; ?></p>
            <dl class="details">
              <div><dt>Setor</dt><dd><?= tenderH(tenderSectorLabel($itemSector)) ?></dd></div><div><dt>Situação</dt><dd><?= tenderH($procurement['situacao'] ?? 'Não informada') ?></dd></div><div><dt>Fonte</dt><dd><?= tenderH(strtoupper((string) ($procurement['fonte'] ?? 'oficial'))) ?></dd></div>
              <div><dt>Modalidade</dt><dd><?= tenderH($procurement['modalidade'] ?? 'Não informada') ?></dd></div><div><dt>Edital</dt><dd><?= tenderH($procurement['numeroEdital'] ?: 'Não informado') ?></dd></div><div><dt>Processo</dt><dd><?= tenderH($procurement['numeroProcesso'] ?: 'Não informado') ?></dd></div>
              <div><dt>Início</dt><dd><?= tenderH(tenderDate($procurement['dataInicio'] ?? null)) ?></dd></div><div><dt>Fim</dt><dd><?= tenderH(tenderDate($procurement['dataFim'] ?? null)) ?></dd></div><div><dt>Valor estimado</dt><dd><?= $estimatedValue > 0 ? tenderH(tenderMoney($estimatedValue)) : 'Não informado' ?></dd></div>
            </dl>
            <div class="actions"><a href="<?= tenderH($procurement['urlProcesso'] ?? '#portais') ?>" target="_blank" rel="noopener noreferrer">Abrir processo oficial ↗</a></div>
          </article>
        <?php endforeach; ?>
        <?php if (!$procurements): ?><div class="empty"><strong>Nenhuma licitação encontrada</strong><br>Altere os filtros ou aguarde a próxima atualização automática.</div><?php endif; ?>
      </div>
      <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Paginação"><a class="page-link<?= $currentPage <= 1 ? ' disabled' : '' ?>" href="<?= tenderH(tenderPageUrl($currentPage - 1, $filters)) ?>#licitacoes">← Anterior</a><span class="page-current">Página <?= $currentPage ?> de <?= $totalPages ?></span><a class="page-link<?= $currentPage >= $totalPages ? ' disabled' : '' ?>" href="<?= tenderH(tenderPageUrl($currentPage + 1, $filters)) ?>#licitacoes">Próxima →</a></nav><?php endif; ?>
      <p class="footnote">A classificação por setor é automática e indicativa. TI tem prioridade quando o objeto contém termos tecnológicos. Confirme sempre o edital e os anexos na fonte oficial.</p>
    </section>
    <footer class="footer"><span>Fontes: portais municipais e API pública do PNCP.</span><span>Consulta sem senha; atualização programada a cada 6 horas.</span></footer>
  </main>
</body>
</html>
