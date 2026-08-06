<?php
/**
 * bot_bolsa.php  -- O ROBO (roda pelo cron 1x por dia).
 *
 * Para cada papel: baixa cotacoes, decide pela estrategia e executa a
 * compra/venda na carteira SIMULADA (carteira.json).
 *
 * Como e chamado:
 *   - Pelo cron (linha de comando):  php bot_bolsa.php
 *   - Ou pela web, com o segredo:    bot_bolsa.php?secret=SEU_SEGREDO
 */

require_once __DIR__ . '/lib.php';

// Se for chamado pela web, exige o segredo (evita que qualquer um dispare o robo)
$viaWeb = PHP_SAPI !== 'cli';
if ($viaWeb) {
    header('Content-Type: text/plain; charset=utf-8');
    $segredo = (string) ($_GET['secret'] ?? '');
    if (CRON_SECRET === '' || $segredo === '' || !hash_equals(CRON_SECRET, $segredo)) {
        http_response_code(403);
        exit("Acesso negado.\n");
    }
}

$log = [];
$log[] = "=== Bot de Bolsa (paper trading) — " . date('Y-m-d H:i:s') . " ===";

$carteira = carregar_carteira();

foreach ($BOT_TICKERS as $ticker) {
    $fech = baixar_fechamentos($ticker);
    if (!$fech) {
        $log[] = "[$ticker] nao consegui baixar cotacoes — pulando.";
        continue;
    }

    $preco    = (float) end($fech);
    $decisao  = decisao_do_dia($fech);
    $log[]    = "[$ticker] preco R$ " . number_format($preco, 2, ',', '.') . " — decisao: $decisao";

    if ($decisao === 'COMPRAR') {
        $qtd = (int) floor(VALOR_POR_COMPRA / $preco);
        $log[] = "   -> " . comprar($carteira, $ticker, $preco, $qtd);
    } elseif ($decisao === 'VENDER') {
        $log[] = "   -> " . vender($carteira, $ticker, $preco);
    } else {
        $log[] = "   -> nada a fazer.";
    }
}

salvar_carteira($carteira);

// Preco atual de cada papel que temos, para calcular o patrimonio
$precos = [];
foreach (array_keys($carteira['posicoes']) as $t) {
    $p = preco_atual($t);
    if ($p !== null) $precos[$t] = $p;
}

$log[] = "Saldo: R$ " . number_format($carteira['saldo'], 2, ',', '.');
$log[] = "Patrimonio total: R$ " . number_format(patrimonio($carteira, $precos), 2, ',', '.');
$log[] = "=== fim ===";

echo implode("\n", $log) . "\n";
