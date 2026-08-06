<?php
/**
 * painel.php -- A carteira simulada: saldo, acoes, patrimonio e historico.
 * Os precos das acoes na carteira se atualizam sozinhos (ao vivo).
 */
require_once __DIR__ . '/lib.php';

$carteira = carregar_carteira();

// Preco atual de cada papel que temos, para mostrar lucro/prejuizo em aberto
$precos = [];
foreach (array_keys($carteira['posicoes']) as $t) {
    $p = preco_atual($t);
    if ($p !== null) $precos[$t] = $p;
}

$patr = patrimonio($carteira, $precos);
$lucroTotal = $patr - SALDO_INICIAL;
$pctTotal = SALDO_INICIAL > 0 ? ($lucroTotal / SALDO_INICIAL) * 100 : 0;

$active = 'painel';
$titulo = 'Painel';
require __DIR__ . '/header.php';
?>
  <h1>Painel da carteira</h1>
  <div class="sub">
    Carteira simulada (paper trading) · B3 ·
    <span class="aovivo"><span class="dot"></span> preços ao vivo · <span id="hora">agora</span></span>
  </div>

  <div class="aviso">
    ⚠️ <strong>Dinheiro fictício.</strong> Ferramenta de estudo, não é recomendação de
    investimento. Estratégias automáticas erram — nunca use dinheiro real que não pode perder.
  </div>

  <div class="cards">
    <div class="card">
      <div class="rot">Dinheiro disponível</div>
      <div class="val"><?= brl($carteira['saldo']) ?></div>
    </div>
    <div class="card">
      <div class="rot">Patrimônio total</div>
      <div class="val"><?= brl($patr) ?></div>
    </div>
    <div class="card">
      <div class="rot">Resultado</div>
      <div class="val <?= $lucroTotal >= 0 ? 'pos' : 'neg' ?>">
        <?= ($lucroTotal >= 0 ? '+' : '') . brl($lucroTotal) ?>
        <span style="font-size:1rem;">(<?= number_format($pctTotal, 1, ',', '.') ?>%)</span>
      </div>
    </div>
  </div>

  <h2>Ações na carteira</h2>
  <div class="overflow">
  <table>
    <tr><th>Papel</th><th class="num">Qtd</th><th class="num">Preço médio</th>
        <th class="num">Preço atual</th><th class="num">Valor</th><th class="num">Result. aberto</th></tr>
    <?php if (empty($carteira['posicoes'])): ?>
      <tr><td colspan="6" class="vazio">Nenhuma ação no momento.</td></tr>
    <?php else: foreach ($carteira['posicoes'] as $ticker => $p):
        $atual = $precos[$ticker] ?? $p['preco_medio'];
        $valor = $atual * $p['quantidade'];
        $res   = ($atual - $p['preco_medio']) * $p['quantidade'];
    ?>
      <tr>
        <td><strong><?= htmlspecialchars($ticker) ?></strong></td>
        <td class="num"><?= (int)$p['quantidade'] ?></td>
        <td class="num"><?= brl($p['preco_medio']) ?></td>
        <td class="num" data-preco="<?= htmlspecialchars($ticker) ?>"><?= brl($atual) ?></td>
        <td class="num"><?= brl($valor) ?></td>
        <td class="num <?= $res >= 0 ? 'pos' : 'neg' ?>"><?= ($res>=0?'+':'') . brl($res) ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </table>
  </div>

  <h2>Histórico de operações</h2>
  <div class="overflow">
  <table>
    <tr><th>Data</th><th>Tipo</th><th>Papel</th><th class="num">Preço</th>
        <th class="num">Qtd</th><th class="num">Resultado</th></tr>
    <?php if (empty($carteira['historico'])): ?>
      <tr><td colspan="6" class="vazio">Nenhuma operação ainda. Rode o robô para começar.</td></tr>
    <?php else: foreach (array_reverse($carteira['historico']) as $h): ?>
      <tr>
        <td><?= htmlspecialchars($h['data']) ?></td>
        <td><span class="tag <?= $h['tipo'] ?>"><?= htmlspecialchars($h['tipo']) ?></span></td>
        <td><?= htmlspecialchars($h['ticker']) ?></td>
        <td class="num"><?= brl($h['preco']) ?></td>
        <td class="num"><?= (int)$h['quantidade'] ?></td>
        <td class="num <?= ($h['lucro'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
          <?= $h['lucro'] === null ? '—' : (($h['lucro']>=0?'+':'') . brl($h['lucro'])) ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
  </table>
  </div>

<script>
const TICKERS = <?= json_encode(array_keys($carteira['posicoes'])) ?>;
function brl(v){ return 'R$ ' + v.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
async function atualizar(){
  if (!TICKERS.length) return;
  try {
    const r = await fetch('cotacoes.php?tickers=' + TICKERS.join(','), {cache:'no-store'});
    const j = await r.json();
    for (const [t, p] of Object.entries(j.precos || {})) {
      const cel = document.querySelector('[data-preco="'+t+'"]');
      if (cel) { cel.textContent = brl(p); cel.style.color = '#7dd3fc'; setTimeout(()=>cel.style.color='', 400); }
    }
    if (j.hora) document.getElementById('hora').textContent = j.hora;
  } catch(e) {}
}
atualizar();
setInterval(atualizar, 45000);
</script>
<?php require __DIR__ . '/footer.php'; ?>
