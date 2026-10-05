<?php
/**
 * Cria ou atualiza o banco, as tabelas e o usuário administrador inicial.
 * Pode ser executado de novo com segurança: aplica só as atualizações pendentes.
 * Uso: php database/setup.php [senha_do_admin]
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute pela linha de comando.');
}

$arquivoConfig = __DIR__ . '/../config/database.php';
if (!is_file($arquivoConfig)) {
    fwrite(STDERR, "Arquivo config/database.php não encontrado. Copie config/database.example.php para config/database.php e ajuste a conexão.\n");
    exit(1);
}
$db = require $arquivoConfig;

$pdo = new PDO(
    "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4",
    $db['user'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$nomeBanco = str_replace('`', '', $db['database']);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$nomeBanco}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$nomeBanco}`");

function coluna_existe(PDO $pdo, string $tabela, string $coluna): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tabela, $coluna]);

    return (int) $stmt->fetchColumn() > 0;
}

$sql = file_get_contents(__DIR__ . '/schema.sql');
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $comando) {
    $pdo->exec($comando);
}
echo "Banco '{$nomeBanco}' e tabelas prontos.\n";

// Atualização: setores e administrador nos usuários.
if (!coluna_existe($pdo, 'usuarios', 'setor')) {
    $pdo->exec("ALTER TABLE usuarios ADD setor ENUM('hardware', 'erp') NOT NULL DEFAULT 'hardware' AFTER senha_hash, ADD admin TINYINT(1) NOT NULL DEFAULT 0 AFTER setor");
    $pdo->exec("UPDATE usuarios SET admin = 1 WHERE login = 'admin'");
    echo "Atualização: setor e administrador adicionados aos usuários.\n";
}

// Atualização: versão de sessão, para derrubar sessões abertas quando a senha é trocada.
if (!coluna_existe($pdo, 'usuarios', 'sessao_versao')) {
    $pdo->exec('ALTER TABLE usuarios ADD sessao_versao INT UNSIGNED NOT NULL DEFAULT 0 AFTER ativo');
    echo "Atualização: controle de sessões adicionado aos usuários.\n";
}

// Atualização: registro de quem editou a observação de uma movimentação.
if (!coluna_existe($pdo, 'movimentacoes', 'editado_em')) {
    $pdo->exec('ALTER TABLE movimentacoes ADD editado_em DATETIME NULL AFTER criado_em, ADD editado_por INT UNSIGNED NULL AFTER editado_em,
                ADD CONSTRAINT fk_movimentacoes_editado_por FOREIGN KEY (editado_por) REFERENCES usuarios (id) ON DELETE SET NULL');
    echo "Atualização: edição de observações adicionada às movimentações.\n";
}

// Atualização: status fixos no código passam para a tabela status_manutencao.
$mapa = "CASE %s WHEN 'recebido' THEN 1 WHEN 'diagnostico' THEN 2 WHEN 'aguardando_peca' THEN 3 WHEN 'em_manutencao' THEN 4
         WHEN 'concluido' THEN 5 WHEN 'sem_conserto' THEN 6 WHEN 'enviado_erp' THEN 7 WHEN 'recebido_erp' THEN 8 END";

if (coluna_existe($pdo, 'manutencoes', 'status') && !coluna_existe($pdo, 'manutencoes', 'status_id')) {
    $pdo->exec('ALTER TABLE manutencoes ADD status_id INT UNSIGNED NULL AFTER servico_executado');
    $pdo->exec('UPDATE manutencoes SET status_id = ' . sprintf($mapa, 'status'));
    $pdo->exec('ALTER TABLE manutencoes MODIFY status_id INT UNSIGNED NOT NULL, DROP INDEX idx_manutencoes_status, DROP COLUMN status,
                ADD CONSTRAINT fk_manutencoes_status FOREIGN KEY (status_id) REFERENCES status_manutencao (id)');
    echo "Atualização: manutenções migradas para status configuráveis.\n";
}

if (coluna_existe($pdo, 'movimentacoes', 'status_novo') && !coluna_existe($pdo, 'movimentacoes', 'status_novo_id')) {
    $pdo->exec('ALTER TABLE movimentacoes ADD status_anterior_id INT UNSIGNED NULL AFTER manutencao_id, ADD status_novo_id INT UNSIGNED NULL AFTER status_anterior_id');
    $pdo->exec('UPDATE movimentacoes SET status_anterior_id = ' . sprintf($mapa, 'status_anterior') . ', status_novo_id = ' . sprintf($mapa, 'status_novo'));
    $pdo->exec('ALTER TABLE movimentacoes MODIFY status_novo_id INT UNSIGNED NOT NULL, DROP INDEX idx_movimentacoes_status_novo,
                DROP COLUMN status_anterior, DROP COLUMN status_novo,
                ADD CONSTRAINT fk_movimentacoes_status_ant FOREIGN KEY (status_anterior_id) REFERENCES status_manutencao (id),
                ADD CONSTRAINT fk_movimentacoes_status_novo FOREIGN KEY (status_novo_id) REFERENCES status_manutencao (id)');
    echo "Atualização: movimentações migradas para status configuráveis.\n";
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
if ($total === 0) {
    $senha = $argv[1] ?? 'admin123';
    $stmt = $pdo->prepare('INSERT INTO usuarios (nome, login, senha_hash, setor, admin) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute(['Administrador', 'admin', password_hash($senha, PASSWORD_DEFAULT), 'hardware']);
    echo "Usuário criado: login 'admin', senha '{$senha}'. Troque a senha após o primeiro acesso.\n";
} else {
    echo "Usuários já existem; nenhum usuário foi criado.\n";
}
