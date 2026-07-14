<?php
require_once __DIR__ . '/auth/security.php';

apply_security_headers();
$user = require_login(false);
$csrf = csrf_token();
$canWrite = can_create_or_update($user);
$canDelete = can_delete($user);
$canManageUsers = $user['perfil'] === ROLE_ADMIN;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title>Dashboard API - Leme Solucoes em TI</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <script>
        window.APP_USER = {
            id: <?= (int) $user['id'] ?>,
            nome: <?= json_encode($user['nome'], JSON_UNESCAPED_UNICODE) ?>,
            email: <?= json_encode($user['email'], JSON_UNESCAPED_UNICODE) ?>,
            perfil: <?= json_encode($user['perfil'], JSON_UNESCAPED_UNICODE) ?>,
            canWrite: <?= $canWrite ? 'true' : 'false' ?>,
            canDelete: <?= $canDelete ? 'true' : 'false' ?>,
            canManageUsers: <?= $canManageUsers ? 'true' : 'false' ?>
        };
    </script>

    <div id="wrapper" class="wrapper">
        <header id="top">
            <div>
                <p class="eyebrow">API JSON + CRUD + Power BI</p>
                <h1>Dashboard do banco principal</h1>
                <p>Origem dos dados: lemeinformatica.com.br/estacio/final/api</p>
            </div>
            <div class="header-actions">
                <div class="user-badge">
                    <strong><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars($user['perfil'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <button id="btnAtualizar" class="btn secondary" type="button">Atualizar dados</button>
                <form method="post" action="logout.php" class="logout-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn ghost-light" type="submit">Sair</button>
                </form>
            </div>
        </header>

        <div id="inner-wrapper" class="inner-wrapper">
            <aside id="menubar" aria-label="Menu principal">
                <ul id="menulist">
                    <li class="menuitem active" data-target="secDashboard">Dashboard</li>
                    <?php if ($canWrite): ?>
                        <li class="menuitem" data-target="secCadastro">Cadastro</li>
                    <?php endif; ?>
                    <li class="menuitem" data-target="secDados">Dados da API</li>
                    <?php if ($canManageUsers): ?>
                        <li class="menuitem" data-target="secUsuarios">Usuarios</li>
                    <?php endif; ?>
                    <li class="menuitem" data-target="secPowerBI">Power BI</li>
                </ul>

                <div class="api-box">
                    <strong>Status</strong>
                    <span id="apiStatus">Aguardando leitura</span>
                </div>

                <div class="api-box">
                    <strong>Permissao</strong>
                    <span><?= htmlspecialchars($user['perfil'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </aside>

            <main id="main">
                <section id="secDashboard" class="page-section">
                    <div class="section-title">
                        <div>
                            <h2>Visao geral</h2>
                            <p>Resumo retornado em JSON pela API principal.</p>
                        </div>
                    </div>

                    <div class="cards">
                        <article class="card">
                            <span>Total de animais</span>
                            <strong id="totalAnimais">0</strong>
                        </article>
                        <article class="card">
                            <span>Portes diferentes</span>
                            <strong id="totalPortes">0</strong>
                        </article>
                        <article class="card">
                            <span>Racas diferentes</span>
                            <strong id="totalRacas">0</strong>
                        </article>
                    </div>

                    <div class="grid-two">
                        <article class="panel">
                            <h3>Animais por porte</h3>
                            <div id="graficoPorte" class="bar-list"></div>
                        </article>
                        <article class="panel">
                            <h3>Animais por raca</h3>
                            <div id="graficoRaca" class="bar-list"></div>
                        </article>
                    </div>
                </section>

                <?php if ($canWrite): ?>
                <section id="secCadastro" class="page-section panel">
                    <div class="panel-header">
                        <div>
                            <h2>Cadastro via API</h2>
                            <p>Inclui e altera registros no banco principal por meio da API JSON.</p>
                        </div>
                        <p id="formStatus"></p>
                    </div>

                    <form id="formAnimal" class="form-grid">
                        <input type="hidden" id="animalId">
                        <label>
                            Nome
                            <input id="nome" name="nome" type="text" required maxlength="100" placeholder="Ex.: Rex">
                        </label>
                        <label>
                            Raca
                            <input id="raca" name="raca" type="text" required maxlength="100" placeholder="Ex.: Vira-lata">
                        </label>
                        <label>
                            Porte
                            <select id="porte" name="porte" required>
                                <option value="">Selecione</option>
                                <option>Pequeno</option>
                                <option>Medio</option>
                                <option>Grande</option>
                            </select>
                        </label>
                        <div class="form-actions">
                            <button class="btn" type="submit" id="btnSalvar">Cadastrar</button>
                            <button class="btn ghost" type="button" id="btnCancelar">Cancelar edicao</button>
                        </div>
                    </form>
                </section>
                <?php else: ?>
                    <p id="formStatus" class="muted">Seu perfil permite apenas visualizacao.</p>
                    <input type="hidden" id="animalId">
                    <form id="formAnimal" style="display:none"></form>
                    <button id="btnSalvar" style="display:none" type="button"></button>
                    <button id="btnCancelar" style="display:none" type="button"></button>
                    <input id="nome" type="hidden"><input id="raca" type="hidden"><input id="porte" type="hidden">
                <?php endif; ?>

                <section id="secDados" class="page-section panel">
                    <div class="panel-header">
                        <div>
                            <h2>Dados recebidos da API</h2>
                            <p>Leitura, edicao e exclusao usando GET, PUT e DELETE conforme seu perfil.</p>
                        </div>
                        <input id="busca" type="search" placeholder="Buscar por nome, raca ou porte">
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Raca</th>
                                    <th>Porte</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaAnimais"></tbody>
                        </table>
                    </div>
                </section>

                <?php if ($canManageUsers): ?>
                <section id="secUsuarios" class="page-section panel">
                    <div class="panel-header">
                        <div>
                            <h2>Usuarios</h2>
                            <p>Crie acessos e ajuste perfis do dashboard.</p>
                        </div>
                        <p id="usuarioStatus"></p>
                    </div>

                    <form id="formUsuario" class="form-grid user-form-grid">
                        <input type="hidden" id="usuarioId">
                        <label>
                            Nome
                            <input id="usuarioNome" name="nome" type="text" required maxlength="100" placeholder="Ex.: Maria Silva">
                        </label>
                        <label>
                            E-mail
                            <input id="usuarioEmail" name="email" type="email" required maxlength="150" autocomplete="off" placeholder="usuario@seudominio.com.br">
                        </label>
                        <label>
                            Perfil
                            <select id="usuarioPerfil" name="perfil" required>
                                <option value="admin">Admin</option>
                                <option value="operador">Operador</option>
                                <option value="visualizador">Visualizador</option>
                            </select>
                        </label>
                        <label>
                            Senha
                            <input id="usuarioSenha" name="senha" type="password" minlength="8" autocomplete="new-password" placeholder="Minimo 8 caracteres">
                        </label>
                        <label class="check-row">
                            <input id="usuarioAtivo" name="ativo" type="checkbox" checked>
                            Ativo
                        </label>
                        <div class="form-actions">
                            <button class="btn" type="submit" id="btnSalvarUsuario">Criar usuario</button>
                            <button class="btn ghost" type="button" id="btnCancelarUsuario">Cancelar edicao</button>
                        </div>
                    </form>

                    <div class="table-wrap users-table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>E-mail</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                    <th>Acoes</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaUsuarios"></tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

                <section id="secPowerBI" class="page-section panel">
                    <h2>Power BI</h2>
                    <p>Use o arquivo <strong>powerbi/consulta-powerbi.m</strong> para importar os dados diretamente da API principal.</p>
                    <div class="endpoint-box">
                        <span>Endpoint:</span>
                        <code>https://lemeinformatica.com.br/estacio/final/api/powerbi.php</code>
                    </div>
                    <p class="muted">A chave da API ja esta configurada no arquivo da consulta. Caso altere a chave no servidor, atualize tambem esse arquivo.</p>
                </section>
            </main>
        </div>

        <footer id="bottom">
            Leme Solucoes em TI - Dashboard protegido por login, sessoes, CSRF, perfis e API key.
        </footer>
    </div>

    <script src="app.js"></script>
</body>
</html>
