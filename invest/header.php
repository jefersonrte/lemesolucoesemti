<?php
/**
 * header.php -- topo compartilhado (menu de navegacao + estilo).
 * Cada pagina define $active ('painel'|'sinais'|'ano'|'sobre') e $titulo antes de incluir.
 */
$active = $active ?? '';
$titulo = $titulo ?? 'Bot de Bolsa';
$itens = [
    'painel'  => ['painel.php',  'Painel'],
    'grafico' => ['grafico.php', 'Gráfico ao vivo'],
    'sinais'  => ['scanner.php', 'Sinais de hoje'],
    'ano'     => ['ano.php',     'Ano'],
    'sobre'   => ['sobre.php',   'Sobre'],
];
?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titulo) ?> — Bot de Bolsa</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
         margin: 0; background: #0f172a; color: #e2e8f0; }
  a { color: inherit; text-decoration: none; }
  .topo { position: sticky; top: 0; z-index: 10; background: #0b1220e6;
          backdrop-filter: blur(6px); border-bottom: 1px solid #1e293b; }
  .topo .barra { max-width: 960px; margin: 0 auto; padding: 12px 16px;
                 display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
  .marca { font-weight: 700; font-size: 1.05rem; }
  .nav { display: flex; gap: 6px; flex-wrap: wrap; }
  .nav a { padding: 7px 12px; border-radius: 8px; font-size: .9rem; color: #94a3b8; }
  .nav a:hover { background: #1e293b; color: #e2e8f0; }
  .nav a.on { background: #1d4ed8; color: #fff; }
  .wrap { max-width: 960px; margin: 0 auto; padding: 22px 16px 64px; }
  h1 { font-size: 1.4rem; margin: 0 0 4px; }
  .sub { color: #94a3b8; font-size: .85rem; }
  .aviso { background: #422006; color: #fed7aa; border: 1px solid #92400e;
           padding: 10px 14px; border-radius: 8px; font-size: .82rem; margin: 14px 0 22px; }
  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
           gap: 12px; margin-bottom: 22px; }
  .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 16px; }
  .card .rot { font-size: .72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }
  .card .val { font-size: 1.5rem; font-weight: 700; margin-top: 4px; }
  .pos { color: #4ade80; } .neg { color: #f87171; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 26px; font-size: .9rem; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #334155; }
  th { color: #94a3b8; font-weight: 600; font-size: .76rem; text-transform: uppercase; }
  td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
  .tag { font-size: .7rem; padding: 2px 9px; border-radius: 999px; font-weight: 700; white-space: nowrap; }
  .tag.COMPRAR, .tag.COMPRA { background: #052e16; color: #4ade80; }
  .tag.VENDER,  .tag.VENDA  { background: #450a0a; color: #f87171; }
  .tag.ALTA  { background: #0c2c1a; color: #86efac; }
  .tag.BAIXA { background: #3b1113; color: #fca5a5; }
  .vazio { color: #64748b; font-style: italic; }
  .overflow { overflow-x: auto; }
  .aovivo { display: inline-flex; align-items: center; gap: 7px; font-size: .8rem; color: #94a3b8; }
  .dot { width: 8px; height: 8px; border-radius: 50%; background: #4ade80; animation: pulse 1.6s infinite; }
  @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }
  h2 { font-size: 1rem; margin: 6px 0 8px; }
  footer { color: #64748b; font-size: .76rem; margin-top: 30px; line-height: 1.6; }
  .btn { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; padding: 7px 12px;
         border-radius: 8px; font-size: .85rem; cursor: pointer; }
  .btn:hover { background: #273449; }
</style>
</head>
<body>
<div class="topo">
  <div class="barra">
    <span class="marca">🤖 Bot de Bolsa</span>
    <nav class="nav">
      <?php foreach ($itens as $k => [$href, $rot]): ?>
        <a class="<?= $k === $active ? 'on' : '' ?>" href="<?= $href ?>"><?= $rot ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
<div class="wrap">
