# Integracao de usuarios do dashboard

Versao: `1.3.0`

## Responsabilidades deste dominio

O endpoint publico para a tela administrativa continua sendo
`api/usuarios.php`, mas ele encaminha as operacoes para a API central publicada
pela Hostinger em `https://lemeinformatica.com.br/pet/usuarios.php`. A chamada
de retorno autenticada chega em `api/usuarios-sync.php`, que mantem a conta local
e a conta do Nextcloud sincronizadas.

## Componentes

- `api/usuarios.php`: proxy administrativo protegido por sessao, perfil admin e
  CSRF.
- `api/usuarios-sync.php`: endpoint interno protegido por `X-API-KEY`.
- `auth/nextcloud-client.php`: cliente da OCS Provisioning API.
- `usuarios_integracao`: associa ID central, ID local e usuario Nextcloud.
- `auth/nextcloud-runtime.php`: configuracao privada ignorada pelo Git e
  bloqueada pelo servidor web.

O identificador imutavel no Nextcloud usa o formato `leme-{id-central}`. O
e-mail e o nome podem ser alterados sem criar outra conta. A senha informada no
cadastro e aplicada aos dois bancos de autenticacao e ao Nextcloud.

## Resposta de sucesso

```json
{
  "ok": true,
  "mensagem": "Usuario sincronizado com sucesso.",
  "sistemas": {
    "dashboard": true,
    "nextcloud": true
  }
}
```

Credenciais e senhas de aplicativo permanecem exclusivamente na configuracao
privada da hospedagem.
