CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    setor ENUM('hardware', 'erp') NOT NULL DEFAULT 'hardware',
    admin TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    sessao_versao INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- sistema: papel fixo usado pelas regras (entrada, envio à ADM, chegada confirmada); esses não podem ser excluídos.
-- encerra: a OS deixa de estar em andamento no Hardware (libera abrir nova OS e o envio à ADM).
CREATE TABLE IF NOT EXISTS status_manutencao (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(60) NOT NULL UNIQUE,
    cor VARCHAR(20) NOT NULL DEFAULT 'slate',
    ordem INT NOT NULL DEFAULT 0,
    sistema ENUM('inicial', 'enviado_erp', 'recebido_erp') NULL UNIQUE,
    encerra TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO status_manutencao (id, nome, cor, ordem, sistema, encerra) VALUES
    (1, 'Recebido pelo Hardware', 'slate', 10, 'inicial', 0),
    (2, 'Em diagnóstico', 'sky', 20, NULL, 0),
    (3, 'Aguardando peça', 'amber', 30, NULL, 0),
    (4, 'Em manutenção', 'indigo', 40, NULL, 0),
    (5, 'Concluído', 'emerald', 50, NULL, 1),
    (6, 'Sem conserto', 'red', 60, NULL, 1),
    (7, 'Enviado à ADM', 'violet', 70, 'enviado_erp', 1),
    (8, 'Chegada confirmada na ADM', 'teal', 80, 'recebido_erp', 1);

CREATE TABLE IF NOT EXISTS equipamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(64) NOT NULL UNIQUE,
    tipo_codigo ENUM('patrimonio', 'ns') NOT NULL DEFAULT 'patrimonio',
    tipo VARCHAR(50) NOT NULL,
    marca VARCHAR(80) NULL,
    modelo VARCHAR(120) NULL,
    setor_origem VARCHAR(120) NULL,
    responsavel VARCHAR(120) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_equipamentos_tipo (tipo),
    INDEX idx_equipamentos_setor (setor_origem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manutencoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipamento_id INT UNSIGNED NOT NULL,
    defeito_relatado TEXT NOT NULL,
    servico_executado TEXT NULL,
    status_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NULL,
    aberta_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluida_em DATETIME NULL,
    INDEX idx_manutencoes_aberta (aberta_em),
    CONSTRAINT fk_manutencoes_equipamento FOREIGN KEY (equipamento_id) REFERENCES equipamentos (id),
    CONSTRAINT fk_manutencoes_status FOREIGN KEY (status_id) REFERENCES status_manutencao (id),
    CONSTRAINT fk_manutencoes_tecnico FOREIGN KEY (tecnico_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pecas_trocadas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    manutencao_id INT UNSIGNED NOT NULL,
    descricao VARCHAR(200) NOT NULL,
    quantidade INT UNSIGNED NOT NULL DEFAULT 1,
    CONSTRAINT fk_pecas_manutencao FOREIGN KEY (manutencao_id) REFERENCES manutencoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimentacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    manutencao_id INT UNSIGNED NOT NULL,
    status_anterior_id INT UNSIGNED NULL,
    status_novo_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    setor ENUM('hardware', 'erp') NOT NULL,
    observacao TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    editado_em DATETIME NULL,
    editado_por INT UNSIGNED NULL,
    INDEX idx_movimentacoes_criado (criado_em),
    CONSTRAINT fk_movimentacoes_editado_por FOREIGN KEY (editado_por) REFERENCES usuarios (id) ON DELETE SET NULL,
    CONSTRAINT fk_movimentacoes_manutencao FOREIGN KEY (manutencao_id) REFERENCES manutencoes (id) ON DELETE CASCADE,
    CONSTRAINT fk_movimentacoes_status_ant FOREIGN KEY (status_anterior_id) REFERENCES status_manutencao (id),
    CONSTRAINT fk_movimentacoes_status_novo FOREIGN KEY (status_novo_id) REFERENCES status_manutencao (id),
    CONSTRAINT fk_movimentacoes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- login guarda o texto digitado, para registrar também tentativas com usuário inexistente.
CREATE TABLE IF NOT EXISTS logs_acesso (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    login VARCHAR(50) NOT NULL,
    evento ENUM('login', 'logout', 'falha') NOT NULL,
    detalhe VARCHAR(60) NULL,
    ip VARCHAR(45) NOT NULL,
    navegador VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_criado (criado_em),
    INDEX idx_logs_usuario (usuario_id, criado_em),
    CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditoria de todas as ações. usuario_nome guarda o nome da época, para o log sobreviver à exclusão do usuário.
CREATE TABLE IF NOT EXISTS logs_sistema (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    usuario_nome VARCHAR(100) NOT NULL,
    setor ENUM('hardware', 'erp') NULL,
    categoria VARCHAR(20) NOT NULL,
    acao VARCHAR(60) NOT NULL,
    descricao VARCHAR(500) NOT NULL,
    referencia_id INT UNSIGNED NULL,
    detalhes TEXT NULL,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_sistema_criado (criado_em),
    INDEX idx_logs_sistema_categoria (categoria, criado_em),
    INDEX idx_logs_sistema_usuario (usuario_id, criado_em),
    CONSTRAINT fk_logs_sistema_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anúncios de novas versões do sistema. publicado_em marca o início do aviso de 24h.
CREATE TABLE IF NOT EXISTS atualizacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    versao VARCHAR(30) NULL,
    resumo VARCHAR(300) NOT NULL,
    conteudo TEXT NOT NULL,
    publicado TINYINT(1) NOT NULL DEFAULT 0,
    publicado_em DATETIME NULL,
    criado_por INT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_atualizacoes_publicado (publicado, publicado_em),
    CONSTRAINT fk_atualizacoes_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quem já viu o aviso de cada atualização (o aviso após o login aparece uma única vez).
CREATE TABLE IF NOT EXISTS atualizacoes_vistas (
    usuario_id INT UNSIGNED NOT NULL,
    atualizacao_id INT UNSIGNED NOT NULL,
    visto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, atualizacao_id),
    CONSTRAINT fk_vistas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_vistas_atualizacao FOREIGN KEY (atualizacao_id) REFERENCES atualizacoes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
    chave VARCHAR(60) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes (chave, valor) VALUES
    ('etiqueta_largura_mm', '40'),
    ('etiqueta_altura_mm', '25'),
    ('etiqueta_titulo', 'Hardware');
