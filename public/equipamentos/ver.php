<?php

require __DIR__ . '/../../src/bootstrap.php';

exigir_setor('hardware');

$id = input_int('id');
$equipamento = equipamento_por_id($id) ?? nao_encontrado('Equipamento não encontrado.');
$manutencoes = manutencoes_do_equipamento($id);
$movimentacoes = movimentacoes_do_equipamento($id);
$aberta = manutencao_aberta($id);

render_header('Equipamento ' . $equipamento['codigo'], 'equipamentos');
?>
<div class="mb-4 flex flex-wrap items-center gap-2">
    <a href="<?= e(url('etiqueta.php?id=' . $id)) ?>" class="btn btn-dark" target="_blank" rel="noopener"><?= icon('printer', 'size-4') ?> Imprimir etiqueta</a>
    <?php if ($aberta): ?>
        <a href="<?= e(url('manutencoes/ver.php?id=' . $aberta['id'])) ?>" class="btn btn-secondary"><?= icon('wrench', 'size-4') ?> OS em andamento</a>
    <?php else: ?>
        <a href="<?= e(url('manutencoes/nova.php?codigo=' . urlencode($equipamento['codigo']))) ?>" class="btn btn-primary"><?= icon('plus', 'size-4') ?> Abrir OS</a>
    <?php endif; ?>
    <a href="<?= e(url('equipamentos/editar.php?id=' . $id)) ?>" class="btn btn-secondary"><?= icon('pencil', 'size-4') ?> Editar</a>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="card p-4" aria-labelledby="t-dados">
            <h2 id="t-dados" class="text-base font-semibold">Dados do equipamento</h2>
            <dl class="mt-3 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-muted-foreground">Código</dt><dd class="font-mono text-base font-semibold"><?= e($equipamento['codigo']) ?></dd></div>
                <div><dt class="text-muted-foreground">Tipo de código</dt><dd><?= e(TIPOS_CODIGO[$equipamento['tipo_codigo']]) ?></dd></div>
                <div><dt class="text-muted-foreground">Tipo</dt><dd><?= e($equipamento['tipo']) ?></dd></div>
                <div><dt class="text-muted-foreground">Marca / modelo</dt><dd><?= e(trim(($equipamento['marca'] ?? '') . ' ' . ($equipamento['modelo'] ?? '')) ?: '-') ?></dd></div>
                <div><dt class="text-muted-foreground">Setor de origem</dt><dd><?= e($equipamento['setor_origem'] ?: '-') ?></dd></div>
                <div><dt class="text-muted-foreground">Responsável</dt><dd><?= e($equipamento['responsavel'] ?: '-') ?></dd></div>
                <div><dt class="text-muted-foreground">Cadastrado em</dt><dd><?= e(data_hora($equipamento['criado_em'])) ?></dd></div>
            </dl>
        </section>

        <section class="card overflow-hidden" aria-labelledby="t-manut">
            <h2 id="t-manut" class="p-4 pb-2 text-base font-semibold">Ordens de serviço (<?= count($manutencoes) ?>)</h2>
            <?php if (!$manutencoes): ?>
                <p class="px-4 pb-4 text-sm text-muted-foreground">Nenhuma OS registrada.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="tabela">
                        <thead class="border-t">
                            <tr><th>#</th><th>Entrada</th><th>Defeito</th><th>Técnico</th><th>Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($manutencoes as $m): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2"><a class="link" href="<?= e(url('manutencoes/ver.php?id=' . $m['id'])) ?>">#<?= (int) $m['id'] ?></a></td>
                                    <td class="px-4 py-2 whitespace-nowrap"><?= e(data_curta($m['aberta_em'])) ?></td>
                                    <td class="max-w-xs px-4 py-2"><span class="line-clamp-1"><?= e($m['defeito_relatado']) ?></span></td>
                                    <td class="px-4 py-2 whitespace-nowrap"><?= e($m['tecnico_nome'] ?? '-') ?></td>
                                    <td class="px-4 py-2"><?= status_badge((int) $m['status_id']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="card h-fit p-4" aria-labelledby="t-traj">
        <h2 id="t-traj" class="mb-4 text-base font-semibold">Trajetória completa</h2>
        <?php render_timeline($movimentacoes, true); ?>
    </section>
</div>
<?php render_footer();
