<?php
/** @var array $dados @var array $erros @var bool|null $editarCodigo */
$editarCodigo ??= false;
$setores = valores_distintos('setor_origem');
?>
<div class="grid gap-4 sm:grid-cols-2">
    <?php if ($editarCodigo): ?>
        <div>
            <label for="codigo" class="label">Código</label>
            <input id="codigo" name="codigo" class="input font-mono uppercase" value="<?= e($dados['codigo'] ?? '') ?>" required<?= aria_erro($erros, 'codigo') ?>>
            <?= campo_erro($erros, 'codigo') ?>
        </div>
    <?php endif; ?>
    <fieldset class="<?= $editarCodigo ? '' : 'sm:col-span-2' ?>">
        <legend class="label">O código é</legend>
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <?php foreach (TIPOS_CODIGO as $valor => $rotulo): ?>
                <label class="inline-flex h-10 cursor-pointer items-center gap-2 text-sm">
                    <input type="radio" name="tipo_codigo" value="<?= e($valor) ?>" class="size-4 accent-primary" <?= ($dados['tipo_codigo'] ?? 'patrimonio') === $valor ? 'checked' : '' ?>>
                    <?= e($rotulo) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= campo_erro($erros, 'tipo_codigo') ?>
    </fieldset>
    <div>
        <label for="tipo" class="label">Tipo</label>
        <input id="tipo" name="tipo" list="lista-tipos" class="input" value="<?= e($dados['tipo'] ?? '') ?>" placeholder="Desktop, notebook..." required<?= aria_erro($erros, 'tipo') ?>>
        <datalist id="lista-tipos">
            <?php foreach (TIPOS_EQUIPAMENTO as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?>
        </datalist>
        <?= campo_erro($erros, 'tipo') ?>
    </div>
    <div>
        <label for="marca" class="label">Marca</label>
        <input id="marca" name="marca" class="input" value="<?= e($dados['marca'] ?? '') ?>" maxlength="80">
    </div>
    <div>
        <label for="modelo" class="label">Modelo</label>
        <input id="modelo" name="modelo" class="input" value="<?= e($dados['modelo'] ?? '') ?>" maxlength="120">
    </div>
    <div>
        <label for="setor_origem" class="label">Setor / cliente de origem</label>
        <input id="setor_origem" name="setor_origem" list="lista-setores" class="input" value="<?= e($dados['setor_origem'] ?? '') ?>" maxlength="120">
        <datalist id="lista-setores">
            <?php foreach ($setores as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
        </datalist>
    </div>
    <div>
        <label for="responsavel" class="label">Responsável no setor</label>
        <input id="responsavel" name="responsavel" class="input" value="<?= e($dados['responsavel'] ?? '') ?>" maxlength="120">
    </div>
</div>
