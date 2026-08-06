# Nuvem Nextcloud

## Enderecos

- entrada catalogada: `https://lemesolucoesemti.com.br/cloud/`;
- subdominio reservado: `https://nuvem.lemesolucoesemti.com.br/`.

## Recuperacao segura

Antes de reinstalar o Nextcloud, identifique no hPanel:

1. o diretorio raiz do subdominio;
2. o banco usado pela instalacao anterior;
3. o diretorio de dados fora da raiz publica;
4. a versao do Nextcloud registrada no backup;
5. um backup completo dos arquivos, banco e configuracao.

Somente depois dessa verificacao a aplicacao deve ser restaurada. Uma instalacao
nova sobre os caminhos antigos pode tornar arquivos existentes inacessiveis.
