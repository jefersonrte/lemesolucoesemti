const tabelaAnimais = document.getElementById('tabelaAnimais');
const totalAnimais = document.getElementById('totalAnimais');
const totalPortes = document.getElementById('totalPortes');
const totalRacas = document.getElementById('totalRacas');
const graficoPorte = document.getElementById('graficoPorte');
const graficoRaca = document.getElementById('graficoRaca');
const formAnimal = document.getElementById('formAnimal');
const formStatus = document.getElementById('formStatus');
const busca = document.getElementById('busca');
const animalId = document.getElementById('animalId');
const btnSalvar = document.getElementById('btnSalvar');
const btnCancelar = document.getElementById('btnCancelar');
const btnAtualizar = document.getElementById('btnAtualizar');
const apiStatus = document.getElementById('apiStatus');
const tabelaUsuarios = document.getElementById('tabelaUsuarios');
const formUsuario = document.getElementById('formUsuario');
const usuarioStatus = document.getElementById('usuarioStatus');
const usuarioId = document.getElementById('usuarioId');
const usuarioNome = document.getElementById('usuarioNome');
const usuarioEmail = document.getElementById('usuarioEmail');
const usuarioPerfil = document.getElementById('usuarioPerfil');
const usuarioSenha = document.getElementById('usuarioSenha');
const usuarioAtivo = document.getElementById('usuarioAtivo');
const btnSalvarUsuario = document.getElementById('btnSalvarUsuario');
const btnCancelarUsuario = document.getElementById('btnCancelarUsuario');
const secUsuarios = document.getElementById('secUsuarios');
const menuItems = document.querySelectorAll('.menuitem[data-target]');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
const appUser = window.APP_USER || { id: null, canWrite: false, canDelete: false, canManageUsers: false, perfil: 'visualizador' };

let animais = [];
let usuarios = [];

async function api(path, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers || {})
    };

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        headers['X-CSRF-Token'] = csrfToken;
    }

    const response = await fetch(path, {
        credentials: 'same-origin',
        ...options,
        method,
        headers
    });

    if (response.status === 401) {
        window.location.href = 'login.php?expirou=1';
        return { ok: false };
    }

    const data = await response.json().catch(() => ({ ok: false, erro: 'Resposta invalida da API.' }));

    if (!response.ok || data.ok === false) {
        throw new Error([data.erro, data.detalhe].filter(Boolean).join(' ') || 'Erro ao comunicar com a API.');
    }

    return data;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function renderBarList(container, rows) {
    if (!rows || rows.length === 0) {
        container.innerHTML = '<p>Nenhum dado encontrado.</p>';
        return;
    }

    const max = Math.max(...rows.map(row => Number(row.total)));

    container.innerHTML = rows.map(row => {
        const total = Number(row.total);
        const width = max > 0 ? Math.max(8, Math.round((total / max) * 100)) : 0;
        return `
            <div class="bar-row">
                <span title="${escapeHtml(row.label)}">${escapeHtml(row.label)}</span>
                <div class="bar-track"><div class="bar-fill" style="--w:${width}%"></div></div>
                <strong>${total}</strong>
            </div>
        `;
    }).join('');
}

function renderActions(animal) {
    const buttons = [];

    if (appUser.canWrite) {
        buttons.push(`<button class="btn ghost" type="button" data-action="editar" data-id="${animal.id}">Editar</button>`);
    }

    if (appUser.canDelete) {
        buttons.push(`<button class="btn danger" type="button" data-action="excluir" data-id="${animal.id}">Excluir</button>`);
    }

    if (buttons.length === 0) {
        return '<span class="muted">Somente leitura</span>';
    }

    return `<div class="actions">${buttons.join('')}</div>`;
}

function usuarioEstaAtivo(usuario) {
    return usuario.ativo === true || Number(usuario.ativo) === 1;
}

function renderUsuarioActions(usuario) {
    const isCurrentUser = Number(usuario.id) === Number(appUser.id);
    const ativo = usuarioEstaAtivo(usuario);
    const buttons = [
        `<button class="btn ghost" type="button" data-user-action="editar" data-id="${usuario.id}">Editar</button>`
    ];

    if (!isCurrentUser) {
        buttons.push(`
            <button class="btn ${ativo ? 'danger' : 'ghost'}" type="button" data-user-action="${ativo ? 'desativar' : 'ativar'}" data-id="${usuario.id}">
                ${ativo ? 'Desativar' : 'Ativar'}
            </button>
        `);
    } else {
        buttons.push('<span class="muted">Usuario atual</span>');
    }

    return `<div class="actions">${buttons.join('')}</div>`;
}

function renderUsuarios() {
    if (!tabelaUsuarios) {
        return;
    }

    if (usuarios.length === 0) {
        tabelaUsuarios.innerHTML = '<tr><td colspan="6">Nenhum usuario encontrado.</td></tr>';
        return;
    }

    tabelaUsuarios.innerHTML = usuarios.map(usuario => {
        const ativo = usuarioEstaAtivo(usuario);
        return `
            <tr>
                <td>${Number(usuario.id)}</td>
                <td>${escapeHtml(usuario.nome)}</td>
                <td>${escapeHtml(usuario.email)}</td>
                <td>${escapeHtml(usuario.perfil)}</td>
                <td><span class="status-badge ${ativo ? 'active' : 'inactive'}">${ativo ? 'Ativo' : 'Inativo'}</span></td>
                <td>${renderUsuarioActions(usuario)}</td>
            </tr>
        `;
    }).join('');
}

function renderTabela() {
    const termo = busca.value.trim().toLowerCase();
    const filtrados = animais.filter(animal => {
        const texto = `${animal.nome} ${animal.raca} ${animal.porte}`.toLowerCase();
        return texto.includes(termo);
    });

    if (filtrados.length === 0) {
        tabelaAnimais.innerHTML = '<tr><td colspan="5">Nenhum registro encontrado.</td></tr>';
        return;
    }

    tabelaAnimais.innerHTML = filtrados.map(animal => `
        <tr>
            <td>${Number(animal.id)}</td>
            <td>${escapeHtml(animal.nome)}</td>
            <td>${escapeHtml(animal.raca)}</td>
            <td>${escapeHtml(animal.porte)}</td>
            <td>${renderActions(animal)}</td>
        </tr>
    `).join('');
}

async function carregarDashboard() {
    const response = await api('api/dashboard.php');
    const dados = response.data;

    totalAnimais.textContent = dados.total_animais ?? 0;
    totalPortes.textContent = dados.por_porte?.length ?? 0;
    totalRacas.textContent = dados.por_raca?.length ?? 0;

    renderBarList(graficoPorte, dados.por_porte || []);
    renderBarList(graficoRaca, dados.por_raca || []);
}

async function carregarAnimais() {
    const response = await api('api/animais.php?limit=5000');
    animais = response.data || [];
    renderTabela();
}

async function carregarUsuarios() {
    if (!appUser.canManageUsers || !tabelaUsuarios) {
        return;
    }

    const response = await api('api/usuarios.php');
    usuarios = response.data || [];
    renderUsuarios();
}

async function atualizarTela() {
    formStatus.textContent = 'Carregando dados...';
    formStatus.className = '';

    try {
        const loaders = [carregarDashboard(), carregarAnimais()];

        if (appUser.canManageUsers) {
            loaders.push(carregarUsuarios());
        }

        await Promise.all(loaders);
        formStatus.textContent = appUser.canWrite ? 'Dados atualizados.' : 'Dados atualizados. Seu perfil permite apenas visualizacao.';
        apiStatus.textContent = 'CRUD conectado a API principal';
        apiStatus.className = 'message-ok';
    } catch (error) {
        formStatus.textContent = error.message;
        formStatus.className = 'message-error';
        apiStatus.textContent = 'Erro ao ler API';
        apiStatus.className = 'message-error';
    }
}

function limparFormulario() {
    if (!formAnimal || !appUser.canWrite) {
        return;
    }

    animalId.value = '';
    formAnimal.reset();
    btnSalvar.textContent = 'Cadastrar';
    btnCancelar.style.display = 'none';
}

function limparFormularioUsuario() {
    if (!formUsuario || !appUser.canManageUsers) {
        return;
    }

    usuarioId.value = '';
    formUsuario.reset();
    usuarioPerfil.value = 'operador';
    usuarioAtivo.checked = true;
    usuarioSenha.required = true;
    usuarioSenha.placeholder = 'Minimo 8 caracteres';
    btnSalvarUsuario.textContent = 'Criar usuario';
    btnCancelarUsuario.style.display = 'none';
}

function editarUsuario(usuario) {
    if (!appUser.canManageUsers) {
        alert('Somente administrador pode gerenciar usuarios.');
        return;
    }

    usuarioId.value = usuario.id;
    usuarioNome.value = usuario.nome || '';
    usuarioEmail.value = usuario.email || '';
    usuarioPerfil.value = usuario.perfil || 'visualizador';
    usuarioAtivo.checked = usuarioEstaAtivo(usuario);
    usuarioSenha.value = '';
    usuarioSenha.required = false;
    usuarioSenha.placeholder = 'Deixe em branco para manter';
    btnSalvarUsuario.textContent = 'Salvar usuario';
    btnCancelarUsuario.style.display = 'inline-block';
    secUsuarios?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function usuarioPayloadBase(usuario) {
    return {
        nome: usuario.nome || '',
        email: usuario.email || '',
        perfil: usuario.perfil || 'visualizador',
        ativo: usuarioEstaAtivo(usuario)
    };
}

async function alterarStatusUsuario(id, ativo) {
    const usuario = usuarios.find(item => Number(item.id) === Number(id));

    if (!usuario) {
        return;
    }

    if (Number(usuario.id) === Number(appUser.id)) {
        alert('Voce nao pode alterar o status do seu proprio usuario.');
        return;
    }

    if (!confirm(`Deseja ${ativo ? 'ativar' : 'desativar'} este usuario?`)) {
        return;
    }

    usuarioStatus.textContent = 'Salvando usuario...';
    usuarioStatus.className = '';

    try {
        const response = await api(`api/usuarios.php?id=${encodeURIComponent(id)}`, {
            method: 'PUT',
            body: JSON.stringify({
                ...usuarioPayloadBase(usuario),
                ativo
            })
        });
        await carregarUsuarios();
        usuarioStatus.textContent = response.mensagem || (ativo ? 'Usuario ativado.' : 'Usuario desativado.');
        usuarioStatus.className = 'message-ok';
    } catch (error) {
        usuarioStatus.textContent = error.message;
        usuarioStatus.className = 'message-error';
    }
}

function editarAnimal(animal) {
    if (!appUser.canWrite) {
        alert('Seu perfil nao permite editar registros.');
        return;
    }

    animalId.value = animal.id;
    document.getElementById('nome').value = animal.nome;
    document.getElementById('raca').value = animal.raca;
    document.getElementById('porte').value = animal.porte;
    btnSalvar.textContent = 'Salvar alteracao';
    btnCancelar.style.display = 'inline-block';
    window.scrollTo({ top: formAnimal.offsetTop - 120, behavior: 'smooth' });
}

async function excluirAnimal(id) {
    if (!appUser.canDelete) {
        alert('Somente administrador pode excluir registros.');
        return;
    }

    if (!confirm('Deseja excluir este registro?')) {
        return;
    }

    try {
        await api(`api/animais.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        await atualizarTela();
    } catch (error) {
        alert(error.message);
    }
}

if (formAnimal && appUser.canWrite) {
    formAnimal.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = {
            nome: document.getElementById('nome').value.trim(),
            raca: document.getElementById('raca').value.trim(),
            porte: document.getElementById('porte').value.trim()
        };

        if (!payload.nome || !payload.raca || !payload.porte) {
            formStatus.textContent = 'Preencha todos os campos.';
            formStatus.className = 'message-error';
            return;
        }

        const id = animalId.value;
        const method = id ? 'PUT' : 'POST';
        const url = id ? `api/animais.php?id=${encodeURIComponent(id)}` : 'api/animais.php';

        formStatus.textContent = 'Salvando...';
        formStatus.className = '';

        try {
            const response = await api(url, {
                method,
                body: JSON.stringify(payload)
            });

            limparFormulario();
            await atualizarTela();
            formStatus.textContent = id ? 'Registro atualizado.' : 'Registro cadastrado.';
        } catch (error) {
            formStatus.textContent = error.message;
            formStatus.className = 'message-error';
            apiStatus.textContent = 'Erro ao executar CRUD';
            apiStatus.className = 'message-error';
        }
    });
}

if (formUsuario && appUser.canManageUsers) {
    limparFormularioUsuario();

    formUsuario.addEventListener('submit', async (event) => {
        event.preventDefault();

        const id = usuarioId.value;
        const senha = usuarioSenha.value.trim();
        const payload = {
            nome: usuarioNome.value.trim(),
            email: usuarioEmail.value.trim(),
            perfil: usuarioPerfil.value,
            ativo: usuarioAtivo.checked
        };

        if (senha !== '') {
            payload.senha = senha;
        }

        if (!id && senha === '') {
            usuarioStatus.textContent = 'Informe a senha do novo usuario.';
            usuarioStatus.className = 'message-error';
            return;
        }

        const method = id ? 'PUT' : 'POST';
        const url = id ? `api/usuarios.php?id=${encodeURIComponent(id)}` : 'api/usuarios.php';

        usuarioStatus.textContent = 'Salvando usuario...';
        usuarioStatus.className = '';

        try {
            await api(url, {
                method,
                body: JSON.stringify(payload)
            });

            limparFormularioUsuario();
            await carregarUsuarios();
            usuarioStatus.textContent = response.mensagem || (id ? 'Usuario atualizado.' : 'Usuario criado.');
            usuarioStatus.className = 'message-ok';
        } catch (error) {
            usuarioStatus.textContent = error.message;
            usuarioStatus.className = 'message-error';
        }
    });
}

menuItems.forEach(item => {
    item.addEventListener('click', () => {
        menuItems.forEach(menu => menu.classList.remove('active'));
        item.classList.add('active');

        const target = document.getElementById(item.dataset.target);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

tabelaAnimais.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) {
        return;
    }

    const id = Number(button.dataset.id);
    const animal = animais.find(item => Number(item.id) === id);

    if (button.dataset.action === 'editar' && animal) {
        editarAnimal(animal);
    }

    if (button.dataset.action === 'excluir') {
        excluirAnimal(id);
    }
});

if (tabelaUsuarios && appUser.canManageUsers) {
    tabelaUsuarios.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-user-action]');
        if (!button) {
            return;
        }

        const id = Number(button.dataset.id);
        const usuario = usuarios.find(item => Number(item.id) === id);

        if (button.dataset.userAction === 'editar' && usuario) {
            editarUsuario(usuario);
        }

        if (button.dataset.userAction === 'ativar') {
            alterarStatusUsuario(id, true);
        }

        if (button.dataset.userAction === 'desativar') {
            alterarStatusUsuario(id, false);
        }
    });
}

busca.addEventListener('input', renderTabela);
btnAtualizar.addEventListener('click', atualizarTela);

if (btnCancelar) {
    btnCancelar.addEventListener('click', limparFormulario);
}

if (btnCancelarUsuario) {
    btnCancelarUsuario.addEventListener('click', limparFormularioUsuario);
}

document.addEventListener('DOMContentLoaded', atualizarTela);
