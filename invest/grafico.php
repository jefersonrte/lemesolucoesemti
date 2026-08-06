<?php
/**
 * grafico.php -- Tela "Gráfico ao vivo" (estilo day trade).
 * Candles intraday que se atualizam sozinhos. Dados Yahoo (~15 min de atraso).
 */
require_once __DIR__ . '/lib.php';

$active = 'grafico';
$titulo = 'Gráfico ao vivo';
require __DIR__ . '/header.php';
?>
  <h1>Gráfico ao vivo <span style="font-size:.8rem;color:#94a3b8;font-weight:400;">(estilo day trade)</span></h1>
  <div class="sub">
    Candles intraday ·
    <span class="aovivo"><span class="dot"></span> ao vivo · atualizado <span id="hora">agora</span></span>
  </div>

  <div class="aviso">
    ⚠️ <strong>Day trade é de altíssimo risco</strong> — a maioria das pessoas perde dinheiro nele.
    Os dados têm <strong>~15 min de atraso</strong> (não servem para operar de verdade). Isto é
    estudo/simulação, não é recomendação.
  </div>

  <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:14px;">
    <select id="ticker" class="btn" style="min-width:120px;">
      <?php foreach ($WATCHLIST as $t): ?>
        <option value="<?= $t ?>"<?= $t === 'PETR4' ? ' selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <span id="grupo-int" style="display:flex; gap:6px;">
      <button class="btn int on" data-int="1m">1 min</button>
      <button class="btn int" data-int="5m">5 min</button>
      <button class="btn int" data-int="15m">15 min</button>
    </span>
    <span id="grupo-rng" style="display:flex; gap:6px;">
      <button class="btn rng on" data-rng="1d">Hoje</button>
      <button class="btn rng" data-rng="5d">5 dias</button>
    </span>
    <button class="btn" id="atualizar">↻ Atualizar</button>
  </div>

  <div class="cards" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));">
    <div class="card"><div class="rot">Último preço</div><div class="val" id="ult">—</div></div>
    <div class="card"><div class="rot">Variação no dia</div><div class="val" id="var">—</div></div>
    <div class="card"><div class="rot">Máxima / Mínima</div><div class="val" id="maxmin" style="font-size:1.05rem;">—</div></div>
  </div>

  <div id="grafico" style="height:420px; width:100%; background:#0b1220; border:1px solid #1e293b; border-radius:12px;"></div>
  <div id="carregando" style="color:#94a3b8; font-size:.85rem; margin-top:10px;">Carregando candles…</div>

<script src="https://unpkg.com/lightweight-charts@4.1.3/dist/lightweight-charts.standalone.production.js"></script>
<script>
let chart, serie, ticker='PETR4', interval='1m', range='1d', timer=null;

function initChart(){
  const el = document.getElementById('grafico');
  chart = LightweightCharts.createChart(el, {
    width: el.clientWidth, height: 420,
    layout: { background:{color:'#0b1220'}, textColor:'#94a3b8' },
    grid: { vertLines:{color:'#16213a'}, horzLines:{color:'#16213a'} },
    timeScale: { timeVisible:true, secondsVisible:false, borderColor:'#334155' },
    rightPriceScale: { borderColor:'#334155' },
    crosshair: { mode: 0 },
  });
  serie = chart.addCandlestickSeries({
    upColor:'#22c55e', downColor:'#ef4444', borderVisible:false,
    wickUpColor:'#22c55e', wickDownColor:'#ef4444',
  });
  window.addEventListener('resize', ()=> chart.applyOptions({ width: el.clientWidth }));
}

function brl(v){ return 'R$ ' + Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }

async function carregar(primeira){
  document.getElementById('carregando').style.display = 'block';
  try{
    const r = await fetch(`intraday.php?ticker=${ticker}&interval=${interval}&range=${range}`, {cache:'no-store'});
    const j = await r.json();
    const c = j.candles || [];
    if (!c.length){ document.getElementById('carregando').textContent = 'Sem dados agora (mercado fechado?). Tente 5 dias.'; return; }
    serie.setData(c);
    if (primeira) chart.timeScale().fitContent();

    const ult = j.preco ?? c[c.length-1].close;
    const prev = j.prevClose ?? c[0].open;
    const varPct = prev ? (ult/prev - 1)*100 : 0;
    let hi=-Infinity, lo=Infinity;
    for (const x of c){ if(x.high>hi)hi=x.high; if(x.low<lo)lo=x.low; }

    document.getElementById('ult').textContent = brl(ult);
    const ve = document.getElementById('var');
    ve.textContent = (varPct>=0?'+':'') + varPct.toFixed(2).replace('.',',') + '%';
    ve.className = 'val ' + (varPct>=0?'pos':'neg');
    document.getElementById('maxmin').textContent = brl(hi) + ' / ' + brl(lo);
    if (j.hora) document.getElementById('hora').textContent = j.hora;
    document.getElementById('carregando').style.display = 'none';
  }catch(e){ document.getElementById('carregando').textContent = 'Erro ao carregar. Tente atualizar.'; }
}

function reiniciar(primeira){
  carregar(primeira);
  if (timer) clearInterval(timer);
  timer = setInterval(()=>carregar(false), 30000); // atualiza a cada 30s
}

document.getElementById('ticker').addEventListener('change', e=>{ ticker=e.target.value; reiniciar(true); });
document.querySelectorAll('.int').forEach(b=> b.addEventListener('click', ()=>{
  document.querySelectorAll('.int').forEach(x=>x.classList.remove('on')); b.classList.add('on');
  interval=b.dataset.int; reiniciar(true);
}));
document.querySelectorAll('.rng').forEach(b=> b.addEventListener('click', ()=>{
  document.querySelectorAll('.rng').forEach(x=>x.classList.remove('on')); b.classList.add('on');
  range=b.dataset.rng; reiniciar(true);
}));
document.getElementById('atualizar').addEventListener('click', ()=>carregar(false));

initChart();
reiniciar(true);
</script>
<style>
  .btn.on { background:#1d4ed8; border-color:#1d4ed8; color:#fff; }
  #grupo-int, #grupo-rng { border-left:1px solid #334155; padding-left:10px; }
</style>
<?php require __DIR__ . '/footer.php'; ?>
