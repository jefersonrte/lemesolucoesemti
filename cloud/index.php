<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'none'; base-uri 'self'; form-action 'none'; frame-ancestors 'none'");
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07110d">
    <meta name="description" content="Acesso a Nuvem Nextcloud da Leme Solucoes em TI">
    <title>Nuvem Nextcloud | Leme Solucoes em TI</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="/" aria-label="Voltar a central de projetos">
            <span>LS</span>
            <strong>Leme Solucoes em TI</strong>
        </a>
        <a class="back-link" href="/">Todos os projetos</a>
    </header>

    <main>
        <section class="status" aria-labelledby="cloud-title">
            <p class="eyebrow">Arquivos e colaboracao</p>
            <h1 id="cloud-title">Nuvem Nextcloud</h1>
            <p class="lead">O endereco da nuvem esta preservado, mas a instalacao anterior nao esta disponivel no diretorio publicado neste momento.</p>

            <div class="state-line" role="status">
                <span aria-hidden="true"></span>
                Ambiente em recuperacao
            </div>

            <dl>
                <div><dt>Plataforma</dt><dd>Nextcloud</dd></div>
                <div><dt>Endereco reservado</dt><dd>nuvem.lemesolucoesemti.com.br</dd></div>
                <div><dt>Estado</dt><dd>Revisao de instalacao e dados</dd></div>
            </dl>

            <div class="actions">
                <a class="primary" href="https://nuvem.lemesolucoesemti.com.br/">Verificar Nextcloud</a>
                <a href="/">Voltar a central</a>
            </div>
        </section>
    </main>
</body>
</html>
