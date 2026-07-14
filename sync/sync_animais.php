<?php
// Sincronizacao opcional para gravar uma copia local dos dados em u216029204_api.
// Por seguranca, exige login de administrador e metodo POST com CSRF.

require_once __DIR__ . '/../auth/security.php';
require_once __DIR__ . '/../api-client.php';

apply_security_headers();
$user = require_role([ROLE_ADMIN], true);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_error('Use metodo POST para executar a sincronizacao.', 405);
}

require_csrf_for_state_change();

function log_sync(mysqli $conn, int $registros, string $status, string $mensagem = ''): void
{
    $stmt = $conn->prepare('INSERT INTO api_sync_log (executado_em, registros, status, mensagem) VALUES (NOW(), ?, ?, ?)');
    $stmt->bind_param('iss', $registros, $status, $mensagem);
    $stmt->execute();
}

try {
    $api = main_api_request('animais.php?limit=5000', 'GET');
    $payload = json_decode($api['body'], true);

    if (($api['status'] ?? 500) >= 400 || !is_array($payload) || empty($payload['ok'])) {
        throw new RuntimeException($payload['erro'] ?? 'API principal retornou erro.');
    }

    $conn = local_db();
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'REPLACE INTO animais_cache (source_id, nome, raca, porte, last_sync_at) VALUES (?, ?, ?, ?, NOW())'
    );

    $total = 0;
    foreach ($payload['data'] as $animal) {
        $id = (int) $animal['id'];
        $nome = (string) $animal['nome'];
        $raca = (string) $animal['raca'];
        $porte = (string) $animal['porte'];
        $stmt->bind_param('isss', $id, $nome, $raca, $porte);
        $stmt->execute();
        $total++;
    }

    log_sync($conn, $total, 'sucesso', 'Sincronizacao concluida.');
    audit_log($user['id'], 'sync_animais', 'Registros sincronizados: ' . $total);
    $conn->commit();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'registros' => $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    try {
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->rollback();
            log_sync($conn, 0, 'erro', $e->getMessage());
        }
    } catch (Throwable $ignored) {
    }

    json_error('Erro na sincronizacao local.', 500, ['detalhe' => APP_ENV === 'production' ? null : $e->getMessage()]);
}
