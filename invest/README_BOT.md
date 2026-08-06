# 🤖 Bot de Bolsa (PHP) — Instalação na Hostinger

Versão web do bot, feita em **PHP puro** — roda na sua hospedagem
compartilhada, **sem Python e sem banco de dados**. A carteira simulada fica
num arquivo `carteira.json` criado automaticamente.

> ⚠️ **Dinheiro fictício.** Ferramenta de estudo, não é recomendação de
> investimento. Nunca use dinheiro real que você não pode perder.

## Arquivos

| Arquivo | Função |
|---|---|
| `painel.php` | Página que você abre no navegador para ver a carteira |
| `bot_bolsa.php` | O robô — roda pelo cron 1x/dia e opera a carteira |
| `lib.php` | Funções (cotações, estratégia, carteira) |
| `config_bot.php` | Configurações (papéis, valores, segredo do cron) |
| `.htaccess` | Protege os arquivos sensíveis |

## Passo 1 — Subir os arquivos

Envie a pasta `invest/` inteira para dentro de `public_html/` no
Gerenciador de Arquivos da Hostinger. Endereços finais:

- Painel: `https://lemesolucoesemti.com.br/invest/painel.php`
- Robô (manual): `https://lemesolucoesemti.com.br/invest/bot_bolsa.php?secret=SEU_SEGREDO`

## Passo 2 — Configurar o acionamento web opcional

Por padrao, o robo so pode ser executado pela linha de comando. Para permitir
acionamento por URL, configure a variavel de ambiente `INVEST_CRON_SECRET` com
um valor longo e aleatorio na hospedagem. O segredo nunca deve ser salvo no Git.

## Passo 3 — Agendar (cron)

No hPanel: **Avançado → Cron Jobs**. Crie uma tarefa **diária** (ex.: 18h30,
após o pregão) com o comando:

```
/usr/bin/php /home/SEU_USUARIO/domains/lemesolucoesemti.com.br/public_html/invest/bot_bolsa.php
```

(Troque `SEU_USUARIO`. A Hostinger mostra o caminho certo na tela do cron.)

Se preferir acionar por URL, use a opção de cron com `wget`:

```
wget -q -O /dev/null "https://lemesolucoesemti.com.br/invest/bot_bolsa.php?secret=SEU_SEGREDO"
```

## Como testar agora

1. Abra o **painel** no navegador — deve mostrar saldo de R$ 10.000 e carteira vazia.
2. Rode o **robô** uma vez pela URL (com o `?secret=`). Ele mostra um log do que decidiu.
3. Volte ao painel e atualize — as operações do dia aparecem.

## Mexer na estratégia

Em `config_bot.php` você pode mudar:
- `$BOT_TICKERS` — quais papéis o bot acompanha
- `MEDIA_CURTA` / `MEDIA_LONGA` — as médias móveis
- `VALOR_POR_COMPRA` — quanto investir por compra
