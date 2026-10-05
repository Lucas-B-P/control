<?php

require __DIR__ . '/../src/bootstrap.php';

exigir_admin();

if (is_post()) {
    csrf_verificar();
    try {
        $arquivo = backup_gerar();
        auditar('configuracao', 'Backup do banco gerado', 'Backup ' . basename($arquivo) . ' (' . backup_tamanho($arquivo) . ')');
        flash('sucesso', 'Backup concluído: ' . basename($arquivo));
    } catch (RuntimeException $ex) {
        flash('erro', $ex->getMessage());
    }
    redirect('backup.php');
}

if (isset($_GET['baixar'])) {
    $arquivo = backup_arquivo((string) $_GET['baixar']) ?? nao_encontrado('Backup não encontrado.');
    auditar('configuracao', 'Backup baixado', 'Download do backup ' . basename($arquivo));
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($arquivo) . '"');
    header('Content-Length: ' . filesize($arquivo));
    readfile($arquivo);
    exit;
}

$backups = backup_lista();

render_header('Backup do banco de dados', 'backup');
?>
<div class="grid max-w-4xl gap-6">
    <section class="card space-y-3 p-4 sm:p-6">
        <p class="text-sm text-muted-foreground">
            Gera uma cópia completa do banco (OS, equipamentos, usuários, status, logs e configurações) na pasta <span class="font-mono">Control/Backup</span>.
            São guardados os <strong class="text-foreground"><?= BACKUP_MANTER ?> backups mais recentes</strong>; ao gerar um novo, o mais antigo é apagado.
        </p>
        <p class="text-sm text-muted-foreground">Recomendado: baixe um backup de vez em quando e guarde em outro computador ou na rede.</p>
        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" data-confirmar="Gerar um novo backup do banco de dados agora?"><?= icon('database', 'size-4') ?> Fazer backup agora</button>
        </form>
    </section>

    <section class="card overflow-hidden" aria-labelledby="t-backups">
        <h2 id="t-backups" class="p-4 pb-2 text-base font-semibold">Backups salvos (<?= count($backups) ?> de <?= BACKUP_MANTER ?>)</h2>
        <?php if (!$backups): ?>
            <p class="border-t border-border p-4 text-sm text-muted-foreground">Nenhum backup gerado ainda.</p>
        <?php else: ?>
            <ul class="divide-y divide-border border-t border-border">
                <?php foreach ($backups as $i => $arquivo): ?>
                    <li class="flex flex-wrap items-center gap-2 p-4">
                        <span class="font-medium"><?= e(date('d/m/Y H:i:s', filemtime($arquivo))) ?></span>
                        <?php if ($i === 0): ?><?= selo('Mais recente') ?><?php endif; ?>
                        <span class="font-mono text-xs text-muted-foreground"><?= e(basename($arquivo)) ?></span>
                        <span class="text-sm text-muted-foreground"><?= e(backup_tamanho($arquivo)) ?></span>
                        <a href="<?= e(url('backup.php?baixar=' . rawurlencode(basename($arquivo)))) ?>" class="btn btn-secondary btn-sm ml-auto"><?= icon('download', 'size-3.5') ?> Baixar</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php render_footer();
