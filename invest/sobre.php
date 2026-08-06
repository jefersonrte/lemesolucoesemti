<?php
/** sobre.php -- Explica o que e o projeto, a estrategia e os limites. */
require_once __DIR__ . '/lib.php';
$active = 'sobre';
$titulo = 'Sobre';
require __DIR__ . '/header.php';
?>
  <h1>Sobre este bot</h1>
  <div class="sub">Como funciona, o que ele faz e o que ele NÃO faz.</div>

  <div class="aviso">
    ⚠️ <strong>Dinheiro fictício.</strong> Ferramenta de estudo. Não é recomendação de
    investimento nem consultoria financeira. Nunca use dinheiro real que não pode perder.
  </div>

  <h2>O que é</h2>
  <p style="line-height:1.7;color:#cbd5e1;">
    Um robô que acompanha ações da B3 e opera numa <strong>carteira simulada</strong>
    (paper trading). Serve para aprender como funciona um sistema de trading automatizado,
    sem arriscar nada.
  </p>

  <h2>A estratégia (médias móveis <?= MEDIA_CURTA ?>/<?= MEDIA_LONGA ?>)</h2>
  <ul style="line-height:1.8;color:#cbd5e1;">
    <li>Calcula duas médias do preço: uma curta (<?= MEDIA_CURTA ?> dias) e uma longa (<?= MEDIA_LONGA ?> dias).</li>
    <li>Curta cruza <strong>para cima</strong> da longa → sinal de <strong>comprar</strong>.</li>
    <li>Curta cruza <strong>para baixo</strong> → sinal de <strong>vender</strong>.</li>
    <li>É uma das estratégias mais clássicas — simples, e erra bastante. O objetivo é aprender.</li>
  </ul>

  <h2>As telas</h2>
  <ul style="line-height:1.8;color:#cbd5e1;">
    <li><strong>Painel</strong>: a carteira simulada — saldo, ações e histórico de operações.</li>
    <li><strong>Sinais de hoje</strong>: o que a estratégia sinaliza agora, com preços ao vivo.</li>
    <li><strong>Ano</strong>: as melhores e piores ações no acumulado do ano.</li>
  </ul>

  <h2>Limites (importante)</h2>
  <p style="line-height:1.7;color:#cbd5e1;">
    As cotações vêm do Yahoo Finance com atraso de ~15 minutos. A estratégia olha só o preço —
    ignora fundamentos, notícias, dividendos e risco. Não sou consultor financeiro:
    para decisões com dinheiro real, procure um profissional certificado (CVM/CEA).
  </p>
<?php require __DIR__ . '/footer.php'; ?>
