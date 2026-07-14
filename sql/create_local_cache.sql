CREATE TABLE IF NOT EXISTS animais_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id INT NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    raca VARCHAR(100) NOT NULL,
    porte VARCHAR(50) NOT NULL,
    last_sync_at DATETIME NOT NULL,
    INDEX idx_source_id (source_id),
    INDEX idx_porte (porte),
    INDEX idx_raca (raca)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_sync_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    executado_em DATETIME NOT NULL,
    registros INT NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL,
    mensagem TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
