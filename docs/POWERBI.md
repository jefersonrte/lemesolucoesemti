# Power BI

## Relatorio web

`/powerbi/` usa o SSO central da Leme Informatica. Quando nao ha sessao, o
usuario segue para `lemeinformatica.com.br`, autentica com a mesma senha do
sistema principal e retorna ao relatorio por um codigo de uso unico.

O navegador consulta somente `powerbi/dados.php`. Esse proxy valida a sessao
integrada e acessa a API principal no servidor, sem expor a chave ao cliente.

## Power Query

O arquivo `powerbi/consulta-powerbi.m` usa o parametro de texto `ApiKeyLeme`.
Crie esse parametro no Power BI Desktop e forneca a chave por um canal seguro.
A chave nao deve ser escrita no arquivo `.m`, na documentacao ou no GitHub.
