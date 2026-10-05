<?php

require __DIR__ . '/../src/bootstrap.php';

exigir_setor('hardware');

$equipamento = equipamento_por_id(input_int('id')) ?? nao_encontrado('Equipamento não encontrado.');

$largura = max(20, min(120, (float) config_valor('etiqueta_largura_mm', '40')));
$altura = max(10, min(100, (float) config_valor('etiqueta_altura_mm', '25')));
$titulo = config_valor('etiqueta_titulo', '');
$auto = input('auto') === '1';
auditar('etiqueta', 'Etiqueta gerada para impressão', 'Etiqueta do equipamento ' . $equipamento['codigo'] . " ({$largura} x {$altura} mm)", (int) $equipamento['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta <?= e($equipamento['codigo']) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('favicon-32x32.png')) ?>">
    <style>
        @page { size: <?= $largura ?>mm <?= $altura ?>mm; margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; background: #E2E8F0; color: #000; }

        .etiqueta {
            width: <?= $largura ?>mm;
            height: <?= $altura ?>mm;
            box-sizing: border-box;
            padding: 1mm 1.5mm;
            background: #FFF;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .topo { display: flex; justify-content: space-between; gap: 1mm; font-size: 5.5pt; font-weight: bold; line-height: 1.15; }
        .barras { flex: 1; min-height: 0; display: flex; }
        .barras svg { width: 100%; height: 100%; display: block; }
        .codigo { font-family: "Courier New", monospace; font-weight: bold; font-size: 8.5pt; text-align: center; line-height: 1.1; letter-spacing: .3px; }
        .descricao { font-size: 5pt; text-align: center; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .controles { font-family: system-ui, sans-serif; max-width: 520px; margin: 24px auto 0; padding: 0 16px; color: #0F172A; }
        .controles h1 { font-size: 18px; margin: 0 0 4px; }
        .controles p { font-size: 14px; color: #475569; margin: 0 0 12px; }
        .controles button, .controles a {
            display: inline-flex; align-items: center; height: 40px; padding: 0 16px; border-radius: 6px;
            font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background-color 150ms ease;
        }
        .controles button { background: #059669; color: #000; border: 0; }
        .controles button:hover { background: #047857; color: #FFF; }
        .controles a { color: #334155; border: 1px solid #334155; margin-left: 8px; }
        .controles a:hover { background: #F2F3F4; }
        .controles :focus-visible { outline: 2px solid #334155; outline-offset: 2px; }
        .previa { display: flex; justify-content: center; padding: 24px 0 48px; }

        @media screen {
            .etiqueta { zoom: 3; box-shadow: 0 1px 2px rgba(0, 0, 0, .15); }
        }
        @media print {
            body { background: #FFF; }
            .controles { display: none; }
            .previa { padding: 0; display: block; }
        }
    </style>
</head>
<body>
    <div class="controles">
        <h1>Etiqueta <?= e($equipamento['codigo']) ?></h1>
        <p>Tamanho <?= e((string) $largura) ?> x <?= e((string) $altura) ?> mm. Na janela de impressão, escolha a impressora térmica, margens "Nenhuma" e escala 100%.</p>
        <button type="button" onclick="window.print()">Imprimir</button>
        <a href="<?= e(url('equipamentos/ver.php?id=' . $equipamento['id'])) ?>">Voltar ao equipamento</a>
    </div>

    <div class="previa">
        <div class="etiqueta">
            <div class="topo">
                <span><?= e($titulo) ?></span>
                <span><?= e(date('d/m/Y')) ?></span>
            </div>
            <div class="barras"><svg id="codigo-barras" role="img" aria-label="Código de barras <?= e($equipamento['codigo']) ?>"></svg></div>
            <div class="codigo"><?= e($equipamento['codigo']) ?></div>
            <div class="descricao"><?= e(equipamento_descricao($equipamento)) ?></div>
        </div>
    </div>

    <script src="<?= e(asset('vendor/JsBarcode.all.min.js')) ?>"></script>
    <script>
    (function () {
        var svg = document.getElementById('codigo-barras');
        // margin = 10 módulos de zona de silêncio de cada lado, exigida pelo Code128.
        JsBarcode(svg, <?= json_encode($equipamento['codigo'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, {
            format: 'CODE128', width: 2, height: 80, margin: 20, marginTop: 0, marginBottom: 0, displayValue: false
        });
        var w = svg.getAttribute('width'), h = svg.getAttribute('height');
        if (!svg.getAttribute('viewBox')) {
            svg.setAttribute('viewBox', '0 0 ' + parseFloat(w) + ' ' + parseFloat(h));
        }
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        svg.removeAttribute('style');
        svg.setAttribute('preserveAspectRatio', 'none');
        <?php if ($auto): ?>
        window.addEventListener('load', function () { window.print(); });
        <?php endif; ?>
    })();
    </script>
</body>
</html>
