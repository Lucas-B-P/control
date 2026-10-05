<?php

require __DIR__ . '/../../src/bootstrap.php';

$usuario = exigir_setor('hardware');

$codigo = normalizar_codigo(input('codigo'));
$equipamento = $codigo !== '' ? equipamento_por_codigo($codigo) : null;
$erros = [];
$dados = [
    'tipo_codigo'  => input('tipo_codigo', 'patrimonio'),
    'tipo'         => input('tipo'),
    'marca'        => input('marca'),
    'modelo'       => input('modelo'),
    'setor_origem' => input('setor_origem'),
    'responsavel'  => input('responsavel'),
];
$defeito = input('defeito');
$tecnicoId = input_int('tecnico_id') ?: (int) $usuario['id'];

if (is_post()) {
    csrf_verificar();

    $dadosEquipamento = null;
    if (!$equipamento) {
        [$dadosEquipamento, $erros] = equipamento_validar(['codigo' => $codigo] + $dados);
    }
    if ($defeito === '') {
        $erros['defeito'] = 'Descreva o defeito relatado.';
    }
    if (!tecnico_valido($tecnicoId)) {
        $erros['tecnico_id'] = 'Escolha um técnico.';
    }

    if (!$erros) {
        try {
            $equipamentoId = $equipamento ? (int) $equipamento['id'] : equipamento_criar($dadosEquipamento);
            $id = manutencao_abrir($equipamentoId, $defeito, $tecnicoId, (int) $usuario['id']);
            flash('sucesso', 'OS aberta. Imprima a etiqueta e cole no equipamento.');
            redirect('manutencoes/ver.php?id=' . $id . '&imprimir=1');
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
            redirect('manutencoes/nova.php?codigo=' . urlencode($codigo));
        }
    }
}

$aberta = $equipamento ? manutencao_aberta((int) $equipamento['id']) : null;
$historico = $equipamento ? manutencoes_do_equipamento((int) $equipamento['id']) : [];
$tecnicos = tecnicos_ativos();

render_header('Abrir OS', 'nova');
?>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <form method="get" class="card p-4" role="search">
            <label for="codigo-busca" class="label">Patrimônio ou número de série</label>
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500"><?= icon('barcode', 'size-4') ?></span>
                    <input id="codigo-busca" name="codigo" class="input pl-9 font-mono uppercase" value="<?= e($codigo) ?>" placeholder="Ex.: 004512 ou SN5CG1234XYZ" autocomplete="off" <?= $codigo === '' ? 'autofocus' : '' ?> required>
                </div>
                <button type="submit" class="btn btn-dark"><?= icon('search', 'size-4') ?> Buscar</button>
            </div>
            <p class="mt-2 text-sm text-muted-foreground">Use o patrimônio. Se o equipamento não tiver, use o número de série (NS) da etiqueta do fabricante.</p>
        </form>

        <?php if ($codigo !== ''): ?>
            <?php if ($aberta): ?>
                <div role="alert" class="card flex flex-wrap items-center gap-3 border-amber-300 bg-amber-50 p-4 text-amber-900">
                    <?= icon('alert') ?>
                    <p class="flex-1 text-sm">Este equipamento já tem uma OS em andamento (<?= e(status_nome((int) $aberta['status_id'])) ?>). Encerre a OS atual antes de abrir outra.</p>
                    <a href="<?= e(url('manutencoes/ver.php?id=' . $aberta['id'])) ?>" class="btn btn-secondary btn-sm">Ver OS atual</a>
                </div>
            <?php else: ?>
                <form method="post" class="card space-y-5 p-4" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="codigo" value="<?= e($codigo) ?>">

                    <?php if ($equipamento): ?>
                        <div>
                            <h2 class="text-base font-semibold">Equipamento encontrado</h2>
                            <dl class="mt-2 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                                <div><dt class="text-muted-foreground">Código</dt><dd class="font-mono font-semibold"><?= e($equipamento['codigo']) ?> <span class="font-sans font-normal text-muted-foreground">(<?= e(TIPOS_CODIGO[$equipamento['tipo_codigo']]) ?>)</span></dd></div>
                                <div><dt class="text-muted-foreground">Equipamento</dt><dd><?= e(equipamento_descricao($equipamento)) ?></dd></div>
                                <div><dt class="text-muted-foreground">Setor de origem</dt><dd><?= e($equipamento['setor_origem'] ?: '-') ?></dd></div>
                                <div><dt class="text-muted-foreground">Responsável</dt><dd><?= e($equipamento['responsavel'] ?: '-') ?></dd></div>
                            </dl>
                            <a class="link mt-2 inline-flex items-center gap-1 text-sm" href="<?= e(url('equipamentos/editar.php?id=' . $equipamento['id'])) ?>"><?= icon('pencil', 'size-3.5') ?> Corrigir dados do equipamento</a>
                        </div>
                    <?php else: ?>
                        <div>
                            <h2 class="text-base font-semibold">Novo equipamento</h2>
                            <p class="text-sm text-muted-foreground">O código <span class="font-mono font-semibold text-foreground"><?= e($codigo) ?></span> ainda não está cadastrado.</p>
                            <?= campo_erro($erros, 'codigo') ?>
                        </div>
                        <?php require __DIR__ . '/../../templates/form_equipamento.php'; ?>
                    <?php endif; ?>

                    <hr class="border-border">

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="defeito" class="label">Defeito relatado</label>
                            <textarea id="defeito" name="defeito" rows="3" class="input" required<?= aria_erro($erros, 'defeito') ?>><?= e($defeito) ?></textarea>
                            <?= campo_erro($erros, 'defeito') ?>
                        </div>
                        <div>
                            <label for="tecnico_id" class="label">Técnico responsável</label>
                            <select id="tecnico_id" name="tecnico_id" class="input"<?= aria_erro($erros, 'tecnico_id') ?>>
                                <?php foreach ($tecnicos as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === $tecnicoId ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= campo_erro($erros, 'tecnico_id') ?>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?> Abrir OS</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if ($equipamento): ?>
        <aside class="card h-fit p-4">
            <h2 class="text-base font-semibold">OS anteriores</h2>
            <?php if (!$historico): ?>
                <p class="mt-2 text-sm text-muted-foreground">Nenhuma OS registrada.</p>
            <?php else: ?>
                <ul class="mt-3 divide-y divide-border">
                    <?php foreach ($historico as $h): ?>
                        <li class="py-2">
                            <a href="<?= e(url('manutencoes/ver.php?id=' . $h['id'])) ?>" class="block rounded-md focus-visible:outline-2 focus-visible:outline-ring">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium">#<?= (int) $h['id'] ?> · <?= e(data_curta($h['aberta_em'])) ?></span>
                                    <?= status_badge((int) $h['status_id']) ?>
                                </div>
                                <p class="mt-1 line-clamp-2 text-sm text-muted-foreground"><?= e($h['defeito_relatado']) ?></p>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>
    <?php endif; ?>
</div>
<?php render_footer();
