<?php
require_once __DIR__ . '/../auth/security.php';

apply_security_headers();
if (!is_logged_in()) {
    redirect('/login.php?next=powerbi');
}
$user = require_login(false);
$embedMode = ($_GET['embed'] ?? '') === '1';
$officialUrl = defined('POWERBI_EMBED_URL') ? trim((string) POWERBI_EMBED_URL) : '';
$hasOfficialReport = preg_match('~^https://app\.powerbi\.com/(view|reportEmbed)~i', $officialUrl) === 1;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatorio Power BI - Leme Solucoes em TI</title>
    <link rel="stylesheet" href="../frontend/css/powerbi.css?v=20260714-frontend">
</head>
<body class="<?= $embedMode ? 'embed-mode' : '' ?>">
    <header class="bi-topbar">
        <div>
            <p class="bi-eyebrow">Leme Solucoes em TI</p>
            <h1>Relatorio operacional</h1>
        </div>
        <div class="bi-top-actions">
            <span class="bi-user"><?= htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8') ?></span>
            <a class="bi-button secondary" href="/">Voltar aos projetos</a>
        </div>
    </header>

    <main class="bi-report">
        <div class="bi-toolbar">
            <div>
                <strong id="reportStatus">Carregando dados</strong>
                <span id="reportUpdated">Conectando a API principal...</span>
            </div>
            <button id="refreshReport" class="bi-button" type="button">Atualizar</button>
        </div>

        <nav class="bi-tabs" aria-label="Paginas do relatorio">
            <button class="bi-tab active" type="button" data-view="overview" aria-selected="true">Visao geral</button>
            <button class="bi-tab" type="button" data-view="animals" aria-selected="false">Animais</button>
            <button class="bi-tab" type="button" data-view="foods" aria-selected="false">Alimentos</button>
            <?php if ($hasOfficialReport): ?>
                <button class="bi-tab" type="button" data-view="official" aria-selected="false">Power BI Microsoft</button>
            <?php endif; ?>
        </nav>

        <section class="bi-view" data-view-panel="overview">
            <div class="bi-kpis">
                <article class="bi-kpi accent-blue">
                    <span>Total de animais</span>
                    <strong id="pbiTotalAnimais">0</strong>
                </article>
                <article class="bi-kpi accent-teal">
                    <span>Total de alimentos</span>
                    <strong id="pbiTotalAlimentos">0</strong>
                </article>
                <article class="bi-kpi accent-green">
                    <span>Racas distintas</span>
                    <strong id="pbiTotalRacas">0</strong>
                </article>
                <article class="bi-kpi accent-amber">
                    <span>Categorias</span>
                    <strong id="pbiTotalCategorias">0</strong>
                </article>
                <article class="bi-kpi accent-coral">
                    <span>Preco medio</span>
                    <strong id="pbiPrecoMedio">R$ 0,00</strong>
                </article>
            </div>

            <div class="bi-grid">
                <article class="bi-panel">
                    <div class="bi-panel-heading">
                        <h2>Animais por porte</h2>
                        <span>Quantidade</span>
                    </div>
                    <div id="pbiChartPorte" class="bi-chart"></div>
                </article>
                <article class="bi-panel">
                    <div class="bi-panel-heading">
                        <h2>Alimentos por categoria</h2>
                        <span>Quantidade</span>
                    </div>
                    <div id="pbiChartCategoria" class="bi-chart"></div>
                </article>
                <article class="bi-panel wide">
                    <div class="bi-panel-heading">
                        <h2>Principais racas</h2>
                        <span>10 maiores grupos</span>
                    </div>
                    <div id="pbiChartRaca" class="bi-chart compact"></div>
                </article>
                <article class="bi-panel">
                    <div class="bi-panel-heading">
                        <h2>Preco medio por categoria</h2>
                        <span>Reais</span>
                    </div>
                    <div id="pbiChartPreco" class="bi-chart"></div>
                </article>
            </div>
        </section>

        <section class="bi-view" data-view-panel="animals" hidden>
            <div class="bi-view-heading">
                <div>
                    <h2>Base de animais</h2>
                    <p><span id="animalResultCount">0</span> registros encontrados</p>
                </div>
                <div class="bi-filters">
                    <input id="animalSearch" type="search" placeholder="Buscar nome ou raca" aria-label="Buscar animais">
                    <select id="animalSize" aria-label="Filtrar por porte">
                        <option value="">Todos os portes</option>
                    </select>
                </div>
            </div>
            <div class="bi-table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Nome</th><th>Raca</th><th>Porte</th></tr></thead>
                    <tbody id="pbiAnimalRows"></tbody>
                </table>
            </div>
            <div class="bi-pagination">
                <button id="animalPrev" class="bi-button secondary" type="button">Anterior</button>
                <span id="animalPage">Pagina 1</span>
                <button id="animalNext" class="bi-button secondary" type="button">Proxima</button>
            </div>
        </section>

        <section class="bi-view" data-view-panel="foods" hidden>
            <div class="bi-view-heading">
                <div>
                    <h2>Base de alimentos</h2>
                    <p><span id="foodResultCount">0</span> registros encontrados</p>
                </div>
                <div class="bi-filters">
                    <input id="foodSearch" type="search" placeholder="Buscar alimento" aria-label="Buscar alimentos">
                    <select id="foodCategory" aria-label="Filtrar por categoria">
                        <option value="">Todas as categorias</option>
                    </select>
                </div>
            </div>
            <div class="bi-table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Unidade</th><th>Preco</th></tr></thead>
                    <tbody id="pbiFoodRows"></tbody>
                </table>
            </div>
            <div class="bi-pagination">
                <button id="foodPrev" class="bi-button secondary" type="button">Anterior</button>
                <span id="foodPage">Pagina 1</span>
                <button id="foodNext" class="bi-button secondary" type="button">Proxima</button>
            </div>
        </section>

        <?php if ($hasOfficialReport): ?>
            <section class="bi-view" data-view-panel="official" hidden>
                <iframe
                    class="official-powerbi-frame"
                    src="<?= htmlspecialchars($officialUrl, ENT_QUOTES, 'UTF-8') ?>"
                    title="Relatorio oficial do Power BI"
                    allowfullscreen
                ></iframe>
            </section>
        <?php endif; ?>
    </main>

    <script src="../frontend/js/powerbi.js?v=20260714-frontend"></script>
</body>
</html>
