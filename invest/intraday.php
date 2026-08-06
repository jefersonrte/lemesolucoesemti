<?php
/**
 * intraday.php -- API (JSON) com os candles intraday para o grafico ao vivo.
 * Uso: intraday.php?ticker=PETR4&interval=1m&range=1d
 */
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
@set_time_limit(30);

$ticker = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_GET['ticker'] ?? 'PETR4'));

$intervalos = ['1m', '5m', '15m'];
$ranges     = ['1d', '5d'];
$interval = in_array($_GET['interval'] ?? '', $intervalos, true) ? $_GET['interval'] : '1m';
$range    = in_array($_GET['range'] ?? '', $ranges, true)       ? $_GET['range']    : '1d';

if ($ticker === '') { echo json_encode(['erro' => 'ticker invalido']); exit; }

$d = serie_intraday($ticker, $interval, $range);
$d['ticker'] = $ticker;
$d['hora']   = date('H:i:s');
echo json_encode($d);
