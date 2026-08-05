(() => {
    'use strict';

    const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const shortDay = new Intl.DateTimeFormat('pt-BR', { weekday: 'short' });
    const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    const label = (value) => String(value || '-').replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

    function setText(id, value) {
        const target = document.getElementById(id);
        if (target) target.textContent = value;
    }

    function renderBars(id, records, nameKey, valueKey) {
        const target = document.getElementById(id);
        if (!target) return;
        if (!records.length) {
            target.innerHTML = '<p class="empty">Nenhum registro no periodo.</p>';
            return;
        }
        const max = Math.max(...records.map((item) => Number(item[valueKey]) || 0), 1);
        target.innerHTML = records.map((item) => {
            const value = Number(item[valueKey]) || 0;
            return `<div class="bar-row"><span title="${escapeHtml(label(item[nameKey]))}">${escapeHtml(label(item[nameKey]))}</span><div class="bar-track"><i style="width:${Math.max(3, Math.round(value / max * 100))}%"></i></div><strong>${escapeHtml(value)}</strong></div>`;
        }).join('');
    }

    function renderSales(records) {
        const target = document.getElementById('salesChart');
        const values = records.map((item) => Number(item.total) || 0);
        const max = Math.max(...values, 1);
        const total = values.reduce((sum, value) => sum + value, 0);
        setText('salesWeekTotal', currency.format(total));
        target.innerHTML = records.map((item) => {
            const value = Number(item.total) || 0;
            const day = new Date(`${item.dia}T12:00:00`);
            return `<div class="chart-column"><strong>${escapeHtml(currency.format(value))}</strong><i style="height:${Math.max(3, Math.round(value / max * 100))}%"></i><span>${escapeHtml(shortDay.format(day).replace('.', ''))}</span></div>`;
        }).join('');
    }

    async function loadDashboard() {
        const button = document.getElementById('refreshButton');
        button.disabled = true;
        const error = document.getElementById('errorBanner');
        error.hidden = true;
        try {
            const response = await fetch('api/dashboard.php', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const payload = await response.json().catch(() => ({ ok: false, erro: 'Resposta invalida do servidor.' }));
            if (response.status === 401) {
                window.location.href = 'https://lemeinformatica.com.br/pet/sso-start.php';
                return;
            }
            if (!response.ok || payload.ok === false) throw new Error(payload.erro || 'Falha na integracao com a API Pet.');

            const data = payload.data || {};
            const totals = data.totais || {};
            setText('totalOwners', totals.tutores ?? 0);
            setText('totalAnimals', totals.animais ?? 0);
            setText('totalAppointments', totals.atendimentos_hoje ?? 0);
            setText('totalAdmissions', totals.internacoes_ativas ?? 0);
            setText('totalGrooming', totals.estetica_hoje ?? 0);
            setText('totalGroomingWeek', totals.estetica_proximos_7_dias ?? 0);
            setText('totalProducts', totals.produtos_ativos ?? 0);
            setText('totalLowStock', totals.estoque_baixo ?? 0);
            setText('salesToday', currency.format(Number(totals.vendas_hoje) || 0));
            setText('salesMonth', currency.format(Number(totals.vendas_mes) || 0));
            renderSales(data.vendas_7_dias || []);
            renderBars('groomingChart', data.estetica_por_status || [], 'status', 'quantidade');
            renderBars('categoryChart', data.produtos_por_categoria || [], 'categoria', 'produtos');
            renderBars('speciesChart', data.animais_por_especie || [], 'especie', 'quantidade');
            const generated = payload.gerado_em ? new Date(payload.gerado_em) : new Date();
            setText('updatedAt', `Atualizado ${generated.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}`);
        } catch (caught) {
            error.hidden = false;
            error.querySelector('span').textContent = caught.message;
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('refreshButton')?.addEventListener('click', loadDashboard);
    loadDashboard();
})();
