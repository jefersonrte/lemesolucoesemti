<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
$config = require __DIR__ . '/private/runtime.php';
$deputies = [];
$propositions = [];
$expenses = [];
$municipalities = [];
$meta = ['total' => 0, 'totais' => [], 'ultimaImportacao' => null];
$municipalityMeta = ['total' => 0, 'pagina' => 1, 'limite' => 48, 'totalPaginas' => 1];
$error = null;
$municipalitySearch = mb_substr(trim((string) ($_GET['municipio_busca'] ?? '')), 0, 60);
$municipalityState = strtoupper(trim((string) ($_GET['municipio_uf'] ?? '')));
$municipalityPageRequested = max(1, (int) ($_GET['municipio_pagina'] ?? 1));
if ($municipalityState !== '' && !preg_match('/^[A-Z]{2}$/', $municipalityState)) {
    $municipalityState = '';
}

function publicFetchGovApi(string $url, string $apiKey): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensao cURL nao esta disponivel.');
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-API-KEY: ' . $apiKey,
            'User-Agent: LemeSolucoesGovSC/3.0',
        ],
    ]);
    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false || $curlError !== '') {
        throw new RuntimeException('Falha de comunicacao com a API.');
    }
    if ($status !== 200) {
        throw new RuntimeException('A API respondeu com HTTP ' . $status . '.');
    }

    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (($payload['ok'] ?? false) !== true) {
        throw new RuntimeException('A API retornou uma resposta inesperada.');
    }
    return $payload;
}

try {
    $payload = publicFetchGovApi($config['api_url'], $config['api_key']);

    $collections = is_array($payload['colecoes'] ?? null) ? $payload['colecoes'] : [];
    $deputies = is_array($collections['deputados'] ?? null)
        ? $collections['deputados']
        : (is_array($payload['dados'] ?? null) ? $payload['dados'] : []);
    $propositions = is_array($collections['proposicoes'] ?? null) ? $collections['proposicoes'] : [];
    $expenses = is_array($collections['despesasRecentes'] ?? null) ? $collections['despesasRecentes'] : [];
    $meta = array_merge($meta, is_array($payload['meta'] ?? null) ? $payload['meta'] : []);

    $separator = strpos($config['api_url'], '?') === false ? '?' : '&';
    $municipalityUrl = $config['api_url'] . $separator . http_build_query([
        'colecao' => 'municipios',
        'busca' => $municipalitySearch,
        'uf' => $municipalityState,
        'pagina' => $municipalityPageRequested,
        'limite' => 48,
    ], '', '&', PHP_QUERY_RFC3986);
    $municipalityPayload = publicFetchGovApi($municipalityUrl, $config['api_key']);
    $municipalities = is_array($municipalityPayload['dados'] ?? null) ? $municipalityPayload['dados'] : [];
    $municipalityMeta = array_merge(
        $municipalityMeta,
        is_array($municipalityPayload['meta'] ?? null) ? $municipalityPayload['meta'] : []
    );
} catch (Throwable $exception) {
    $error = 'Os dados governamentais estao temporariamente indisponiveis.';
    error_log('[GOV VIEW] ' . $exception->getMessage());
}

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function moneyBr($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function propositionLabel(array $proposition): string
{
    $label = trim((string) ($proposition['siglaTipo'] ?? 'Proposicao'));
    $number = (int) ($proposition['numero'] ?? 0);
    $year = (int) ($proposition['ano'] ?? 0);
    if ($number > 0) {
        $label .= ' ' . $number;
    }
    if ($year > 0) {
        $label .= '/' . $year;
    }
    return $label;
}

function municipalityPageUrl(int $page, string $search, string $state): string
{
    $parameters = ['municipio_pagina' => max(1, $page)];
    if ($search !== '') {
        $parameters['municipio_busca'] = $search;
    }
    if ($state !== '') {
        $parameters['municipio_uf'] = $state;
    }
    return '/gov/?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986) . '#municipios';
}

$parties = [];
$deputyNames = [];
foreach ($deputies as $deputy) {
    $party = trim((string) ($deputy['siglaPartido'] ?? ''));
    $name = trim((string) ($deputy['nome'] ?? ''));
    if ($party !== '') {
        $parties[$party] = true;
    }
    if ($name !== '') {
        $deputyNames[$name] = true;
    }
}
$parties = array_keys($parties);
$deputyNames = array_keys($deputyNames);
sort($parties, SORT_NATURAL | SORT_FLAG_CASE);
sort($deputyNames, SORT_NATURAL | SORT_FLAG_CASE);
$expenseSample = array_slice($expenses, 0, 36);
$municipalityStates = is_array($meta['municipiosPorUf'] ?? null) ? $meta['municipiosPorUf'] : [];
$municipalityTotal = (int) ($municipalityMeta['total'] ?? 0);
$municipalityPage = (int) ($municipalityMeta['pagina'] ?? 1);
$municipalityTotalPages = max(1, (int) ($municipalityMeta['totalPaginas'] ?? 1));
$updatedAt = !empty($meta['ultimaImportacao'])
    ? date('d/m/Y \a\s H:i', strtotime((string) $meta['ultimaImportacao']))
    : 'aguardando importacao';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Mais de 5 mil municipios brasileiros, representantes, proposicoes e despesas parlamentares em fontes oficiais do IBGE e da Camara.">
  <meta name="theme-color" content="#052117">
  <title>Brasil em dados publicos | Leme Solucoes em TI</title>
  <style>
    :root{color-scheme:dark;--bg:#04130d;--panel:#0a2219;--panel2:#071a13;--line:#1d5b44;--green:#35e89d;--text:#effff8;--muted:#95b9ab;--orange:#ffca73}*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;min-height:100vh;background:radial-gradient(circle at 82% 0,#0c3d2b 0,transparent 32%),linear-gradient(135deg,#03100b,#071b13 70%);color:var(--text);font:15px/1.55 Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(1180px,calc(100% - 32px));margin:auto;padding:34px 0 70px}.nav{display:flex;align-items:center;justify-content:space-between;gap:22px;margin-bottom:54px}.brand{color:#dffff0;font-weight:900;letter-spacing:-.02em}.brand span{color:var(--green)}.links{display:flex;align-items:center;gap:17px}.links a{color:#b9ddce;font-size:12px;text-decoration:none}.links a:hover{color:var(--green)}.source{padding:8px 12px;border:1px solid var(--line);border-radius:999px;color:#bdfdde!important;font:700 11px ui-monospace,monospace}.hero{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:26px}.eyebrow{color:var(--green);font:800 12px ui-monospace,monospace;letter-spacing:.17em}.hero h1{max-width:850px;margin:9px 0 12px;font-size:clamp(38px,7vw,76px);line-height:.96;letter-spacing:-.067em}.hero p{max-width:720px;margin:0;color:var(--muted);font-size:16px}.hero-number{min-width:150px;padding:20px;border-left:1px solid var(--line)}.hero-number strong{display:block;color:var(--green);font-size:46px;line-height:1}.hero-number span{color:var(--muted);font-size:12px}.metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:30px 0 55px}.metric{padding:18px;border:1px solid var(--line);border-radius:14px;background:rgba(8,30,22,.75)}.metric strong{display:block;color:var(--green);font-size:27px}.metric span{color:var(--muted);font-size:12px}.section{scroll-margin-top:20px;margin-top:58px}.section-head{display:flex;align-items:end;justify-content:space-between;gap:24px;margin-bottom:20px}.section-tag{color:var(--green);font:800 11px ui-monospace,monospace;letter-spacing:.13em}.section h2{margin:4px 0 5px;font-size:clamp(27px,4vw,42px);letter-spacing:-.045em}.section-head p{max-width:670px;margin:0;color:var(--muted)}.counter{flex:0 0 auto;color:var(--green);font:800 12px ui-monospace,monospace}.tools{display:flex;gap:10px;margin-bottom:20px}.tools input,.tools select{min-height:46px;border:1px solid var(--line);border-radius:11px;background:#071b14;color:#fff;padding:10px 13px;font:inherit}.tools input{flex:1}.tools input:focus,.tools select:focus{outline:2px solid rgba(53,232,157,.25);border-color:var(--green)}.people{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.person{overflow:hidden;border:1px solid var(--line);border-radius:16px;background:rgba(8,30,22,.9);box-shadow:0 18px 50px rgba(0,0,0,.18);transition:transform .2s,border-color .2s}.person:hover{transform:translateY(-3px);border-color:#2a9c70}.photo{aspect-ratio:4/3;overflow:hidden;background:linear-gradient(135deg,#0c3b29,#071a13)}.photo img{width:100%;height:100%;object-fit:cover;object-position:50% 22%;filter:saturate(.9)}.info{padding:15px}.party,.type{display:inline-block;margin-bottom:7px;color:var(--green);font:800 11px ui-monospace,monospace;letter-spacing:.08em}.info h3{margin:0;font-size:17px;line-height:1.2}.info a,.proposal a,.expense a{display:inline-block;margin-top:9px;color:#bfffe1;font-size:12px;text-decoration:none}.info a:hover,.proposal a:hover,.expense a:hover{text-decoration:underline}.proposals{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.proposal{display:flex;flex-direction:column;min-height:210px;padding:20px;border:1px solid var(--line);border-radius:15px;background:rgba(8,30,22,.85)}.proposal .meta{display:flex;align-items:center;justify-content:space-between;gap:15px}.proposal time{color:var(--muted);font-size:11px}.proposal h3{margin:14px 0 0;font-size:16px;line-height:1.45;font-weight:650}.proposal a{margin-top:auto;padding-top:15px}.expenses{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.expense{display:flex;flex-direction:column;min-height:220px;padding:18px;border:1px solid var(--line);border-radius:15px;background:rgba(8,30,22,.85)}.expense .who{color:var(--green);font:800 11px ui-monospace,monospace}.expense .value{display:block;margin:11px 0 2px;color:var(--orange);font-size:24px;letter-spacing:-.035em}.expense time{color:var(--muted);font-size:11px}.expense h3{margin:14px 0 3px;font-size:13px;line-height:1.4}.expense p{margin:0;color:var(--muted);font-size:12px}.expense a{margin-top:auto;padding-top:13px}.empty,.error{grid-column:1/-1;padding:30px;border:1px dashed #2a684f;border-radius:14px;text-align:center;color:var(--muted)}.error{margin:20px 0;border-color:#7a4141;color:#ffd4d4}.note{margin-top:12px;color:var(--muted);font-size:12px}.footer{display:flex;justify-content:space-between;gap:20px;margin-top:58px;padding-top:18px;border-top:1px solid #164532;color:var(--muted);font-size:12px}.footer a{color:#b7fbdc}@media(max-width:980px){.people{grid-template-columns:repeat(3,1fr)}.expenses{grid-template-columns:repeat(2,1fr)}.links a:not(.source){display:none}}@media(max-width:720px){.wrap{width:min(100% - 20px,1180px);padding-top:24px}.nav{margin-bottom:38px}.hero{grid-template-columns:1fr}.hero-number{border-left:0;border-top:1px solid var(--line);padding:14px 0}.metrics{grid-template-columns:1fr;margin-bottom:42px}.tools{display:grid;grid-template-columns:1fr}.people,.proposals{grid-template-columns:repeat(2,1fr)}.section-head{align-items:start;flex-direction:column}.footer{flex-direction:column}}@media(max-width:500px){.people,.proposals,.expenses{grid-template-columns:1fr}.source{display:none}}
  </style>
  <style>
    .metrics{grid-template-columns:repeat(4,1fr)}.municipality-tools{display:grid;grid-template-columns:minmax(0,1fr) 220px auto auto;gap:10px;margin-bottom:20px}.municipality-tools input,.municipality-tools select{min-height:48px;border:1px solid var(--line);border-radius:11px;background:#071b14;color:#fff;padding:10px 13px;font:inherit}.municipality-tools input:focus,.municipality-tools select:focus{outline:2px solid rgba(53,232,157,.25);border-color:var(--green)}.municipality-tools button,.clear-filter{display:grid;place-items:center;min-height:48px;border:0;border-radius:11px;padding:10px 18px;background:var(--green);color:#042016;font:800 12px Inter,system-ui;text-decoration:none;cursor:pointer}.clear-filter{border:1px solid var(--line);background:#0a2219;color:#bfffe1}.municipalities{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.municipality{display:flex;flex-direction:column;min-height:205px;padding:18px;border:1px solid var(--line);border-radius:15px;background:rgba(8,30,22,.85)}.municipality .code{display:flex;align-items:center;justify-content:space-between;gap:10px;color:var(--green);font:800 10px ui-monospace,monospace;letter-spacing:.07em}.municipality h3{margin:16px 0 5px;font-size:19px;line-height:1.18}.municipality>p{margin:0;color:var(--muted);font-size:12px}.municipality dl{display:grid;gap:7px;margin:15px 0 0;padding-top:13px;border-top:1px solid #164532}.municipality dt{color:#6f9e8b;font-size:10px;text-transform:uppercase;letter-spacing:.07em}.municipality dd{margin:0;color:#d8f9eb;font-size:12px}.municipality a{margin-top:auto;padding-top:14px;color:#bfffe1;font-size:12px;text-decoration:none}.municipality a:hover{text-decoration:underline}.pagination{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:20px}.pagination a{padding:10px 14px;border:1px solid var(--line);border-radius:10px;color:#bfffe1;text-decoration:none}.pagination a:hover{border-color:var(--green)}.pagination span{color:var(--muted);font-size:12px}.sources{display:flex;flex-wrap:wrap;gap:8px 18px}@media(max-width:980px){.municipalities{grid-template-columns:repeat(3,1fr)}.municipality-tools{grid-template-columns:1fr 180px auto}}@media(max-width:720px){.metrics{grid-template-columns:1fr 1fr}.municipality-tools{grid-template-columns:1fr}.municipalities{grid-template-columns:repeat(2,1fr)}}@media(max-width:500px){.metrics,.municipalities{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <main class="wrap">
    <nav class="nav">
      <div class="brand">LEME <span>SOLUCOES</span></div>
      <div class="links"><a class="source" href="/gov/licitacoes.php" target="_blank" rel="noopener">LICITACOES ABERTAS ↗</a><a href="#municipios">Municipios</a><a href="#representantes">Representantes</a><a href="#proposicoes">Proposicoes</a><a href="#despesas">Despesas</a><a href="https://servicodados.ibge.gov.br/api/docs/localidades" target="_blank" rel="noreferrer">Fontes oficiais</a></div>
    </nav>

    <header class="hero">
      <div>
        <span class="eyebrow">BRASIL / TRANSPARENCIA PUBLICA</span>
        <h1>Brasil em dados oficiais</h1>
        <p>Explore o cadastro nacional de municipios do IBGE e dados de representacao politica de Santa Catarina em um unico painel.</p>
      </div>
      <div class="hero-number"><strong><?= number_format((int) ($meta['totais']['municipios'] ?? 0), 0, ',', '.') ?></strong><span>municipios cadastrados</span></div>
    </header>

    <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <section class="metrics" aria-label="Colecoes disponiveis">
      <div class="metric"><strong><?= number_format((int) ($meta['totais']['municipios'] ?? 0), 0, ',', '.') ?></strong><span>municipios de todas as UFs</span></div>
      <div class="metric"><strong><?= count($deputies) ?></strong><span>deputados federais de SC</span></div>
      <div class="metric"><strong><?= count($propositions) ?></strong><span>proposicoes recentes da amostra</span></div>
      <div class="metric"><strong><?= count($expenses) ?></strong><span>lancamentos recentes da amostra</span></div>
    </section>

    <section class="section" id="municipios">
      <header class="section-head">
        <div><span class="section-tag">COLECAO 01 / IBGE</span><h2>Municipios do Brasil</h2><p>Cadastro oficial com codigo IBGE, unidade da federacao, macrorregiao e divisoes geograficas imediata e intermediaria.</p></div>
        <span class="counter"><?= number_format($municipalityTotal, 0, ',', '.') ?> RESULTADOS</span>
      </header>
      <form class="municipality-tools" method="get" action="/gov/#municipios">
        <input name="municipio_busca" type="search" value="<?= h($municipalitySearch) ?>" placeholder="Buscar municipio, por exemplo: Joinville" aria-label="Buscar municipios">
        <select name="municipio_uf" aria-label="Filtrar municipios por estado">
          <option value="">Todas as UFs</option>
          <?php foreach ($municipalityStates as $state): ?>
            <option value="<?= h($state['sigla'] ?? '') ?>" <?= $municipalityState === ($state['sigla'] ?? '') ? 'selected' : '' ?>><?= h($state['nome'] ?? '') ?> (<?= (int) ($state['total'] ?? 0) ?>)</option>
          <?php endforeach; ?>
        </select>
        <button type="submit">BUSCAR</button>
        <?php if ($municipalitySearch !== '' || $municipalityState !== ''): ?><a class="clear-filter" href="/gov/#municipios">LIMPAR</a><?php endif; ?>
      </form>
      <div class="municipalities">
        <?php if (!$municipalities): ?>
          <div class="empty">Nenhum municipio encontrado com estes filtros.</div>
        <?php else: foreach ($municipalities as $municipality): ?>
          <article class="municipality">
            <div class="code"><span>CODIGO <?= (int) ($municipality['id'] ?? 0) ?></span><span><?= h($municipality['ufSigla'] ?? '') ?></span></div>
            <h3><?= h($municipality['nome'] ?? '') ?></h3>
            <p><?= h($municipality['ufNome'] ?? '') ?> &middot; <?= h($municipality['regiaoNome'] ?? '') ?></p>
            <dl>
              <div><dt>Regiao imediata</dt><dd><?= h($municipality['regiaoImediataNome'] ?? 'Nao informada') ?></dd></div>
              <div><dt>Regiao intermediaria</dt><dd><?= h($municipality['regiaoIntermediariaNome'] ?? 'Nao informada') ?></dd></div>
            </dl>
            <a href="https://servicodados.ibge.gov.br/api/v1/localidades/municipios/<?= (int) ($municipality['id'] ?? 0) ?>" target="_blank" rel="noreferrer">Ver registro no IBGE &rarr;</a>
          </article>
        <?php endforeach; endif; ?>
      </div>
      <?php if ($municipalityTotalPages > 1): ?>
        <nav class="pagination" aria-label="Paginacao dos municipios">
          <?php if ($municipalityPage > 1): ?><a href="<?= h(municipalityPageUrl($municipalityPage - 1, $municipalitySearch, $municipalityState)) ?>">&larr; Anterior</a><?php endif; ?>
          <span>Pagina <?= $municipalityPage ?> de <?= $municipalityTotalPages ?></span>
          <?php if ($municipalityPage < $municipalityTotalPages): ?><a href="<?= h(municipalityPageUrl($municipalityPage + 1, $municipalitySearch, $municipalityState)) ?>">Proxima &rarr;</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>

    <section class="section" id="representantes">
      <header class="section-head">
        <div><span class="section-tag">COLECAO 02</span><h2>Representantes de SC</h2><p>Deputados federais em exercicio, com partido, contato e acesso ao registro oficial.</p></div>
        <span class="counter"><span id="people-count"><?= count($deputies) ?></span> EXIBIDOS</span>
      </header>
      <div class="tools" aria-label="Filtros de representantes">
        <input id="people-search" type="search" placeholder="Buscar por nome ou partido" aria-label="Buscar representantes">
        <select id="party" aria-label="Filtrar por partido">
          <option value="">Todos os partidos</option>
          <?php foreach ($parties as $party): ?><option value="<?= h(strtolower($party)) ?>"><?= h($party) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="people" id="people-grid">
        <?php if (!$error && !$deputies): ?>
          <div class="empty">Aguardando a primeira importacao manual.</div>
        <?php else: foreach ($deputies as $deputy):
          $name = (string) ($deputy['nome'] ?? '');
          $party = (string) ($deputy['siglaPartido'] ?? '');
        ?>
          <article class="person" data-search="<?= h(strtolower($name . ' ' . $party)) ?>" data-party="<?= h(strtolower($party)) ?>">
            <div class="photo"><?php if (!empty($deputy['urlFoto'])): ?><img src="<?= h($deputy['urlFoto']) ?>" alt="Foto de <?= h($name) ?>" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?></div>
            <div class="info"><span class="party"><?= h($party) ?> / SC</span><h3><?= h($name) ?></h3><?php if (!empty($deputy['uri'])): ?><a href="<?= h($deputy['uri']) ?>" target="_blank" rel="noreferrer">Abrir registro oficial &rarr;</a><?php endif; ?></div>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <section class="section" id="proposicoes">
      <header class="section-head">
        <div><span class="section-tag">COLECAO 03</span><h2>Proposicoes recentes</h2><p>Projetos, requerimentos, pareceres e outras materias com autoria vinculada a Santa Catarina nos ultimos 45 dias.</p></div>
        <span class="counter"><span id="proposal-count"><?= count($propositions) ?></span> EXIBIDAS</span>
      </header>
      <div class="tools"><input id="proposal-search" type="search" placeholder="Buscar na ementa ou pelo tipo" aria-label="Buscar proposicoes"></div>
      <div class="proposals" id="proposal-grid">
        <?php if (!$propositions): ?>
          <div class="empty">Nenhuma proposicao disponivel nesta janela de tempo.</div>
        <?php else: foreach ($propositions as $proposition):
          $label = propositionLabel($proposition);
          $summary = (string) ($proposition['ementa'] ?? '');
          $id = (int) ($proposition['id'] ?? 0);
        ?>
          <article class="proposal" data-search="<?= h(strtolower($label . ' ' . $summary)) ?>">
            <div class="meta"><span class="type"><?= h($label) ?></span><time><?= !empty($proposition['dataApresentacao']) ? h(date('d/m/Y H:i', strtotime($proposition['dataApresentacao']))) : 'sem data' ?></time></div>
            <h3><?= h($summary) ?></h3>
            <?php if ($id > 0): ?><a href="https://www.camara.leg.br/proposicoesWeb/fichadetramitacao?idProposicao=<?= $id ?>" target="_blank" rel="noreferrer">Ver tramitacao oficial &rarr;</a><?php endif; ?>
          </article>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <section class="section" id="despesas">
      <header class="section-head">
        <div><span class="section-tag">COLECAO 04</span><h2>Despesas recentes</h2><p>Amostra dos ultimos lancamentos da Cota para o Exercicio da Atividade Parlamentar, com valor liquido, fornecedor e comprovante quando publicado.</p></div>
        <span class="counter"><span id="expense-count"><?= count($expenseSample) ?></span> EXIBIDAS</span>
      </header>
      <div class="tools">
        <select id="expense-deputy" aria-label="Filtrar despesas por deputado"><option value="">Todos os deputados</option><?php foreach ($deputyNames as $name): ?><option value="<?= h(strtolower($name)) ?>"><?= h($name) ?></option><?php endforeach; ?></select>
      </div>
      <div class="expenses" id="expense-grid">
        <?php if (!$expenseSample): ?>
          <div class="empty">Nenhuma despesa recente disponivel.</div>
        <?php else: foreach ($expenseSample as $expense):
          $deputyName = (string) ($expense['deputadoNome'] ?? '');
        ?>
          <article class="expense" data-deputy="<?= h(strtolower($deputyName)) ?>">
            <span class="who"><?= h($deputyName) ?> / <?= h($expense['siglaPartido'] ?? '') ?></span>
            <strong class="value"><?= h(moneyBr($expense['valorLiquido'] ?? 0)) ?></strong>
            <time><?= !empty($expense['dataDocumento']) ? h(date('d/m/Y', strtotime($expense['dataDocumento']))) : 'data nao informada' ?></time>
            <h3><?= h($expense['tipoDespesa'] ?? 'Tipo nao informado') ?></h3>
            <p><?= h($expense['fornecedor'] ?? 'Fornecedor nao informado') ?></p>
            <?php if (!empty($expense['urlDocumento'])): ?><a href="<?= h($expense['urlDocumento']) ?>" target="_blank" rel="noreferrer">Ver comprovante oficial &rarr;</a><?php endif; ?>
          </article>
        <?php endforeach; endif; ?>
      </div>
      <p class="note">Exibindo ate 36 registros entre os 8 lancamentos mais recentes de cada deputado no ano corrente. Esta amostra nao corresponde ao total mensal ou anual.</p>
    </section>

    <footer class="footer"><span>Ultima importacao: <?= h($updatedAt) ?></span><span class="sources"><a href="https://servicodados.ibge.gov.br/api/docs/localidades" target="_blank" rel="noreferrer">API de Localidades do IBGE</a><a href="https://dadosabertos.camara.leg.br/swagger/api.html" target="_blank" rel="noreferrer">Dados Abertos da Camara</a></span></footer>
  </main>

  <script>
    (() => {
      const normalize = (value) => value.toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, '');

      const peopleSearch = document.getElementById('people-search');
      const party = document.getElementById('party');
      const peopleCount = document.getElementById('people-count');
      const people = Array.from(document.querySelectorAll('.person'));
      const filterPeople = () => {
        const term = normalize(peopleSearch.value.trim());
        const selectedParty = party.value;
        let visible = 0;
        people.forEach((card) => {
          const show = normalize(card.dataset.search).includes(term) && (!selectedParty || card.dataset.party === selectedParty);
          card.hidden = !show;
          if (show) visible++;
        });
        peopleCount.textContent = String(visible);
      };
      peopleSearch?.addEventListener('input', filterPeople);
      party?.addEventListener('change', filterPeople);

      const proposalSearch = document.getElementById('proposal-search');
      const proposalCount = document.getElementById('proposal-count');
      const proposals = Array.from(document.querySelectorAll('.proposal'));
      proposalSearch?.addEventListener('input', () => {
        const term = normalize(proposalSearch.value.trim());
        let visible = 0;
        proposals.forEach((card) => {
          const show = normalize(card.dataset.search).includes(term);
          card.hidden = !show;
          if (show) visible++;
        });
        proposalCount.textContent = String(visible);
      });

      const expenseDeputy = document.getElementById('expense-deputy');
      const expenseCount = document.getElementById('expense-count');
      const expenseCards = Array.from(document.querySelectorAll('.expense'));
      expenseDeputy?.addEventListener('change', () => {
        let visible = 0;
        expenseCards.forEach((card) => {
          const show = !expenseDeputy.value || card.dataset.deputy === expenseDeputy.value;
          card.hidden = !show;
          if (show) visible++;
        });
        expenseCount.textContent = String(visible);
      });
    })();
  </script>
</body>
</html>
