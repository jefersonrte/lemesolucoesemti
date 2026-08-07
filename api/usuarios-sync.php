<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth/conexao-local.php';
require_once __DIR__ . '/../auth/nextcloud-client.php';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function sync_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sync_require_api_key(): void
{
    $received = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    $configured = defined('API_KEY') ? trim((string) API_KEY) : '';

    if ($configured === '' || $received === '' || !hash_equals($configured, $received)) {
        sync_response(['ok' => false, 'erro' => 'Nao autorizado.'], 401);
    }
}

function sync_request_data(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);

    if (!is_array($data)) {
        sync_response(['ok' => false, 'erro' => 'JSON invalido.'], 400);
    }

    return $data;
}

function sync_ensure_schema(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS usuarios_integracao (
            usuario_central_id INT NOT NULL PRIMARY KEY,
            usuario_local_id INT NOT NULL UNIQUE,
            nextcloud_usuario_id VARCHAR(64) NOT NULL UNIQUE,
            sincronizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_integracao_local (usuario_local_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function sync_find_local_user(mysqli $conn, int $centralId, string $email, string $previousEmail): ?array
{
    $stmt = $conn->prepare(
        'SELECT u.id, u.nome, u.email, u.perfil, u.ativo
         FROM usuarios_integracao i
         INNER JOIN usuarios_admin u ON u.id = i.usuario_local_id
         WHERE i.usuario_central_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $centralId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        return $user;
    }

    $candidateEmails = array_values(array_unique(array_filter([$email, $previousEmail])));
    foreach ($candidateEmails as $candidateEmail) {
        $stmt = $conn->prepare('SELECT id, nome, email, perfil, ativo FROM usuarios_admin WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $candidateEmail);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user) {
            return $user;
        }
    }

    return null;
}

function sync_validate_user(array $data): array
{
    $centralId = filter_var($data['usuario_central_id'] ?? null, FILTER_VALIDATE_INT);
    $action = trim((string) ($data['acao'] ?? ''));
    $name = trim((string) ($data['nome'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $previousEmail = strtolower(trim((string) ($data['email_anterior'] ?? '')));
    $role = trim((string) ($data['perfil'] ?? 'visualizador'));
    $password = (string) ($data['senha'] ?? '');
    $active = filter_var($data['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if (!$centralId || !in_array($action, ['upsert', 'disable'], true)) {
        sync_response(['ok' => false, 'erro' => 'Identificador ou acao de sincronizacao invalida.'], 422);
    }

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sync_response(['ok' => false, 'erro' => 'Nome e e-mail validos sao obrigatorios.'], 422);
    }

    if (!in_array($role, ['admin', 'operador', 'visualizador'], true)) {
        sync_response(['ok' => false, 'erro' => 'Perfil de usuario invalido.'], 422);
    }

    if ($password !== '' && strlen($password) < 8) {
        sync_response(['ok' => false, 'erro' => 'A senha deve ter pelo menos 8 caracteres.'], 422);
    }

    return [
        'central_id' => (int) $centralId,
        'acao' => $action,
        'nome' => $name,
        'email' => $email,
        'email_anterior' => $previousEmail,
        'perfil' => $role,
        'senha' => $password,
        'ativo' => $action === 'disable' ? 0 : ($active === false ? 0 : 1),
    ];
}

function sync_audit(mysqli $conn, int $localUserId, string $email): void
{
    try {
        $action = 'usuario_sincronizado';
        $details = 'Sincronizacao central concluida: ' . $email;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $stmt = $conn->prepare(
            'INSERT INTO auth_audit_log (usuario_id, acao, detalhes, ip, user_agent) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $localUserId, $action, $details, $ip, $userAgent);
        $stmt->execute();
    } catch (Throwable $e) {
        // Auditoria nao deve invalidar uma sincronizacao concluida.
    }
}

sync_require_api_key();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sync_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
}

$user = sync_validate_user(sync_request_data());
$conn = local_db();
$nextcloudUserId = 'leme-' . $user['central_id'];
$nextcloudCreated = false;

try {
    sync_ensure_schema($conn);
    $conn->begin_transaction();
    $localUser = sync_find_local_user(
        $conn,
        $user['central_id'],
        $user['email'],
        $user['email_anterior']
    );

    if ($localUser === null && $user['acao'] === 'disable') {
        nextcloud_disable_user_if_exists($nextcloudUserId);
        $conn->commit();
        sync_response([
            'ok' => true,
            'mensagem' => 'Usuario ja estava ausente nos sistemas integrados.',
            'sistemas' => ['dashboard' => true, 'nextcloud' => true],
        ]);
    }

    if ($localUser === null) {
        if ($user['senha'] === '') {
            throw new RuntimeException('Informe uma nova senha para realizar a primeira sincronizacao deste usuario.');
        }

        $hash = password_hash($user['senha'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            'INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, ativo) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssi', $user['nome'], $user['email'], $hash, $user['perfil'], $user['ativo']);
        $stmt->execute();
        $localUserId = (int) $conn->insert_id;
    } else {
        $localUserId = (int) $localUser['id'];
        if ($user['senha'] !== '') {
            $hash = password_hash($user['senha'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare(
                'UPDATE usuarios_admin SET nome = ?, email = ?, senha_hash = ?, perfil = ?, ativo = ? WHERE id = ?'
            );
            $stmt->bind_param(
                'ssssii',
                $user['nome'],
                $user['email'],
                $hash,
                $user['perfil'],
                $user['ativo'],
                $localUserId
            );
        } else {
            $stmt = $conn->prepare(
                'UPDATE usuarios_admin SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?'
            );
            $stmt->bind_param('sssii', $user['nome'], $user['email'], $user['perfil'], $user['ativo'], $localUserId);
        }
        $stmt->execute();
    }

    $stmt = $conn->prepare(
        'SELECT usuario_central_id FROM usuarios_integracao WHERE usuario_local_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $localUserId);
    $stmt->execute();
    $linkedCentralId = $stmt->get_result()->fetch_assoc()['usuario_central_id'] ?? null;
    if ($linkedCentralId !== null && (int) $linkedCentralId !== $user['central_id']) {
        throw new RuntimeException('A conta local ja esta vinculada a outro usuario central.');
    }

    $stmt = $conn->prepare(
        'INSERT INTO usuarios_integracao (usuario_central_id, usuario_local_id, nextcloud_usuario_id)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE usuario_local_id = VALUES(usuario_local_id),
                                 nextcloud_usuario_id = VALUES(nextcloud_usuario_id)'
    );
    $stmt->bind_param('iis', $user['central_id'], $localUserId, $nextcloudUserId);
    $stmt->execute();

    if ($user['acao'] === 'disable') {
        nextcloud_disable_user_if_exists($nextcloudUserId);
    } else {
        $nextcloudCreated = nextcloud_upsert_user(
            $nextcloudUserId,
            $user['senha'],
            $user['nome'],
            $user['email'],
            $user['ativo'] === 1
        );
    }

    $conn->commit();
    sync_audit($conn, $localUserId, $user['email']);

    sync_response([
        'ok' => true,
        'mensagem' => 'Usuario sincronizado com sucesso.',
        'sistemas' => ['dashboard' => true, 'nextcloud' => true],
    ]);
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    if ($nextcloudCreated) {
        try {
            nextcloud_delete_user_if_exists($nextcloudUserId);
        } catch (Throwable $cleanupError) {
        }
    }

    $isConflict = ($e instanceof mysqli_sql_exception && (int) $e->getCode() === 1062)
        || str_contains($e->getMessage(), 'vinculada a outro usuario central');
    $status = $isConflict ? 409 : 502;
    $message = match (true) {
        $isConflict => 'Ja existe uma conta local vinculada a outro usuario central.',
        $e instanceof mysqli_sql_exception => 'O banco local recusou a sincronizacao do usuario.',
        default => $e->getMessage(),
    };
    sync_response(['ok' => false, 'erro' => $message], $status);
}
