# Leme Solucoes em TI

Dashboards, integracoes e projetos de inteligencia de dados da Leme Solucoes em
TI.

## Projetos publicados

- central de projetos: `https://lemesolucoesemti.com.br/`;
- dashboard Pet: `https://lemesolucoesemti.com.br/pet/`;
- relatorios Power BI: `https://lemesolucoesemti.com.br/powerbi/`;
- Brasil em Dados: `https://lemesolucoesemti.com.br/gov/`;
- investimentos: `https://lemesolucoesemti.com.br/invest/`;
- entrada da Nuvem/Nextcloud: `https://lemesolucoesemti.com.br/cloud/`.

O menu tambem fornece acesso aos projetos operacionais hospedados em
`lemeinformatica.com.br`.

## Central de projetos

O catalogo da pagina inicial fica no array `$projects` de `index.php`. Estilos,
comportamento e imagem ficam separados em `frontend`. Consulte
[`docs/PROJECT_HUB.md`](docs/PROJECT_HUB.md) antes de adicionar uma nova rota.
A versao atual do catalogo fica em `PROJECT_HUB_VERSION`.

O subdominio historico `nuvem.lemesolucoesemti.com.br` esta reservado, mas a
instalacao anterior do Nextcloud precisa ser recuperada a partir da hospedagem
ou de backup. A pagina `cloud/` evita um atalho quebrado enquanto essa etapa nao
for concluida.

## Seguranca

Credenciais de hospedagem, banco, API e arquivos de configuracao de producao nao
devem ser versionados. O deploy usa apenas segredos protegidos do GitHub Actions.

## Dashboard Pet 1.1.0

O diretorio `pet/` contem a visualizacao gerencial. O backend usa a autenticacao
central da Leme Informatica por codigo unico, mantem o token somente na sessao
PHP e repassa apenas indicadores agregados ao navegador. Consulte
`docs/PET_DASHBOARD.md`.
