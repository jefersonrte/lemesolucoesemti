# Central de projetos

Versao atual: `1.2.1`
Publicacao: 06/08/2026

## Objetivo

A pagina inicial de `lemesolucoesemti.com.br` funciona como um menu publico para
os projetos dos dois dominios Leme. Sistemas protegidos continuam exigindo login
em suas proprias rotas.

## Estrutura

- `index.php`: registro dos projetos e HTML semantico.
- `frontend/css/home.css`: layout responsivo e estados visuais.
- `frontend/js/home.js`: menu movel, tecla Escape e ano do rodape.
- `frontend/assets/matrix-city-v2.webp`: imagem de fundo otimizada.

## Adicionar um projeto

Inclua um item no array `$projects` de `index.php` com os campos:

```php
[
    'name' => 'Nome do projeto',
    'description' => 'Descricao curta e objetiva.',
    'url' => 'https://dominio.example/projeto/',
    'category' => 'Categoria',
    'accent' => 'cyan',
    'local' => true,
]
```

Os valores de `accent` disponiveis sao `mint`, `cyan`, `yellow`, `coral`,
`violet` e `blue`. Use `local` para identificar projetos hospedados no dominio
da pagina atual.

## Rotas catalogadas

| Projeto | Destino | Estado esperado |
|---|---|---|
| Clinica Pet | `https://lemeinformatica.com.br/pet/` | login central |
| Dashboard Pet | `https://lemesolucoesemti.com.br/pet/` | SSO central |
| Relatorios Power BI | `https://lemesolucoesemti.com.br/powerbi/` | SSO central |
| Dados Publicos SC | `https://lemeinformatica.com.br/gov/` | publico |
| Brasil em Dados | `https://lemesolucoesemti.com.br/gov/` | publico |
| Investimentos | `https://lemesolucoesemti.com.br/invest/` | publico |
| Nuvem / Nextcloud | `https://lemesolucoesemti.com.br/cloud/` | redireciona para `/nextcloud/` |
| Administracao e API | `https://lemeinformatica.com.br/login.php` | login central |

## Versoes

- `1.2.1`: pre-autoriza o subdominio historico no Nextcloud e aplica idioma,
  localidade e regiao telefonica do Brasil.
- `1.2.0`: instala o Nextcloud 34.0.2 fora das pastas controladas pelo Git,
  preserva os dados fora de `public_html` e ativa a entrada `/cloud/`.
- `1.1.2`: acompanha a compatibilidade de montagem do modulo Pet no dominio
  principal, sem alterar as URLs do catalogo.
- `1.1.1`: troca o login local do Power BI pelo mesmo SSO central usado no
  Dashboard Pet.
- `1.1.0`: inclui Power BI e Nextcloud, restaura `gov` e `invest`, corrige o
  retorno do login e amplia a verificacao do deploy.
- `1.0.0`: primeira central publica de projetos.

## Seguranca

A pagina nao contem credenciais. Os cabecalhos restringem recursos a arquivos do
proprio dominio, bloqueiam incorporacao por terceiros e desabilitam camera,
microfone e geolocalizacao.
