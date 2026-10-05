<?php

require __DIR__ . '/../../src/bootstrap.php';

exigir_setor('hardware');

$id = input_int('id');
$equipamento = equipamento_por_id($id) ?? nao_encontrado('Equipamento não encontrado.');
$dados = $equipamento;
$erros = [];

if (is_post()) {
    csrf_verificar();
    [$dados, $erros] = equipamento_validar($_POST, $id);
    if (!$erros) {
        equipamento_atualizar($id, $dados);
        flash('sucesso', 'Dados do equipamento atualizados.');
        redirect('equipamentos/ver.php?id=' . $id);
    }
}

$editarCodigo = true;
render_header('Editar equipamento', 'equipamentos');
?>
<form method="post" class="card max-w-3xl space-y-5 p-4" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <p class="text-sm text-muted-foreground">Se mudar o código, imprima uma nova etiqueta para o equipamento.</p>
    <?php require __DIR__ . '/../../templates/form_equipamento.php'; ?>
    <div class="flex justify-end gap-2">
        <a href="<?= e(url('equipamentos/ver.php?id=' . $id)) ?>" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?> Salvar</button>
    </div>
</form>
<?php render_footer();
