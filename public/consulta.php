<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_setor('erp');

$codigo = normalizar_codigo(input('codigo'));
$equipamento = $codigo !== '' ? equipamento_por_codigo($codigo) : null;
$manutencao = $equipamento ? manutencao_ultima((int) $equipamento['id']) : null;

if (is_post()) {
    csrf_verificar();

    try {
        if (!$manutencao) {
            throw new RuntimeException('Nenhuma OS encontrada para este código.');
        }
        switch (input('acao')) {
            case 'confirmar':
                manutencao_mudar_status($manutencao, status_sistema_id(STATUS_RECEBIDO_ERP), $usuario, input('observacao'));
                flash('sucesso', 'Chegada de ' . $equipamento['codigo'] . ' confirmada na ADM.');
                redirect('consulta.php');
            case 'devolver':
                manutencao_mudar_status($manutencao, status_sistema_id(STATUS_INICIAL), $usuario, input('observacao'));
                flash('sucesso', 'Aparelho devolvido ao Hardware.');
                break;
            case 'observacao':
                manutencao_observacao($manutencao, $usuario, input('observacao'));
                flash('sucesso', 'Observação registrada.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash('erro', $ex->getMessage());
    }

    redirect('consulta.php?codigo=' . urlencode($codigo));
}

$movimentacoes = $equipamento ? movimentacoes_do_equipamento((int) $equipamento['id']) : [];
$pecas = $manutencao ? pecas_da_manutencao((int) $manutencao['id']) : [];
$aguardando = $manutencao && aguardando_erp($manutencao);

$pendentes = $codigo === '' ? manutencoes_aguardando_erp() : [];

render_header('Confirmar chegada na ADM', 'consulta');
?>
<form method="get" class="card mb-6 p-4 sm:p-6" role="search">
    <label for="codigo" class="label text-base">Bipe o NS ou o patrimônio, ou digite o código</label>
    <div class="flex flex-col gap-2 sm:flex-row">
        <div class="relative flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-500"><?= icon('barcode', 'size-6') ?></span>
            <input id="codigo" name="codigo" class="input h-14 pl-13 font-mono text-xl uppercase" value="<?= e($codigo) ?>" placeholder="Patrimônio ou número de série" autocomplete="off" autofocus required>
        </div>
        <button type="submit" class="btn btn-dark h-14 px-6 text-base"><?= icon('search') ?> Consultar</button>
    </div>
    <p class="mt-2 text-sm text-muted-foreground">O leitor de código de barras preenche o campo e consulta automaticamente.</p>
</form>

<?php if ($codigo === ''): ?>
    <section class="card overflow-hidden" aria-labelledby="t-pendentes">
        <h2 id="t-pendentes" class="p-4 pb-2 text-base font-semibold">Aguardando confirmação de chegada (<?= count($pendentes) ?>)</h2>
        <?php if (!$pendentes): ?>
            <p class="px-4 pb-4 text-sm text-muted-foreground">Nenhum aparelho enviado pelo Hardware aguardando confirmação.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="tabela">
                    <thead class="border-t">
                        <tr>
                            <th>Código</th>
                            <th>Equipamento</th>
                            <th>Setor de origem</th>
                            <th>Enviado em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php foreach ($pendentes as $p): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2 whitespace-nowrap"><a class="link font-mono" href="<?= e(url('consulta.php?codigo=' . urlencode($p['codigo']))) ?>"><?= e($p['codigo']) ?></a></td>
                                <td class="max-w-xs px-4 py-2"><span class="block truncate"><?= e(equipamento_descricao($p)) ?></span></td>
                                <td class="max-w-[12rem] px-4 py-2"><span class="block truncate"><?= e($p['setor_origem'] ?: '-') ?></span></td>
                                <td class="px-4 py-2 whitespace-nowrap"><?= e(data_hora($p['enviado_em'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php elseif (!$equipamento): ?>
    <div role="alert" class="card flex items-center gap-3 border-amber-300 bg-amber-50 p-4 text-amber-900">
        <?= icon('alert') ?>
        <p class="text-sm">Nenhum aparelho encontrado com o código <span class="font-mono font-semibold"><?= e($codigo) ?></span>. Confira se a etiqueta foi lida corretamente.</p>
    </div>
<?php else: ?>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <?php if ($aguardando): ?>
                <form method="post" class="card border-primary p-4 sm:p-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                    <h2 class="text-base font-semibold">Este aparelho foi enviado à ADM pelo Hardware</h2>
                    <div class="mt-3">
                        <label for="observacao" class="label">Observação <span class="font-normal text-muted-foreground">(obrigatória para devolver)</span></label>
                        <textarea id="observacao" name="observacao" rows="2" class="input" placeholder="Ex.: instalado no caixa 3"></textarea>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="submit" name="acao" value="confirmar" class="btn btn-primary h-12 px-6 text-base"><?= icon('check') ?> Confirmar chegada na ADM</button>
                        <button type="submit" name="acao" value="devolver" class="btn btn-danger h-12" data-confirmar="Devolver o aparelho ao Hardware?"><?= icon('undo', 'size-4') ?> Devolver ao Hardware</button>
                    </div>
                </form>
            <?php endif; ?>

            <section class="card p-4 sm:p-6" aria-labelledby="t-equip">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-muted-foreground"><?= e(TIPOS_CODIGO[$equipamento['tipo_codigo']]) ?></p>
                        <h2 id="t-equip" class="font-mono text-2xl font-bold break-all"><?= e($equipamento['codigo']) ?></h2>
                        <p class="mt-1 text-base"><?= e(equipamento_descricao($equipamento)) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="mb-1 text-xs font-semibold text-muted-foreground uppercase">Status atual</p>
                        <?= $manutencao ? status_badge((int) $manutencao['status_id'], 'lg') : '<span class="text-sm text-muted-foreground">Sem OS</span>' ?>
                        <?php if ($manutencao && e_admin()): ?>
                            <a href="<?= e(url('manutencoes/ver.php?id=' . $manutencao['id'])) ?>" class="link mt-2 block text-sm">Gerenciar OS #<?= (int) $manutencao['id'] ?> (admin)</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!$aguardando && $manutencao): ?>
                    <p class="mt-3 rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">
                        <?= manutencao_com_erp($manutencao) ? 'A chegada deste aparelho na ADM já foi confirmada.' : 'Este aparelho ainda está no Hardware; a confirmação fica disponível quando ele for enviado à ADM.' ?>
                    </p>
                <?php endif; ?>
                <dl class="mt-4 grid gap-x-6 gap-y-3 border-t border-border pt-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted-foreground">Setor de origem</dt><dd><?= e($equipamento['setor_origem'] ?: '-') ?></dd></div>
                    <div><dt class="text-muted-foreground">Responsável</dt><dd><?= e($equipamento['responsavel'] ?: '-') ?></dd></div>
                    <?php if ($manutencao): ?>
                        <div><dt class="text-muted-foreground">OS aberta em</dt><dd><?= e(data_hora($manutencao['aberta_em'])) ?></dd></div>
                        <div><dt class="text-muted-foreground">Técnico</dt><dd><?= e($manutencao['tecnico_nome'] ?? '-') ?></dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted-foreground">Defeito relatado</dt><dd class="whitespace-pre-line"><?= e($manutencao['defeito_relatado']) ?></dd></div>
                        <div class="sm:col-span-2"><dt class="text-muted-foreground">Serviço executado</dt><dd class="whitespace-pre-line"><?= e($manutencao['servico_executado'] ?: 'Não informado') ?></dd></div>
                        <div class="sm:col-span-2">
                            <dt class="text-muted-foreground">Peças trocadas</dt>
                            <dd>
                                <?php if ($pecas): ?>
                                    <ul class="mt-1 flex flex-wrap gap-2">
                                        <?php foreach ($pecas as $p): ?>
                                            <li class="rounded-md bg-muted px-2 py-1"><?= (int) $p['quantidade'] ?>x <?= e($p['descricao']) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    Nenhuma
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </section>

            <?php if ($manutencao && !$aguardando): ?>
                <form method="post" class="card space-y-3 p-4 sm:p-6">
                    <?= csrf_field() ?>
                    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">
                    <input type="hidden" name="acao" value="observacao">
                    <label for="obs-livre" class="label">Registrar observação</label>
                    <textarea id="obs-livre" name="observacao" rows="2" class="input" required></textarea>
                    <button type="submit" class="btn btn-secondary"><?= icon('message', 'size-4') ?> Registrar observação</button>
                </form>
            <?php endif; ?>
        </div>

        <section class="card h-fit p-4 sm:p-6" aria-labelledby="t-traj">
            <h2 id="t-traj" class="mb-4 text-base font-semibold">Trajetória</h2>
            <?php render_timeline($movimentacoes, true); ?>
        </section>
    </div>
<?php endif; ?>

<script>
(function () {
    var campo = document.getElementById('codigo');
    if (campo.value) { campo.select(); }
})();
</script>
<?php render_footer();
