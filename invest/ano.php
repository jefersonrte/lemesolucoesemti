<?php
/**
 * ano.php -- Tela "Ano": desempenho no ano (YTD) das acoes, melhores e piores.
 * Usa cache de 1 hora (o calculo baixa 1 ano de dados de muitos papeis).
 */
require_once __DIR__ . '/lib.php';

@set_time_limit(90); // a 1a carga baixa ~33 papeis; depois vem do cache (1h)
$dados = desempenho_ano($ANO_TICKERS);

$sobem = array_filter($dados, fn($d) => $d['ytd'] >= 0);
$melhor = $dados[0] ?? null;
$pior   = end($dados) ?: null;

$topo = array_slice($dados, 0, 8);
$baixo = array_slice($dados, -8);
$grafico = array_merge($topo, $baixo);
$maxAbs = 1;
foreach ($grafico as $g) $maxAbs = max($maxAbs, abs($g['ytd']));

$active = 'ano';
$titulo = 'Desempenho no ano';
require __DIR__ . '/header.php';
?>
  <h1>Desempenho no ano</h1>
  <div class="sub">Retorno acumulado em <?= date('Y') ?> (YTD) · <?= count($dados) ?> ações · atualiza a cada 1 h</div>

  <div class="aviso">
    ⚠️ Isto é <strong>retrospectiva</strong>: o que já aconteceu no ano. "Melhor do ano" não significa
    que vai continuar subindo. Não é recomendação.
  </div>

  <?php if ($melhor && $pior): ?>
  <div class="cards">
    <div class="card"><div class="rot">Melhor do ano</div>
      <div class="val"><?= $melhor['ticker'] ?> <span class="pos"><?= pct($melhor['ytd']) ?></span></div></div>
    <div class="card"><div class="rot">Pior do ano</div>
      <div class="val"><?= $pior['ticker'] ?> <span class="neg"><?= pct($pior['ytd']) ?></span></div></div>
    <div class="card"><div class="rot">Placar</div>
      <div class="val"><?= count($sobem) ?> <span style="font-size:.9rem;color:#94a3b8">sobem</span> ·
        <?= count($dados) - count($sobem) ?> <span style="font-size:.9rem;color:#94a3b8">caem</span></div></div>
  </div>
  <?php endif; ?>

  <?php if (empty($dados)): ?>
    <p class="vazio">Não consegui montar o ranking agora. Recarregue em instantes.</p>
  <?php else: ?>
  <h2>Melhores e piores (8 de cada)</h2>
  <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:26px;">
    <?php foreach ($grafico as $g):
        $largura = round(abs($g['ytd']) / $maxAbs * 100);
        $cor = $g['ytd'] >= 0 ? '#22c55e' : '#ef4444';
    ?>
    <div style="display:flex; align-items:center; gap:10px;">
      <div style="width:66px; font-size:.85rem; font-weight:700;"><?= $g['ticker'] ?></div>
      <div style="flex:1; background:#1e293b; border-radius:6px; height:22px; position:relative;">
        <div style="width:<?= $largura ?>%; background:<?= $cor ?>; height:100%; border-radius:6px;"></div>
      </div>
      <div style="width:64px; text-align:right; font-size:.85rem; font-variant-numeric:tabular-nums;"
           class="<?= $g['ytd'] >= 0 ? 'pos' : 'neg' ?>"><?= pct($g['ytd']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <h2>Tabela completa</h2>
  <div class="overflow">
  <table>
    <tr><th>#</th><th>Papel</th><th class="num">Preço</th><th class="num">Retorno no ano</th></tr>
    <?php foreach ($dados as $i => $d): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><strong><?= $d['ticker'] ?></strong></td>
        <td class="num"><?= brl($d['preco']) ?></td>
        <td class="num <?= $d['ytd'] >= 0 ? 'pos' : 'neg' ?>"><?= pct($d['ytd']) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
