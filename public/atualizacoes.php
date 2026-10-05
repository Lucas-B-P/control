<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_login();
$atualizacoes = atualizacoes_publicadas();
$naoVistas = array_column(atualizacoes_nao_vistas((int) $usuario['id']), 'id');
// Quem abriu a página já viu tudo: o aviso pós-login não precisa aparecer depois.
atualizacoes_marcar_vistas((int) $usuario['id'], $naoVistas);

render_header('Novidades do sistema', 'atualizacoes');
?>
<div class="mx-auto max-w-3xl">
    <p class="mb-4 text-sm text-muted-foreground">O que mudou no <?= e(app_config('nome')) ?>, da atualização mais recente para a mais antiga.</p>

    <?php if (!$atualizacoes): ?>
        <div class="card p-6 text-sm text-muted-foreground">Nenhuma atualização anunciada ainda.</div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php foreach ($atualizacoes as $a): $nova = in_array($a['id'], $naoVistas, false); ?>
            <article id="atualizacao-<?= (int) $a['id'] ?>" class="card scroll-mt-20 p-4 sm:p-6 <?= $nova ? 'border-primary' : '' ?>" aria-labelledby="t-atualizacao-<?= (int) $a['id'] ?>">
                <header class="mb-3 border-b border-border pb-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="t-atualizacao-<?= (int) $a['id'] ?>" class="text-lg font-semibold"><?= e($a['titulo']) ?></h2>
                        <?php if ($a['versao']): ?><?= selo('v' . $a['versao']) ?><?php endif; ?>
                        <?php if ($nova): ?><span class="rounded bg-primary px-1.5 py-0.5 text-xs font-semibold text-white">Nova</span><?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground"><time datetime="<?= e(date('c', strtotime($a['publicado_em']))) ?>"><?= e(data_hora($a['publicado_em'])) ?></time></p>
                    <p class="mt-2 text-sm font-medium"><?= e($a['resumo']) ?></p>
                </header>
                <?= texto_formatado($a['conteudo']) ?>
            </article>
        <?php endforeach; ?>
    </div>
</div>
<?php render_footer();
