CREATE TABLE IF NOT EXISTS usuarios_integracao (
    usuario_central_id INT NOT NULL PRIMARY KEY,
    usuario_local_id INT NOT NULL UNIQUE,
    nextcloud_usuario_id VARCHAR(64) NOT NULL UNIQUE,
    sincronizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_integracao_local (usuario_local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A tabela associa a identidade central ao cadastro local e ao Nextcloud.
-- O endpoint api/usuarios-sync.php tambem cria esta estrutura de forma idempotente.
