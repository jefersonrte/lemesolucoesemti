# Leme Solucoes em TI

Dashboards, integracoes e projetos de inteligencia de dados da Leme Solucoes em
TI.

## Projetos publicados

- central de projetos: `https://lemesolucoesemti.com.br/`;
- dashboard Pet: `https://lemesolucoesemti.com.br/pet/`;
- Brasil em Dados: `https://lemesolucoesemti.com.br/gov/`;
- investimentos: `https://lemesolucoesemti.com.br/invest/`.

O menu tambem fornece acesso aos projetos operacionais hospedados em
`lemeinformatica.com.br`.

## Central de projetos

O catalogo da pagina inicial fica no array `$projects` de `index.php`. Estilos,
comportamento e imagem ficam separados em `frontend`. Consulte
[`docs/PROJECT_HUB.md`](docs/PROJECT_HUB.md) antes de adicionar uma nova rota.

## Seguranca

Credenciais de hospedagem, banco, API e arquivos de configuracao de producao nao
devem ser versionados. O deploy usa apenas segredos protegidos do GitHub Actions.

## Dashboard Pet 1.1.0

O diretorio `pet/` contem a visualizacao gerencial. O backend local autentica o
usuario, chama o relatorio agregado do dominio principal com a API key guardada
em `config.php` e repassa somente indicadores ao navegador. Consulte
`docs/PET_DASHBOARD.md`.
