<?php
/**
 * cotacoes.php -- API simples (JSON) com os precos "ao vivo".
 * Chamado pelo JavaScript das telas para atualizar os precos sem recarregar.
 *
 * Uso:  cotacoes.php?tickers=PETR4,VALE3,ITUB4
 * Resposta: {"hora":"14:32:05","precos":{"PETR4":42.21,"VALE3":75.24}}
 */

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$bruto = $_GET['tickers'] ?? '';
$tickers = [];
foreach (explode(',', $bruto) as $t) {
    $t = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $t)); // sanitiza
    if ($t !== '') $tickers[] = $t;
}
$tickers = array_slice(array_unique($tickers), 0, 25); // limite de seguranca

echo json_encode([
    'hora'   => date('H:i:s'),
    'precos' => $tickers ? cotacoes_ao_vivo($tickers) : (object)[],
]);
