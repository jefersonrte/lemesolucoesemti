<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth/security.php';

apply_security_headers();
start_secure_session();
$user = current_user();
if ($user === null) {
    header('Location: ../login.php?next=pet');
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Dashboard Pet | Leme Solucoes em TI</title>
    <link rel="stylesheet" href="frontend/css/app.css?v=1.1.0">
    <script src="frontend/js/app.js?v=1.1.0" defer></script>
</head>
<body>
    <header class="app-header">
        <a class="brand" href="../"><span class="brand-mark">LS</span><span><strong>Leme Solucoes em TI</strong><small>Inteligencia Pet</small></span></a>
        <div class="header-actions">
            <span class="user-name"><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></span>
            <a class="icon-link" href="../" title="Central de projetos" aria-label="Central de projetos">&#8962;</a>
            <form method="post" action="../logout.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <button class="logout-button" type="submit">Sair</button>
            </form>
        </div>
    </header>

    <main class="dashboard-shell">
        <div class="page-heading">
            <div><p class="eyebrow">Visao consolidada</p><h1>Dashboard Pet</h1><p>Operacao clinica, estetica e comercial em um unico painel.</p></div>
            <div class="heading-meta"><span id="updatedAt">Atualizando...</span><button id="refreshButton" type="button">Atualizar</button></div>
        </div>

        <section class="metrics" aria-label="Indicadores principais">
            <article><span>Tutores ativos</span><strong id="totalOwners">0</strong><small>cadastros</small></article>
            <article><span>Animais ativos</span><strong id="totalAnimals">0</strong><small>pacientes</small></article>
            <article><span>Atendimentos hoje</span><strong id="totalAppointments">0</strong><small>agenda clinica</small></article>
            <article class="urgent"><span>Internacoes</span><strong id="totalAdmissions">0</strong><small>ativas agora</small></article>
            <article><span>Banho e tosa hoje</span><strong id="totalGrooming">0</strong><small>agendamentos</small></article>
            <article><span>Proximos 7 dias</span><strong id="totalGroomingWeek">0</strong><small>estetica pet</small></article>
            <article><span>Produtos ativos</span><strong id="totalProducts">0</strong><small>catalogo</small></article>
            <article class="warning"><span>Estoque baixo</span><strong id="totalLowStock">0</strong><small>itens para repor</small></article>
            <article class="money"><span>Vendas hoje</span><strong id="salesToday">R$ 0,00</strong><small>faturamento</small></article>
            <article class="money"><span>Vendas no mes</span><strong id="salesMonth">R$ 0,00</strong><small>faturamento</small></article>
        </section>

        <div class="analytics-grid">
            <section class="analytics-section sales-panel">
                <div class="section-heading"><div><h2>Vendas nos ultimos 7 dias</h2><p>Faturamento concluido por dia</p></div><strong id="salesWeekTotal">R$ 0,00</strong></div>
                <div class="column-chart" id="salesChart"><p class="empty">Carregando vendas...</p></div>
            </section>

            <section class="analytics-section">
                <div class="section-heading"><div><h2>Banho e tosa</h2><p>Agendamentos no mes por status</p></div></div>
                <div class="bar-list" id="groomingChart"><p class="empty">Carregando agenda...</p></div>
            </section>

            <section class="analytics-section">
                <div class="section-heading"><div><h2>Catalogo comercial</h2><p>Produtos ativos por categoria</p></div></div>
                <div class="bar-list" id="categoryChart"><p class="empty">Carregando produtos...</p></div>
            </section>

            <section class="analytics-section">
                <div class="section-heading"><div><h2>Pacientes</h2><p>Animais ativos por especie</p></div></div>
                <div class="bar-list" id="speciesChart"><p class="empty">Carregando pacientes...</p></div>
            </section>
        </div>

        <div class="error-banner" id="errorBanner" hidden><strong>Nao foi possivel atualizar o painel.</strong><span></span></div>
    </main>
</body>
</html>
