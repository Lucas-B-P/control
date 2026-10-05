<?php

require __DIR__ . '/../src/bootstrap.php';

exigir_admin();

if (is_post()) {
    csrf_verificar();
    $acao = input('acao');

    try {
        $status = $acao === 'criar' ? null : status_por_id(input_int('id')) ?? throw new RuntimeException('Status não encontrado.');
        switch ($acao) {
            case 'criar':
                flash('sucesso', 'Status "' . status_criar($_POST) . '" criado.');
                break;
            case 'salvar':
                flash('sucesso', 'Status "' . status_salvar($status, $_POST) . '" salvo.');
                break;
            case 'alternar':
                status_alternar($status);
                flash('sucesso', 'Status "' . $status['nome'] . '" ' . ($status['ativo'] ? 'desativado.' : 'reativado.'));
                break;
            case 'excluir':
                status_excluir($status);
                flash('sucesso', 'Status "' . $status['nome'] . '" excluído.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash('erro', $ex->getMessage());
    }

    redirect('status.php');
}

$usos = db_pares('SELECT status_id, COUNT(*) FROM manutencoes GROUP BY status_id');
$proximaOrdem = (int) max(array_column(status_todos(), 'ordem') ?: [0]) + 10;

render_header('Status', 'status');
?>
<p class="mb-4 max-w-3xl text-sm text-muted-foreground">
    Estes são os status que o Hardware escolhe para as OS. Marque <strong class="text-foreground">Encerra o serviço</strong> nos status que finalizam o conserto (ex.: Concluído, Sem conserto): a partir deles a OS pode ser enviada à ADM e o aparelho pode ganhar uma nova OS.
    Os status com o selo <strong class="text-foreground">Sistema</strong> são usados pelas regras do fluxo; podem ser renomeados, mas não excluídos.
</p>

<div class="grid gap-6 xl:grid-cols-3">
    <section class="card overflow-hidden xl:col-span-2" aria-labelledby="t-status">
        <h2 id="t-status" class="p-4 pb-2 text-base font-semibold">Status cadastrados</h2>
        <ul class="divide-y divide-border border-t border-border">
            <?php foreach (status_todos() as $s): $sid = (int) $s['id']; ?>
                <li class="p-4 <?= $s['ativo'] ? '' : 'bg-slate-50' ?>">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <?= status_badge($sid) ?>
                        <?php if ($s['sistema']): ?>
                            <?= selo('Sistema', PAPEIS_SISTEMA[$s['sistema']]) ?>
                            <span class="text-xs text-muted-foreground"><?= e(PAPEIS_SISTEMA[$s['sistema']]) ?></span>
                        <?php endif; ?>
                        <?php if (!$s['ativo']): ?>
                            <span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs font-semibold text-slate-700">Inativo</span>
                        <?php endif; ?>
                        <span class="ml-auto text-xs text-muted-foreground"><?= (int) ($usos[$sid] ?? 0) ?> OS neste status</span>
                    </div>
                    <form method="post" class="grid items-end gap-3 sm:grid-cols-12">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="salvar">
                        <input type="hidden" name="id" value="<?= $sid ?>">
                        <div class="sm:col-span-5">
                            <label for="nome-<?= $sid ?>" class="label text-xs">Nome</label>
                            <input id="nome-<?= $sid ?>" name="nome" class="input h-9 text-sm" value="<?= e($s['nome']) ?>" maxlength="60" required>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="cor-<?= $sid ?>" class="label text-xs">Cor</label>
                            <?= select_cor('cor-' . $sid, $s['cor'], 'input h-9 text-sm') ?>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="ordem-<?= $sid ?>" class="label text-xs">Ordem</label>
                            <input id="ordem-<?= $sid ?>" name="ordem" type="number" class="input h-9 text-sm" value="<?= (int) $s['ordem'] ?>">
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-dark btn-sm h-9 w-full">Salvar</button>
                        </div>
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm sm:col-span-12 <?= $s['sistema'] ? 'cursor-not-allowed text-muted-foreground' : '' ?>">
                            <input type="checkbox" name="encerra" value="1" class="size-4 accent-primary" <?= $s['encerra'] ? 'checked' : '' ?> <?= $s['sistema'] ? 'disabled' : '' ?>>
                            Encerra o serviço
                        </label>
                    </form>
                    <?php if (!$s['sistema']): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="alternar">
                                <input type="hidden" name="id" value="<?= $sid ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"><?= $s['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                            </form>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= $sid ?>">
                                <button type="submit" class="btn btn-sm btn-danger-outline" data-confirmar="Excluir o status &quot;<?= e($s['nome']) ?>&quot;?"><?= icon('trash', 'size-3.5') ?> Excluir</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <form method="post" class="card h-fit space-y-4 p-4">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar">
        <h2 class="text-base font-semibold">Novo status</h2>
        <div>
            <label for="novo-nome" class="label">Nome</label>
            <input id="novo-nome" name="nome" class="input" maxlength="60" placeholder="Ex.: Aguardando aprovação" required>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="novo-cor" class="label">Cor</label>
                <?= select_cor('novo-cor', 'blue') ?>
            </div>
            <div>
                <label for="novo-ordem" class="label">Ordem</label>
                <input id="novo-ordem" name="ordem" type="number" class="input" value="<?= $proximaOrdem ?>">
            </div>
        </div>
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" name="encerra" value="1" class="size-4 accent-primary">
            Encerra o serviço
        </label>
        <button type="submit" class="btn btn-primary w-full"><?= icon('plus', 'size-4') ?> Criar status</button>
    </form>
</div>
<?php render_footer();
