# Nuvem Nextcloud

## Instalacao atual

- entrada publica: `https://lemesolucoesemti.com.br/cloud/`;
- aplicacao isolada: `https://lemesolucoesemti.com.br/nextcloud/`;
- versao inicial: Nextcloud `34.0.2`;
- banco: MariaDB compartilhado com prefixo exclusivo `oc_`;
- dados: diretorio `nextcloud-data` fora de `public_html`;
- codigo do Nextcloud: fora do Git e gerenciado pelo atualizador oficial.

O gateway `cloud/index.php` e versionado, mas o nucleo, a configuracao privada e
os arquivos dos usuarios nunca devem ser adicionados ao repositorio.

## Endereco historico

`https://nuvem.lemesolucoesemti.com.br/` ainda possui DNS, mas sua raiz de
documentos nao esta disponivel pela conta FTP atual. O host ja esta cadastrado
em `trusted_domains`; depois de apontar o subdominio para a instalacao atual no
hPanel, basta validar o acesso antes de torna-lo o endereco principal.
