<?php
/**
 * lib.php
 * Funcoes do bot: baixar cotacoes, calcular a estrategia e operar a
 * carteira SIMULADA (dinheiro ficticio) guardada em carteira.json.
 */

require_once __DIR__ . '/config_bot.php';

/* ------------------------------------------------------------------ *
 *  COTACOES (Yahoo Finance, sem token)
 * ------------------------------------------------------------------ */

function http_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; bot-bolsa/1.0)',
    ]);
    $resp = curl_exec($ch);
    $erro = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        error_log("bot-bolsa: erro ao acessar $url : $erro");
        return null;
    }
    return $resp;
}

/** Baixa os fechamentos diarios (ultimos ~3 meses) de um papel da B3. */
function baixar_fechamentos(string $ticker): array
{
    $simbolo = $ticker . '.SA'; // B3 no Yahoo usa sufixo .SA
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/"
         . rawurlencode($simbolo) . "?range=3mo&interval=1d";

    $resp = http_get($url);
    if ($resp === null) return [];

    $j = json_decode($resp, true);
    $res = $j['chart']['result'][0] ?? null;
    if (!$res) return [];

    $closes = $res['indicators']['quote'][0]['close'] ?? [];
    $saida = [];
    foreach ($closes as $c) {
        if ($c !== null) $saida[] = (float) $c; // ignora dias sem cotacao
    }
    return $saida;
}

/** Ultimo preco conhecido de um papel (ou null se nao conseguir baixar). */
function preco_atual(string $ticker): ?float
{
    $fech = baixar_fechamentos($ticker);
    return $fech ? (float) end($fech) : null;
}

/* ------------------------------------------------------------------ *
 *  ESTRATEGIA (cruzamento de medias moveis)
 * ------------------------------------------------------------------ */

/** Media dos $n valores que terminam no indice $fim. Null se nao houver dados. */
function media(array $v, int $n, int $fim): ?float
{
    if ($fim + 1 < $n) return null;
    $soma = 0.0;
    for ($i = $fim - $n + 1; $i <= $fim; $i++) $soma += $v[$i];
    return $soma / $n;
}

/** Decide a acao de HOJE: "COMPRAR", "VENDER" ou "ESPERAR". */
function decisao_do_dia(array $close): string
{
    $n = count($close);
    if ($n < MEDIA_LONGA + 1) return 'ESPERAR';

    $i = $n - 1; // hoje
    $curtaHoje  = media($close, MEDIA_CURTA, $i);
    $longaHoje  = media($close, MEDIA_LONGA, $i);
    $curtaOntem = media($close, MEDIA_CURTA, $i - 1);
    $longaOntem = media($close, MEDIA_LONGA, $i - 1);

    $acimaHoje  = $curtaHoje  > $longaHoje;
    $acimaOntem = $curtaOntem > $longaOntem;

    if ($acimaHoje && !$acimaOntem)  return 'COMPRAR'; // cruzou pra cima
    if (!$acimaHoje && $acimaOntem)  return 'VENDER';  // cruzou pra baixo
    return 'ESPERAR';
}

/* ------------------------------------------------------------------ *
 *  CARTEIRA SIMULADA (arquivo JSON)
 * ------------------------------------------------------------------ */

function carregar_carteira(): array
{
    if (is_file(ARQUIVO_CARTEIRA)) {
        $dados = json_decode(file_get_contents(ARQUIVO_CARTEIRA), true);
        if (is_array($dados)) return $dados;
    }
    return ['saldo' => SALDO_INICIAL, 'posicoes' => [], 'historico' => []];
}

function salvar_carteira(array $c): void
{
    file_put_contents(
        ARQUIVO_CARTEIRA,
        json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function registrar(array &$c, string $tipo, string $ticker, float $preco,
                   int $qtd, float $taxa, ?float $lucro = null): void
{
    $c['historico'][] = [
        'data'       => date('Y-m-d H:i:s'),
        'tipo'       => $tipo,
        'ticker'     => $ticker,
        'preco'      => round($preco, 2),
        'quantidade' => $qtd,
        'taxa'       => round($taxa, 2),
        'lucro'      => $lucro === null ? null : round($lucro, 2),
    ];
}

/** Compra $qtd acoes se houver saldo. Retorna mensagem do que aconteceu. */
function comprar(array &$c, string $ticker, float $preco, int $qtd): string
{
    $custo = $preco * $qtd;
    $taxa  = $custo * TAXA_POR_OPERACAO;
    $total = $custo + $taxa;

    if ($qtd <= 0)             return "sem quantidade para comprar";
    if ($total > $c['saldo'])  return "saldo insuficiente";

    $c['saldo'] -= $total;

    if (isset($c['posicoes'][$ticker])) {
        $p = $c['posicoes'][$ticker];
        $qtdTotal = $p['quantidade'] + $qtd;
        $p['preco_medio'] = ($p['preco_medio'] * $p['quantidade'] + $preco * $qtd) / $qtdTotal;
        $p['quantidade']  = $qtdTotal;
        $c['posicoes'][$ticker] = $p;
    } else {
        $c['posicoes'][$ticker] = ['quantidade' => $qtd, 'preco_medio' => $preco];
    }

    registrar($c, 'COMPRA', $ticker, $preco, $qtd, $taxa);
    return "COMPRA de $qtd x $ticker a R$ " . number_format($preco, 2, ',', '.');
}

/** Vende toda a posicao do papel. Retorna mensagem do que aconteceu. */
function vender(array &$c, string $ticker, float $preco): string
{
    if (empty($c['posicoes'][$ticker]['quantidade'])) {
        return "nao ha posicao em $ticker para vender";
    }
    $p   = $c['posicoes'][$ticker];
    $qtd = $p['quantidade'];

    $receita = $preco * $qtd;
    $taxa    = $receita * TAXA_POR_OPERACAO;
    $lucro   = ($preco - $p['preco_medio']) * $qtd - $taxa;

    $c['saldo'] += $receita - $taxa;
    unset($c['posicoes'][$ticker]);

    registrar($c, 'VENDA', $ticker, $preco, $qtd, $taxa, $lucro);
    $sinal = $lucro >= 0 ? '+' : '';
    return "VENDA de $qtd x $ticker a R$ " . number_format($preco, 2, ',', '.')
         . " (resultado: {$sinal}R$ " . number_format($lucro, 2, ',', '.') . ")";
}

/** Patrimonio = dinheiro + valor de mercado das acoes. */
function patrimonio(array $c, array $precos): float
{
    $total = $c['saldo'];
    foreach ($c['posicoes'] as $ticker => $p) {
        $preco = $precos[$ticker] ?? $p['preco_medio'];
        $total += $preco * $p['quantidade'];
    }
    return $total;
}

/* ------------------------------------------------------------------ *
 *  FORMATACAO (usada pelas telas)
 * ------------------------------------------------------------------ */

if (!function_exists('brl')) {
    function brl($v): string { return 'R$ ' . number_format((float)$v, 2, ',', '.'); }
}
function pct($v, int $casas = 1): string
{
    return ($v >= 0 ? '+' : '') . number_format((float)$v, $casas, ',', '.') . '%';
}

/* ------------------------------------------------------------------ *
 *  CACHE EM ARQUIVO (evita bater no Yahoo a cada carregamento)
 *  Os arquivos cache_*.json ficam protegidos pelo .htaccess.
 * ------------------------------------------------------------------ */

function cache_ler(string $chave, int $ttl): ?array
{
    $arq = __DIR__ . "/cache_$chave.json";
    if (is_file($arq) && (time() - filemtime($arq)) < $ttl) {
        $d = json_decode(file_get_contents($arq), true);
        if (is_array($d)) return $d;
    }
    return null;
}

function cache_gravar(string $chave, array $dados): void
{
    @file_put_contents(__DIR__ . "/cache_$chave.json", json_encode($dados));
}

/* ------------------------------------------------------------------ *
 *  SERIES COM DATA (para o desempenho no ANO)
 * ------------------------------------------------------------------ */

/** Baixa fechamentos + datas de um papel. Retorna ['ts'=>[], 'close'=>[]]. */
function serie_com_datas(string $ticker, string $range = '1y'): array
{
    $simbolo = $ticker . '.SA';
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/"
         . rawurlencode($simbolo) . "?range=$range&interval=1d";
    $resp = http_get($url);
    if ($resp === null) return ['ts' => [], 'close' => []];

    $res = json_decode($resp, true)['chart']['result'][0] ?? null;
    if (!$res) return ['ts' => [], 'close' => []];

    $ts = $res['timestamp'] ?? [];
    $cl = $res['indicators']['quote'][0]['close'] ?? [];
    $outTs = []; $outCl = [];
    foreach ($cl as $i => $c) {
        if ($c !== null && isset($ts[$i])) { $outTs[] = $ts[$i]; $outCl[] = (float)$c; }
    }
    return ['ts' => $outTs, 'close' => $outCl];
}

/* ------------------------------------------------------------------ *
 *  ANALISE POR PAPEL (para a tela "Sinais de hoje")
 * ------------------------------------------------------------------ */

/** Analisa 1 papel: preco, sinal detalhado, forca (%) e retorno de ~1 mes. */
function analisar_ticker(string $ticker): ?array
{
    $c = baixar_fechamentos($ticker);
    $n = count($c);
    if ($n < MEDIA_LONGA + 1) return null;

    $i = $n - 1;
    $curtaHoje  = media($c, MEDIA_CURTA, $i);
    $longaHoje  = media($c, MEDIA_LONGA, $i);
    $curtaOntem = media($c, MEDIA_CURTA, $i - 1);
    $longaOntem = media($c, MEDIA_LONGA, $i - 1);

    $acimaHoje  = $curtaHoje  > $longaHoje;
    $acimaOntem = $curtaOntem > $longaOntem;

    if     ($acimaHoje && !$acimaOntem)  $sinal = 'COMPRAR';
    elseif (!$acimaHoje && $acimaOntem)  $sinal = 'VENDER';
    elseif ($acimaHoje)                  $sinal = 'ALTA';
    else                                 $sinal = 'BAIXA';

    $preco = $c[$i];
    $forca = $longaHoje > 0 ? ($curtaHoje / $longaHoje - 1) * 100 : 0;
    $base  = $n >= 22 ? $c[$n - 22] : $c[0];
    $ret1m = $base > 0 ? ($preco / $base - 1) * 100 : 0;

    return [
        'ticker' => $ticker, 'preco' => $preco, 'sinal' => $sinal,
        'forca'  => $forca,  'ret1m' => $ret1m,
    ];
}

/** Scanner da watchlist (com cache de 5 min), ja ordenado. */
function scanner(array $tickers): array
{
    $cache = cache_ler('scanner', 300);
    if ($cache !== null) return $cache;

    $linhas = [];
    foreach ($tickers as $t) {
        $a = analisar_ticker($t);
        if ($a) $linhas[] = $a;
    }
    $peso = ['COMPRAR' => 0, 'ALTA' => 1, 'VENDER' => 2, 'BAIXA' => 3];
    usort($linhas, function ($x, $y) use ($peso) {
        $px = $peso[$x['sinal']] ?? 9; $py = $peso[$y['sinal']] ?? 9;
        if ($px !== $py) return $px <=> $py;
        return $y['forca'] <=> $x['forca'];
    });

    cache_gravar('scanner', $linhas);
    return $linhas;
}

/* ------------------------------------------------------------------ *
 *  DESEMPENHO NO ANO (tela "Ano")
 * ------------------------------------------------------------------ */

/** Retorno de cada papel desde o 1o pregao do ano (cache de 1 h), ordenado. */
function desempenho_ano(array $tickers): array
{
    $cache = cache_ler('ano', 3600);
    if ($cache !== null) return $cache;

    $corte = mktime(0, 0, 0, 1, 1, (int)date('Y')); // 1o de janeiro deste ano
    $linhas = [];
    foreach ($tickers as $t) {
        $s = serie_com_datas($t, '1y');
        if (count($s['close']) < 30) continue;

        $baseAno = null;
        foreach ($s['ts'] as $k => $ts) {
            if ($ts >= $corte) { $baseAno = $s['close'][$k]; break; }
        }
        if ($baseAno === null) $baseAno = $s['close'][0];

        $atual = end($s['close']);
        $ytd   = $baseAno > 0 ? ($atual / $baseAno - 1) * 100 : 0;
        $linhas[] = ['ticker' => $t, 'preco' => $atual, 'ytd' => $ytd];
    }
    usort($linhas, fn($a, $b) => $b['ytd'] <=> $a['ytd']);

    cache_gravar('ano', $linhas);
    return $linhas;
}

/* ------------------------------------------------------------------ *
 *  CANDLES INTRADAY (para a tela de grafico ao vivo / day trade)
 * ------------------------------------------------------------------ */

/**
 * Candles OHLC intraday de um papel.
 * $interval: '1m'|'5m'|'15m'  |  $range: '1d'|'5d'
 * Retorna ['candles'=>[['time','open','high','low','close']...],
 *          'preco'=>float, 'prevClose'=>float].
 * Os horarios ja vem convertidos para o fuso de Brasilia (UTC-3).
 */
function serie_intraday(string $ticker, string $interval = '1m', string $range = '1d'): array
{
    $simbolo = $ticker . '.SA';
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/"
         . rawurlencode($simbolo) . "?range=$range&interval=$interval";

    $resp = http_get($url);
    $vazio = ['candles' => [], 'preco' => null, 'prevClose' => null];
    if ($resp === null) return $vazio;

    $res = json_decode($resp, true)['chart']['result'][0] ?? null;
    if (!$res) return $vazio;

    $ts = $res['timestamp'] ?? [];
    $q  = $res['indicators']['quote'][0] ?? [];
    $o = $q['open'] ?? []; $h = $q['high'] ?? [];
    $l = $q['low']  ?? []; $c = $q['close'] ?? [];

    $OFFSET = -3 * 3600; // Brasilia (UTC-3): o grafico usa o eixo em UTC
    $candles = [];
    foreach ($ts as $i => $t) {
        if (!isset($c[$i]) || $c[$i] === null) continue; // pula candle sem dado
        $candles[] = [
            'time'  => (int)$t + $OFFSET,
            'open'  => round((float)($o[$i] ?? $c[$i]), 2),
            'high'  => round((float)($h[$i] ?? $c[$i]), 2),
            'low'   => round((float)($l[$i] ?? $c[$i]), 2),
            'close' => round((float)$c[$i], 2),
        ];
    }

    return [
        'candles'   => $candles,
        'preco'     => $res['meta']['regularMarketPrice'] ?? ($candles ? end($candles)['close'] : null),
        'prevClose' => $res['meta']['chartPreviousClose'] ?? null,
    ];
}

/* ------------------------------------------------------------------ *
 *  COTACOES AO VIVO (uma unica chamada para varios papeis)
 * ------------------------------------------------------------------ */

/** Precos "ao vivo" (atraso ~15min do Yahoo) de varios papeis de uma vez. */
function cotacoes_ao_vivo(array $tickers): array
{
    if (!$tickers) return [];
    $simbolos = implode(',', array_map(fn($t) => $t . '.SA', $tickers));
    $url = "https://query1.finance.yahoo.com/v8/finance/spark?symbols="
         . rawurlencode($simbolos) . "&range=1d&interval=5m";

    $resp = http_get($url);
    $saida = [];
    if ($resp === null) return $saida;

    // Formato atual do spark: { "PETR4.SA": { close:[...], previousClose:.. }, ... }
    $j = json_decode($resp, true);
    if (!is_array($j)) return $saida;

    foreach ($j as $sym => $node) {
        if (!is_array($node) || strpos($sym, '.SA') === false) continue;
        $papel  = str_replace('.SA', '', $sym);
        $closes = $node['close'] ?? [];
        $preco  = null;
        for ($i = count($closes) - 1; $i >= 0; $i--) {
            if ($closes[$i] !== null) { $preco = (float)$closes[$i]; break; }
        }
        if ($preco === null && isset($node['previousClose'])) {
            $preco = (float)$node['previousClose'];
        }
        if ($papel && $preco !== null) $saida[$papel] = round($preco, 2);
    }
    return $saida;
}
