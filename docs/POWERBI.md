# Power BI

## Relatorio web

`/powerbi/` exige login e consome os proxies protegidos de `api/`. Quando uma
sessao expira, o usuario retorna para `/login.php?next=powerbi` e volta ao
relatorio depois de autenticar.

## Power Query

O arquivo `powerbi/consulta-powerbi.m` usa o parametro de texto `ApiKeyLeme`.
Crie esse parametro no Power BI Desktop e forneca a chave por um canal seguro.
A chave nao deve ser escrita no arquivo `.m`, na documentacao ou no GitHub.
