# Login seguro do dashboard

## O que foi implementado

- `login.php`: tela de login.
- `login-processa.php`: valida e-mail, senha, CSRF, tentativas e cria sessao.
- `logout.php`: encerra sessao com POST + CSRF.
- `index.php`: dashboard protegido por login.
- `auth/security.php`: sessoes seguras, CSRF, permissoes, headers e auditoria.
- `auth/login-service.php`: autenticacao, limite de tentativas e registro de acesso.
- `api/animais.php`: proxy CRUD protegido por sessao, perfil e CSRF.
- `api/dashboard.php`: leitura protegida por sessao.
- `api/usuarios.php`: CRUD de usuarios protegido para perfil admin.
- `sync/sync_animais.php`: sincronizacao local protegida para admin.
- `sql/create_auth_tables.sql`: tabelas de usuarios, tentativas e logs.

## Perfis

| Perfil | Dashboard | Cadastrar | Editar | Excluir | Usuarios |
|---|---:|---:|---:|---:|---:|
| admin | Sim | Sim | Sim | Sim | Sim |
| operador | Sim | Sim | Sim | Nao | Nao |
| visualizador | Sim | Nao | Nao | Nao | Nao |

## Primeiro acesso

Depois de rodar o SQL `sql/create_auth_tables.sql`, o sistema cria este usuario inicial:

- E-mail: `admin@lemesolucoesemti.com.br`
- Senha: `Admin@2026!`
- Perfil: `admin`

Troque essa senha imediatamente depois da instalacao.

## Instalar no banco local

No phpMyAdmin do banco `u216029204_api`, execute:

```sql
sql/create_auth_tables.sql
```

Se tambem quiser cache local da API, execute:

```sql
sql/create_local_cache.sql
```

## Configurar banco local

Copie `config.example.php` para `config.php` somente no servidor e edite as credenciais. O `config.php` real e ignorado pelo Git:

```php
const LOCAL_DB_HOST = 'localhost';
const LOCAL_DB_NAME = 'u216029204_api';
const LOCAL_DB_USER = 'u216029204_api';
const LOCAL_DB_PASS = 'SENHA_DO_BANCO_LOCAL_AQUI';
```

## Criar outro usuario

Entre no dashboard com um usuario `admin` e abra o menu `Usuarios`.

A tela permite:

- criar usuario;
- alterar nome, e-mail, perfil e status;
- trocar senha quando o campo de senha for preenchido;
- ativar ou desativar usuarios sem apagar o historico.

O sistema impede que todos os administradores sejam desativados.

## Criar outro usuario manualmente

Use `password_hash()` para gerar a senha:

```php
<?php echo password_hash('NovaSenhaForte', PASSWORD_DEFAULT);
```

Depois insira no banco:

```sql
INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, ativo)
VALUES ('Operador', 'operador@seudominio.com.br', 'HASH_GERADO_AQUI', 'operador', 1);
```

## Seguranca aplicada

1. Senha com `password_hash` e validacao com `password_verify`.
2. Sessao com cookie `HttpOnly`, `Secure` quando em HTTPS e `SameSite=Lax`.
3. Expiracao por inatividade em 30 minutos.
4. Regeneracao periodica do ID da sessao.
5. CSRF em login, logout, POST, PUT e DELETE.
6. Bloqueio apos 5 tentativas erradas por e-mail/IP por 15 minutos.
7. Permissao no front e no backend.
8. Logs de login, falha, logout, CRUD e sincronizacao.
9. Headers contra clickjacking, sniffing e permissive referrer.
10. API principal segue protegida por `X-API-KEY`.

## Enderecos apos publicar

- Login: `https://lemesolucoesemti.com.br/dashboard-api/login.php`
- Dashboard: `https://lemesolucoesemti.com.br/dashboard-api/index.php`
- API local protegida: `https://lemesolucoesemti.com.br/dashboard-api/api/animais.php`
- Usuarios do dashboard: `https://lemesolucoesemti.com.br/dashboard-api/api/usuarios.php`
