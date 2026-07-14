<?php
require_once __DIR__ . '/../auth/security.php';

apply_security_headers();
$loggedUser = require_role([ROLE_ADMIN], true);
require_csrf_for_state_change();

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    switch ($method) {
        case 'GET':
            listar_usuarios();
            break;
        case 'POST':
            criar_usuario($loggedUser);
            break;
        case 'PUT':
            atualizar_usuario($loggedUser);
            break;
        case 'DELETE':
            desativar_usuario($loggedUser);
            break;
        default:
            json_error('Metodo nao permitido.', 405);
    }
} catch (mysqli_sql_exception $e) {
    if ((int) $e->getCode() === 1062) {
        json_error('Ja existe usuario cadastrado com este e-mail.', 409);
    }

    if ((int) $e->getCode() === 1146) {
        json_error('Tabela usuarios_admin nao encontrada. Execute sql/create_auth_tables.sql no banco local.', 500);
    }

    json_error('Erro ao salvar usuario.', 500, ['detalhe' => $e->getMessage()]);
} catch (Throwable $e) {
    json_error('Erro interno ao gerenciar usuarios.', 500, ['detalhe' => $e->getMessage()]);
}

function json_ok(array $payload = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_data(): array
{
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return $_POST ?: [];
    }

    $data = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        json_error('JSON invalido.', 400, ['detalhe' => json_last_error_msg()]);
    }

    return $data;
}

function clean_text(mixed $value): string
{
    return trim((string) $value);
}

function clean_email(mixed $value): string
{
    return strtolower(trim((string) $value));
}

function bool_value(mixed $value, bool $default = true): int
{
    if ($value === null) {
        return $default ? 1 : 0;
    }

    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $bool === null ? ($default ? 1 : 0) : ($bool ? 1 : 0);
}

function allowed_roles(): array
{
    return [ROLE_ADMIN, ROLE_OPERADOR, ROLE_VISUALIZADOR];
}

function validar_usuario(array $data, bool $creating): array
{
    $nome = clean_text($data['nome'] ?? '');
    $email = clean_email($data['email'] ?? '');
    $perfil = clean_text($data['perfil'] ?? ROLE_VISUALIZADOR);
    $senha = (string) ($data['senha'] ?? '');
    $ativo = bool_value($data['ativo'] ?? true);
    $erros = [];

    if ($nome === '') {
        $erros['nome'] = 'Nome e obrigatorio.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'E-mail invalido.';
    }

    if (!in_array($perfil, allowed_roles(), true)) {
        $erros['perfil'] = 'Perfil invalido.';
    }

    if ($creating && $senha === '') {
        $erros['senha'] = 'Senha e obrigatoria.';
    }

    if ($senha !== '' && strlen($senha) < 8) {
        $erros['senha'] = 'A senha deve ter pelo menos 8 caracteres.';
    }

    if ($erros) {
        json_error('Dados invalidos.', 422, ['campos' => $erros]);
    }

    return [
        'nome' => $nome,
        'email' => $email,
        'perfil' => $perfil,
        'senha' => $senha,
        'ativo' => $ativo,
    ];
}

function usuario_id_from_request(array $data): int
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        json_error('Informe o id do usuario.', 422);
    }

    return (int) $id;
}

function buscar_usuario(int $id): array
{
    $conn = local_db();
    $stmt = $conn->prepare('SELECT id, nome, email, perfil, ativo FROM usuarios_admin WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();

    if (!$usuario) {
        json_error('Usuario nao encontrado.', 404);
    }

    return $usuario;
}

function active_admins_except(int $userId): int
{
    $conn = local_db();
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM usuarios_admin WHERE ativo = 1 AND perfil = ? AND id <> ?');
    $roleAdmin = ROLE_ADMIN;
    $stmt->bind_param('si', $roleAdmin, $userId);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

function ensure_active_admin_remains(int $targetId, string $newPerfil, int $newAtivo): void
{
    if ($newPerfil === ROLE_ADMIN && $newAtivo === 1) {
        return;
    }

    if (active_admins_except($targetId) < 1) {
        json_error('Mantenha pelo menos um administrador ativo.', 422);
    }
}

function listar_usuarios(): void
{
    $conn = local_db();
    $result = $conn->query(
        'SELECT id, nome, email, perfil, ativo, criado_em, atualizado_em, ultimo_login_em
         FROM usuarios_admin
         ORDER BY ativo DESC, nome ASC, id ASC'
    );

    json_ok(['data' => $result->fetch_all(MYSQLI_ASSOC)]);
}

function criar_usuario(array $loggedUser): void
{
    $data = validar_usuario(request_data(), true);
    $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
    $conn = local_db();

    $stmt = $conn->prepare('INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, ativo) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssi', $data['nome'], $data['email'], $hash, $data['perfil'], $data['ativo']);
    $stmt->execute();

    audit_log($loggedUser['id'], 'usuario_criado', 'Usuario criado: ' . $data['email']);

    json_ok([
        'mensagem' => 'Usuario criado com sucesso.',
        'data' => [
            'id' => $conn->insert_id,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'perfil' => $data['perfil'],
            'ativo' => $data['ativo'],
        ],
    ], 201);
}

function atualizar_usuario(array $loggedUser): void
{
    $input = request_data();
    $id = usuario_id_from_request($input);
    $current = buscar_usuario($id);
    $data = validar_usuario($input, false);

    if ($id === (int) $loggedUser['id'] && ($data['perfil'] !== ROLE_ADMIN || $data['ativo'] !== 1)) {
        json_error('Voce nao pode remover seu proprio acesso de administrador.', 422);
    }

    ensure_active_admin_remains($id, $data['perfil'], $data['ativo']);

    $conn = local_db();

    if ($data['senha'] !== '') {
        $hash = password_hash($data['senha'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE usuarios_admin SET nome = ?, email = ?, perfil = ?, ativo = ?, senha_hash = ? WHERE id = ?');
        $stmt->bind_param('sssisi', $data['nome'], $data['email'], $data['perfil'], $data['ativo'], $hash, $id);
    } else {
        $stmt = $conn->prepare('UPDATE usuarios_admin SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?');
        $stmt->bind_param('sssii', $data['nome'], $data['email'], $data['perfil'], $data['ativo'], $id);
    }

    $stmt->execute();
    audit_log($loggedUser['id'], 'usuario_atualizado', 'Usuario atualizado: ' . $current['email'] . ' -> ' . $data['email']);

    json_ok([
        'mensagem' => 'Usuario atualizado com sucesso.',
        'data' => [
            'id' => $id,
            'nome' => $data['nome'],
            'email' => $data['email'],
            'perfil' => $data['perfil'],
            'ativo' => $data['ativo'],
        ],
    ]);
}

function desativar_usuario(array $loggedUser): void
{
    $input = request_data();
    $id = usuario_id_from_request($input);
    $current = buscar_usuario($id);

    if ($id === (int) $loggedUser['id']) {
        json_error('Voce nao pode desativar seu proprio usuario.', 422);
    }

    ensure_active_admin_remains($id, (string) $current['perfil'], 0);

    $conn = local_db();
    $stmt = $conn->prepare('UPDATE usuarios_admin SET ativo = 0 WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    audit_log($loggedUser['id'], 'usuario_desativado', 'Usuario desativado: ' . $current['email']);

    json_ok([
        'mensagem' => 'Usuario desativado com sucesso.',
        'data' => ['id' => $id],
    ]);
}
