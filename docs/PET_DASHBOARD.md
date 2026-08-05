# Dashboard Pet

Versao inicial: 1.1.0, 2026-08-05.

## Fluxo

```text
Navegador autenticado
  -> /pet/api/dashboard.php
  -> api-client.php (API key somente no servidor)
  -> https://lemeinformatica.com.br/pet/api/relatorios.php
  -> banco principal
```

O navegador recebe totais de tutores, animais, atendimentos, internacoes,
estetica, produtos, estoque e vendas, alem de series agregadas para os graficos.
Nenhum nome, contato, fotografia ou prontuario cruza os dominios.

## Autenticacao

O dashboard reutiliza a sessao existente da Leme Solucoes em TI. Quando a
sessao nao existe, `/pet/` redireciona para `/login.php?next=pet`, e o login
retorna ao dashboard por um destino fixo permitido.

## Arquivos

- `pet/index.php`: pagina protegida;
- `pet/api/dashboard.php`: proxy autenticado;
- `pet/frontend/css/app.css`: layout responsivo;
- `pet/frontend/js/app.js`: indicadores e graficos;
- `api-client.php`: cliente servidor a servidor.

## Publicacao

O workflow `Deploy Project Hub` publica por FTPS quando os secrets existem. Sem
FTPS, ele aguarda o deploy Git da Hostinger e valida o CSS versionado em
producao antes de concluir.
