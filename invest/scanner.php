<?php
/**
 * scanner.php -- Tela "Sinais de hoje".
 * Roda a estrategia (medias moveis) sobre a watchlist e mostra o que ela sinaliza,
 * com os precos se atualizando sozinhos (ao vivo).
 */
require_once __DIR__ . '/lib.php';

@set_time_limit(60); // a 1a carga baixa a watchlist; depois vem do cache (5min)
$linhas = scanner($WATCHLIST);

$rotulo = [
    'COMPRAR' => 'Comprar', 'VENDER' => 'Vender',
    'ALTA'    => 'Em alta', 'BAIXA'  => 'Em baixa',
];

$active = 'sinais';
$titulo = 'Sinais de hoje';
require __DIR__ . '/header.php';
?>
  <h1>Sinais de hoje</h1>
  <div class="sub">
    A estratégia lida sobre <?= count($linhas) ?> ações da B3 ·
    <span class="aovivo"><span class="dot"></span> ao vivo · atualizado <span id="hora">agora</span></span>
  </div>

  <div class="aviso">
    ⚠️ Isto é a leitura mecânica de uma estratégia simples sobre o preço.
    <strong>Não é recomendação de compra.</strong> "Em alta" descreve o que já subiu — não prevê o futuro.
  </div>

  <div style="margin-bottom:14px;">
    <button class="btn" id="atualizar">↻ Atualizar agora</button>
  </div>

  <div class="overflow">
  <table>
    <tr>
      <th>Papel</th><th>Sinal</th>
      <th class="num">Preço</th><th class="num">Força</th><th class="num">Retorno 1 mês</th>
    </tr>
    <?php if (empty($linhas)): ?>
      <tr><td colspan="5" class="vazio">Não consegui baixar as cotações agora. Tente atualizar em instantes.</td></tr>
    <?php else: foreach ($linhas as $l): ?>
      <tr>
        <td><strong><?= htmlspecialchars($l['ticker']) ?></strong></td>
        <td><span class="tag <?= $l['sinal'] ?>"><?= $rotulo[$l['sinal']] ?? $l['sinal'] ?></span></td>
        <td class="num" data-preco="<?= htmlspecialchars($l['ticker']) ?>"><?= brl($l['preco']) ?></td>
        <td class="num <?= $l['forca'] >= 0 ? 'pos' : 'neg' ?>"><?= pct($l['forca']) ?></td>
        <td class="num <?= $l['ret1m'] >= 0 ? 'pos' : 'neg' ?>"><?= pct($l['ret1m']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </table>
  </div>

<script>
const TICKERS = <?= json_encode(array_column($linhas, 'ticker')) ?>;
function brl(v){ return 'R$ ' + v.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
async function atualizar(){
  if (!TICKERS.length) return;
  try {
    const r = await fetch('cotacoes.php?tickers=' + TICKERS.join(','), {cache:'no-store'});
    const j = await r.json();
    for (const [t, p] of Object.entries(j.precos || {})) {
      const cel = document.querySelector('[data-preco="'+t+'"]');
      if (cel) { cel.textContent = brl(p); cel.style.transition='color .3s'; cel.style.color='#7dd3fc';
                 setTimeout(()=>cel.style.color='', 400); }
    }
    if (j.hora) document.getElementById('hora').textContent = j.hora;
  } catch(e) { /* silencioso: mantem o ultimo preco */ }
}
document.getElementById('atualizar').addEventListener('click', atualizar);
atualizar();
setInterval(atualizar, 45000); // a cada 45 segundos
</script>
<?php require __DIR__ . '/footer.php'; ?>
