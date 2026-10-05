<?php

declare(strict_types=1);

const BACKUP_PASTA = __DIR__ . '/../Backup';
const BACKUP_MANTER = 7;

function backup_mysqldump(): string
{
    return (string) (app_config('mysqldump') ?? 'C:/xampp/mysql/bin/mysqldump.exe');
}

/** Backups existentes, do mais recente para o mais antigo. */
function backup_lista(): array
{
    $arquivos = glob(BACKUP_PASTA . '/backup_control_*.sql') ?: [];
    clearstatcache();
    usort($arquivos, fn ($a, $b) => filemtime($b) <=> filemtime($a) ?: strcmp($b, $a));

    return $arquivos;
}

/** Caminho do backup pelo nome, somente se for um dos backups da pasta (evita baixar outros arquivos). */
function backup_arquivo(string $nome): ?string
{
    foreach (backup_lista() as $arquivo) {
        if (basename($arquivo) === $nome) {
            return $arquivo;
        }
    }

    return null;
}

/** Gera um backup completo do banco e apaga os que passarem de BACKUP_MANTER. Retorna o caminho do novo arquivo. */
function backup_gerar(): string
{
    backup_preparar_pasta();
    if (!is_file(backup_mysqldump())) {
        throw new RuntimeException('mysqldump não encontrado em ' . backup_mysqldump() . '.');
    }

    $final = BACKUP_PASTA . '/backup_control_' . date('Y-m-d_H-i-s') . '.sql';
    $temporario = $final . '.tmp';
    backup_executar_dump($temporario);
    if (!rename($temporario, $final)) {
        @unlink($temporario);
        throw new RuntimeException('Não foi possível salvar o arquivo de backup.');
    }
    backup_remover_excedentes();

    return $final;
}

function backup_preparar_pasta(): void
{
    if (!is_dir(BACKUP_PASTA) && !mkdir(BACKUP_PASTA, 0770, true)) {
        throw new RuntimeException('Não foi possível criar a pasta Backup.');
    }
    if (!is_file(BACKUP_PASTA . '/.htaccess')) {
        file_put_contents(BACKUP_PASTA . '/.htaccess', "Require all denied\n");
    }
}

function backup_executar_dump(string $destino): void
{
    $db = require __DIR__ . '/../config/database.php';
    // Credenciais num arquivo temporário, para a senha não aparecer na linha de comando.
    $credenciais = BACKUP_PASTA . '/.credenciais_' . bin2hex(random_bytes(6)) . '.cnf';
    file_put_contents($credenciais, "[client]\nuser=\"{$db['user']}\"\npassword=\"{$db['password']}\"\nhost=\"{$db['host']}\"\nport={$db['port']}\n");

    try {
        $comando = escapeshellarg(backup_mysqldump())
            . ' --defaults-extra-file=' . escapeshellarg($credenciais)
            . ' --single-transaction --routines --triggers --default-character-set=utf8mb4'
            . ' --result-file=' . escapeshellarg($destino)
            . ' ' . escapeshellarg($db['database']) . ' 2>&1';
        exec($comando, $saida, $codigo);
    } finally {
        @unlink($credenciais);
    }

    if ($codigo !== 0 || !backup_completo($destino)) {
        @unlink($destino);
        error_log('Backup do banco falhou: ' . implode("\n", $saida));
        throw new RuntimeException('O backup falhou. Verifique se o MySQL do XAMPP está ligado.');
    }
}

/** O mysqldump só escreve "Dump completed" no fim quando termina sem erro. */
function backup_completo(string $arquivo): bool
{
    if (!is_file($arquivo)) {
        return false;
    }
    $fim = (string) file_get_contents($arquivo, false, null, max(0, filesize($arquivo) - 200));

    return str_contains($fim, 'Dump completed');
}

function backup_remover_excedentes(): void
{
    foreach (array_slice(backup_lista(), BACKUP_MANTER) as $antigo) {
        unlink($antigo);
    }
}

function backup_tamanho(string $arquivo): string
{
    $kb = filesize($arquivo) / 1024;

    return $kb >= 1024 ? number_format($kb / 1024, 1, ',', '.') . ' MB' : number_format($kb, 0, ',', '.') . ' KB';
}
