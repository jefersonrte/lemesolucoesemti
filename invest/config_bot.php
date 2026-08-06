<?php
/**
 * config_bot.php
 * Configuracoes do Bot de Bolsa (paper trading / dinheiro ficticio).
 * Nao ha senhas aqui: a carteira e guardada num arquivo JSON e as cotacoes
 * vem do Yahoo Finance (sem token).
 */

// Papeis da B3 que o bot acompanha (SEM o ".SA" -- ele e adicionado sozinho)
$BOT_TICKERS = ['PETR4', 'VALE3', 'ITUB4'];

// Papeis que aparecem na tela "Sinais de hoje" (scanner) e nas cotacoes ao vivo
$WATCHLIST = ['PETR4', 'VALE3', 'ITUB4', 'BBAS3', 'BBDC4', 'ABEV3', 'B3SA3',
              'WEGE3', 'GGBR4', 'PRIO3', 'MGLU3', 'ITSA4', 'SUZB3', 'RADL3',
              'VIVT3', 'EQTL3'];

// Papeis usados na tela "Ano" (ranking anual de melhores/piores)
$ANO_TICKERS = ['PETR4', 'PETR3', 'VALE3', 'ITUB4', 'BBAS3', 'BBDC4', 'ABEV3',
                'B3SA3', 'WEGE3', 'ITSA4', 'RENT3', 'SUZB3', 'GGBR4', 'LREN3',
                'RADL3', 'PRIO3', 'VIVT3', 'MGLU3', 'EQTL3', 'USIM5', 'CMIG4',
                'HAPV3', 'RDOR3', 'TOTS3', 'VBBR3', 'SBSP3', 'BPAC11', 'CYRE3',
                'COGN3', 'GOAU4', 'ASAI3', 'RAIL3', 'CSNA3'];

// Estrategia: cruzamento de medias moveis (em dias)
const MEDIA_CURTA = 9;
const MEDIA_LONGA = 21;

// Regras de dinheiro (tudo FICTICIO)
const SALDO_INICIAL      = 10000.0;   // saldo inicial da carteira simulada
const VALOR_POR_COMPRA   = 2000.0;    // quanto investir por compra
const TAXA_POR_OPERACAO  = 0.0003;    // 0,03% de taxa por operacao

// Arquivo onde a carteira simulada e salva (fica protegido pelo .htaccess)
const ARQUIVO_CARTEIRA = __DIR__ . '/carteira.json';

// O acionamento web fica desabilitado ate o segredo ser configurado no servidor.
define('CRON_SECRET', trim((string) getenv('INVEST_CRON_SECRET')));
