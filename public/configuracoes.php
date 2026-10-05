<?php

require __DIR__ . '/../src/bootstrap.php';

exigir_admin();

$erros = [];
$valores = [
    'etiqueta_largura_mm' => config_valor('etiqueta_largura_mm', '40'),
    'etiqueta_altura_mm'  => config_valor('etiqueta_altura_mm', '25'),
    'etiqueta_titulo'     => config_valor('etiqueta_titulo', ''),
];

if (is_post()) {
    csrf_verificar();
    $valores = config_etiqueta_formulario();
    $erros = config_etiqueta_validar($valores);

    if (!$erros) {
        config_etiqueta_salvar($valores);
        flash('sucesso', 'Configurações salvas.');
        redirect('configuracoes.php');
    }
}

$tamanhos = [['40', '25'], ['50', '30'], ['60', '40'], ['100', '50']];

render_header('Configurações', 'configuracoes');
?>
<form method="post" class="card max-w-2xl space-y-5 p-4" novalidate>
    <?= csrf_field() ?>
    <div>
        <h2 class="text-base font-semibold">Etiqueta</h2>
        <p class="text-sm text-muted-foreground">Use o mesmo tamanho configurado no driver da impressora térmica. Para números de série longos, prefira 50 x 30 mm ou maior.</p>
    </div>

    <div class="flex flex-wrap gap-2" aria-label="Tamanhos comuns">
        <?php foreach ($tamanhos as [$l, $a]): ?>
            <button type="button" class="btn btn-secondary btn-sm" data-tamanho="<?= $l ?>x<?= $a ?>"><?= $l ?> x <?= $a ?> mm</button>
        <?php endforeach; ?>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="etiqueta_largura_mm" class="label">Largura (mm)</label>
            <input id="etiqueta_largura_mm" name="etiqueta_largura_mm" inputmode="decimal" class="input" value="<?= e($valores['etiqueta_largura_mm']) ?>" required<?= aria_erro($erros, 'etiqueta_largura_mm') ?>>
            <?= campo_erro($erros, 'etiqueta_largura_mm') ?>
        </div>
        <div>
            <label for="etiqueta_altura_mm" class="label">Altura (mm)</label>
            <input id="etiqueta_altura_mm" name="etiqueta_altura_mm" inputmode="decimal" class="input" value="<?= e($valores['etiqueta_altura_mm']) ?>" required<?= aria_erro($erros, 'etiqueta_altura_mm') ?>>
            <?= campo_erro($erros, 'etiqueta_altura_mm') ?>
        </div>
        <div class="sm:col-span-2">
            <label for="etiqueta_titulo" class="label">Texto no topo da etiqueta</label>
            <input id="etiqueta_titulo" name="etiqueta_titulo" class="input" value="<?= e($valores['etiqueta_titulo']) ?>" maxlength="40" placeholder="Ex.: Hardware">
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?> Salvar</button>
    </div>
</form>

<script>
document.querySelectorAll('[data-tamanho]').forEach(function (botao) {
    botao.addEventListener('click', function () {
        var partes = botao.getAttribute('data-tamanho').split('x');
        document.getElementById('etiqueta_largura_mm').value = partes[0];
        document.getElementById('etiqueta_altura_mm').value = partes[1];
    });
});
</script>
<?php render_footer();
