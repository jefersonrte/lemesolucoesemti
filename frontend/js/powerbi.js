const state = {
    summary: {},
    animals: [],
    foods: [],
    animalPage: 1,
    foodPage: 1,
    pageSize: 25
};

const byId = id => document.getElementById(id);
const currency = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const integer = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 0 });

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function getJson(path) {
    const response = await fetch(path, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    });

    if (response.status === 401) {
        window.top.location.href = 'https://lemeinformatica.com.br/pet/sso-start.php?next=powerbi';
        throw new Error('Sessao expirada.');
    }

    const data = await response.json().catch(() => ({ ok: false, erro: 'Resposta invalida da API.' }));
    if (!response.ok || data.ok === false) {
        throw new Error(data.erro || 'Nao foi possivel carregar os dados.');
    }
    return data;
}

function setText(id, value) {
    const element = byId(id);
    if (element) element.textContent = value;
}

function normalized(value) {
    return String(value ?? '').trim().toLocaleLowerCase('pt-BR');
}

function renderBars(containerId, rows, valueFormatter = integer.format, limit = 12) {
    const container = byId(containerId);
    const sorted = [...rows]
        .map(row => ({ label: row.label || 'Nao informado', total: Number(row.total) || 0 }))
        .sort((a, b) => b.total - a.total)
        .slice(0, limit);

    if (!sorted.length) {
        container.innerHTML = '<p class="bi-empty">Nenhum dado encontrado.</p>';
        return;
    }

    const max = Math.max(...sorted.map(row => row.total), 1);
    container.innerHTML = sorted.map(row => `
        <div class="bi-chart-row">
            <span class="bi-chart-label" title="${escapeHtml(row.label)}">${escapeHtml(row.label)}</span>
            <progress class="bi-progress" max="${max}" value="${row.total}">${row.total}</progress>
            <span class="bi-chart-value">${escapeHtml(valueFormatter(row.total))}</span>
        </div>
    `).join('');
}

function priceByCategory() {
    const groups = new Map();
    state.foods.forEach(food => {
        const label = food.categoria || 'Nao informado';
        const group = groups.get(label) || { sum: 0, count: 0 };
        group.sum += Number(food.preco) || 0;
        group.count += 1;
        groups.set(label, group);
    });

    return [...groups.entries()].map(([label, group]) => ({
        label,
        total: group.count ? group.sum / group.count : 0
    }));
}

function renderOverview() {
    const summary = state.summary;
    const averagePrice = state.foods.length
        ? state.foods.reduce((sum, food) => sum + (Number(food.preco) || 0), 0) / state.foods.length
        : 0;

    setText('pbiTotalAnimais', integer.format(summary.total_animais ?? state.animals.length));
    setText('pbiTotalAlimentos', integer.format(summary.total_alimentos ?? state.foods.length));
    setText('pbiTotalRacas', integer.format(summary.por_raca?.length ?? 0));
    setText('pbiTotalCategorias', integer.format(summary.por_categoria_alimento?.length ?? 0));
    setText('pbiPrecoMedio', currency.format(averagePrice));

    renderBars('pbiChartPorte', summary.por_porte || []);
    renderBars('pbiChartCategoria', summary.por_categoria_alimento || []);
    renderBars('pbiChartRaca', summary.por_raca || [], integer.format, 10);
    renderBars('pbiChartPreco', priceByCategory(), currency.format, 10);
}

function uniqueOptions(items, field) {
    return [...new Set(items.map(item => String(item[field] || '').trim()).filter(Boolean))]
        .sort((a, b) => a.localeCompare(b, 'pt-BR'));
}

function populateSelect(id, values, firstLabel) {
    const select = byId(id);
    const current = select.value;
    select.innerHTML = `<option value="">${escapeHtml(firstLabel)}</option>` + values.map(value => (
        `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`
    )).join('');
    if (values.includes(current)) select.value = current;
}

function filteredAnimals() {
    const term = normalized(byId('animalSearch').value);
    const size = normalized(byId('animalSize').value);
    return state.animals.filter(animal => {
        const matchesTerm = !term || normalized(`${animal.nome} ${animal.raca}`).includes(term);
        const matchesSize = !size || normalized(animal.porte) === size;
        return matchesTerm && matchesSize;
    });
}

function filteredFoods() {
    const term = normalized(byId('foodSearch').value);
    const category = normalized(byId('foodCategory').value);
    return state.foods.filter(food => {
        const matchesTerm = !term || normalized(`${food.nome} ${food.categoria} ${food.unidade}`).includes(term);
        const matchesCategory = !category || normalized(food.categoria) === category;
        return matchesTerm && matchesCategory;
    });
}

function paginate(rows, requestedPage) {
    const totalPages = Math.max(1, Math.ceil(rows.length / state.pageSize));
    const page = Math.min(Math.max(requestedPage, 1), totalPages);
    return {
        page,
        totalPages,
        rows: rows.slice((page - 1) * state.pageSize, page * state.pageSize)
    };
}

function renderAnimals() {
    const filtered = filteredAnimals();
    const pagination = paginate(filtered, state.animalPage);
    state.animalPage = pagination.page;

    setText('animalResultCount', integer.format(filtered.length));
    setText('animalPage', `Pagina ${pagination.page} de ${pagination.totalPages}`);
    byId('animalPrev').disabled = pagination.page <= 1;
    byId('animalNext').disabled = pagination.page >= pagination.totalPages;
    byId('pbiAnimalRows').innerHTML = pagination.rows.length ? pagination.rows.map(animal => `
        <tr>
            <td>${Number(animal.id)}</td>
            <td>${escapeHtml(animal.nome)}</td>
            <td>${escapeHtml(animal.raca)}</td>
            <td>${escapeHtml(animal.porte)}</td>
        </tr>
    `).join('') : '<tr><td colspan="4" class="bi-empty">Nenhum animal encontrado.</td></tr>';
}

function renderFoods() {
    const filtered = filteredFoods();
    const pagination = paginate(filtered, state.foodPage);
    state.foodPage = pagination.page;

    setText('foodResultCount', integer.format(filtered.length));
    setText('foodPage', `Pagina ${pagination.page} de ${pagination.totalPages}`);
    byId('foodPrev').disabled = pagination.page <= 1;
    byId('foodNext').disabled = pagination.page >= pagination.totalPages;
    byId('pbiFoodRows').innerHTML = pagination.rows.length ? pagination.rows.map(food => `
        <tr>
            <td>${Number(food.id)}</td>
            <td>${escapeHtml(food.nome)}</td>
            <td>${escapeHtml(food.categoria)}</td>
            <td>${escapeHtml(food.unidade)}</td>
            <td>${escapeHtml(currency.format(Number(food.preco) || 0))}</td>
        </tr>
    `).join('') : '<tr><td colspan="5" class="bi-empty">Nenhum alimento encontrado.</td></tr>';
}

function renderAll() {
    populateSelect('animalSize', uniqueOptions(state.animals, 'porte'), 'Todos os portes');
    populateSelect('foodCategory', uniqueOptions(state.foods, 'categoria'), 'Todas as categorias');
    renderOverview();
    renderAnimals();
    renderFoods();
}

async function loadReport() {
    const status = byId('reportStatus');
    status.textContent = 'Atualizando relatorio';
    status.className = '';
    byId('refreshReport').disabled = true;

    try {
        const [summary, animals, foods] = await Promise.all([
            getJson('dados.php?dataset=dashboard'),
            getJson('dados.php?dataset=animais&limit=5000'),
            getJson('dados.php?dataset=alimentos&limit=5000')
        ]);

        state.summary = summary.data || {};
        state.animals = animals.data || [];
        state.foods = foods.data || [];
        renderAll();

        status.textContent = 'API conectada';
        status.className = '';
        setText('reportUpdated', `Atualizado em ${new Date().toLocaleString('pt-BR')}`);
    } catch (error) {
        status.textContent = 'Erro na atualizacao';
        status.className = 'bi-error';
        setText('reportUpdated', error.message);
    } finally {
        byId('refreshReport').disabled = false;
    }
}

document.querySelectorAll('.bi-tab[data-view]').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.bi-tab[data-view]').forEach(item => {
            const active = item === tab;
            item.classList.toggle('active', active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('[data-view-panel]').forEach(panel => {
            panel.hidden = panel.dataset.viewPanel !== tab.dataset.view;
        });
    });
});

byId('animalSearch').addEventListener('input', () => { state.animalPage = 1; renderAnimals(); });
byId('animalSize').addEventListener('change', () => { state.animalPage = 1; renderAnimals(); });
byId('foodSearch').addEventListener('input', () => { state.foodPage = 1; renderFoods(); });
byId('foodCategory').addEventListener('change', () => { state.foodPage = 1; renderFoods(); });
byId('animalPrev').addEventListener('click', () => { state.animalPage -= 1; renderAnimals(); });
byId('animalNext').addEventListener('click', () => { state.animalPage += 1; renderAnimals(); });
byId('foodPrev').addEventListener('click', () => { state.foodPage -= 1; renderFoods(); });
byId('foodNext').addEventListener('click', () => { state.foodPage += 1; renderFoods(); });
byId('refreshReport').addEventListener('click', loadReport);

document.addEventListener('DOMContentLoaded', loadReport);
