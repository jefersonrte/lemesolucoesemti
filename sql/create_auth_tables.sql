CREATE TABLE IF NOT EXISTS usuarios_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin', 'operador', 'visualizador') NOT NULL DEFAULT 'visualizador',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    ultimo_login_em TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identificador VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent VARCHAR(500) NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identificador_criado (identificador, criado_em),
    INDEX idx_email_criado (email, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(80) NOT NULL,
    detalhes TEXT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_criado (usuario_id, criado_em),
    INDEX idx_acao_criado (acao, criado_em),
    CONSTRAINT fk_auth_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crie o primeiro administrador por um procedimento privado de instalacao.
-- Nunca grave senha ou hash padrao neste arquivo versionado.
