<?php

require __DIR__ . '/../../src/bootstrap.php';

exigir_setor('hardware');

$busca = input('busca');
$tipo = input('tipo');
$setor = input('setor');

$filtro = (new Filtro())
    ->contem(['e.codigo', 'e.marca', 'e.modelo', 'e.responsavel'], $busca)
    ->igual('e.tipo', $tipo, $tipo !== '')
    ->igual('e.setor_origem', $setor, $setor !== '');

$ultimaOs = 'FROM manutencoes m WHERE m.equipamento_id = e.id ORDER BY m.id DESC LIMIT 1';
$resultado = consulta_paginada(
    "e.*,
        (SELECT m.id {$ultimaOs}) AS ultima_os,
        (SELECT m.status_id {$ultimaOs}) AS ultimo_status,
        (SELECT COUNT(*) FROM manutencoes m WHERE m.equipamento_id = e.id) AS total_manutencoes",
    'equipamentos e',
    $filtro,
    'e.criado_em DESC'
);

render_header('Equipamentos', 'equipamentos');
?>
<form method="get" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4" role="search">
    <div class="lg:col-span-2">
        <label for="busca" class="label">Buscar</label>
        <input id="busca" name="busca" type="search" class="input" value="<?= e($busca) ?>" placeholder="Código, marca, modelo ou responsável">
    </div>
    <div>
        <label for="tipo" class="label">Tipo</label>
        <select id="tipo" name="tipo" class="input">
            <option value="">Todos</option>
            <?= opcoes(opcoes_lista(valores_distintos('tipo')), $tipo) ?>
        </select>
    </div>
    <div>
        <label for="setor" class="label">Setor de origem</label>
        <select id="setor" name="setor" class="input">
            <option value="">Todos</option>
            <?= opcoes(opcoes_lista(valores_distintos('setor_origem')), $setor) ?>
        </select>
    </div>
    <div class="flex gap-2 sm:col-span-2 lg:col-span-4">
        <button type="submit" class="btn btn-dark"><?= icon('search', 'size-4') ?> Filtrar</button>
        <a href="<?= e(url('equipamentos/index.php')) ?>" class="btn btn-secondary">Limpar</a>
    </div>
</form>

<div class="card overflow-hidden">
    <?php if (!$resultado->linhas): ?>
        <p class="p-6 text-sm text-muted-foreground">Nenhum equipamento encontrado.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Equipamento</th>
                        <th>Setor de origem</th>
                        <th>OS</th>
                        <th>Status atual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($resultado->linhas as $eq): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <a class="link font-mono" href="<?= e(url('equipamentos/ver.php?id=' . $eq['id'])) ?>"><?= e($eq['codigo']) ?></a>
                                <span class="ml-1 text-xs text-muted-foreground"><?= $eq['tipo_codigo'] === 'ns' ? 'NS' : 'Patr.' ?></span>
                            </td>
                            <td class="max-w-xs px-4 py-2"><span class="block truncate"><?= e(equipamento_descricao($eq)) ?></span></td>
                            <td class="max-w-[12rem] px-4 py-2"><span class="block truncate"><?= e($eq['setor_origem'] ?: '-') ?></span></td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <?php if ($eq['ultima_os'] !== null): ?>
                                    <a class="link font-mono" href="<?= e(url('manutencoes/ver.php?id=' . (int) $eq['ultima_os'])) ?>">#<?= (int) $eq['ultima_os'] ?></a>
                                    <?php if ((int) $eq['total_manutencoes'] > 1): ?>
                                        <span class="ml-1 text-xs text-muted-foreground" title="Total de OS deste equipamento"><?= (int) $eq['total_manutencoes'] ?> no total</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted-foreground">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2"><?= status_badge($eq['ultimo_status'] !== null ? (int) $eq['ultimo_status'] : null) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php render_paginacao($resultado); ?>
</div>
<?php render_footer();
