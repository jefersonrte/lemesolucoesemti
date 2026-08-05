# Dashboard Pet

Versao inicial: 1.1.0, 2026-08-05.

## Fluxo

```text
Navegador autenticado
  -> /pet/api/dashboard.php
  -> token SSO na sessao PHP do servidor
  -> https://lemeinformatica.com.br/pet/api/relatorios.php
  -> banco principal
```

O navegador recebe totais de tutores, animais, atendimentos, internacoes,
estetica, produtos, estoque e vendas, alem de series agregadas para os graficos.
Nenhum nome, contato, fotografia ou prontuario cruza os dominios.

## Autenticacao

O dashboard reutiliza diretamente a autenticacao central da Leme Informatica.
Quando a sessao nao existe, `/pet/` redireciona ao dominio principal, que emite
um codigo unico. O backend troca esse codigo por um token sem expor senha ou
chave permanente no segundo dominio.

## Arquivos

- `pet/index.php`: pagina protegida;
- `pet/callback.php`: troca segura do codigo de acesso;
- `pet/api/dashboard.php`: proxy autenticado;
- `pet/includes/session.php`: sessao local sem banco;
- `pet/includes/client.php`: cliente HTTPS do relatorio;
- `pet/frontend/css/app.css`: layout responsivo;
- `pet/frontend/js/app.js`: indicadores e graficos;

## Publicacao

O workflow `Deploy Project Hub` publica por FTPS quando os secrets existem. Sem
FTPS, ele aguarda o deploy Git da Hostinger e valida o CSS versionado em
producao antes de concluir.
