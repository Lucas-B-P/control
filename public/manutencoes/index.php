<?php

require __DIR__ . '/../../src/bootstrap.php';

exigir_setor('hardware');

$status = input_int('status');
$tipo = input('tipo');
$setor = input('setor');
$de = input_data('de');
$ate = input_data('ate');
$busca = normalizar_codigo(input('busca'));

$filtro = (new Filtro())
    ->igual('m.status_id', $status, status_por_id($status) !== null)
    ->igual('e.tipo', $tipo, $tipo !== '')
    ->igual('e.setor_origem', $setor, $setor !== '')
    ->periodo('m.aberta_em', $de, $ate)
    ->contem(['e.codigo'], $busca);

$resultado = consulta_paginada(
    'm.*, e.codigo, e.tipo, e.marca, e.modelo, e.setor_origem, u.nome AS tecnico_nome',
    'manutencoes m JOIN equipamentos e ON e.id = m.equipamento_id LEFT JOIN usuarios u ON u.id = m.tecnico_id',
    $filtro,
    'm.aberta_em DESC, m.id DESC'
);

$opcoesStatus = array_map(fn ($s) => $s['nome'] . ($s['ativo'] ? '' : ' (inativo)'), status_todos());

render_header('Ordens de serviço', 'manutencoes');
?>
<form method="get" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6" role="search">
    <div class="lg:col-span-2">
        <label for="busca" class="label">Código</label>
        <input id="busca" name="busca" type="search" class="input font-mono uppercase" value="<?= e($busca) ?>" placeholder="Patrimônio ou NS">
    </div>
    <div>
        <label for="status" class="label">Status</label>
        <select id="status" name="status" class="input">
            <option value="">Todos</option>
            <?= opcoes($opcoesStatus, $status) ?>
        </select>
    </div>
    <div>
        <label for="tipo" class="label">Tipo</label>
        <select id="tipo" name="tipo" class="input">
            <option value="">Todos</option>
            <?= opcoes(opcoes_lista(valores_distintos('tipo')), $tipo) ?>
        </select>
    </div>
    <div class="lg:col-span-2">
        <label for="setor" class="label">Setor de origem</label>
        <select id="setor" name="setor" class="input">
            <option value="">Todos</option>
            <?= opcoes(opcoes_lista(valores_distintos('setor_origem')), $setor) ?>
        </select>
    </div>
    <div>
        <label for="de" class="label">Entrada de</label>
        <input id="de" name="de" type="date" class="input" value="<?= e($de) ?>">
    </div>
    <div>
        <label for="ate" class="label">Entrada até</label>
        <input id="ate" name="ate" type="date" class="input" value="<?= e($ate) ?>">
    </div>
    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
        <button type="submit" class="btn btn-dark"><?= icon('search', 'size-4') ?> Filtrar</button>
        <a href="<?= e(url('manutencoes/index.php')) ?>" class="btn btn-secondary">Limpar</a>
    </div>
</form>

<div class="card overflow-hidden">
    <?php if (!$resultado->linhas): ?>
        <p class="p-6 text-sm text-muted-foreground">Nenhuma OS encontrada.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Equipamento</th>
                        <th>Defeito</th>
                        <th>Técnico</th>
                        <th>Entrada</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($resultado->linhas as $m): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2"><a class="link" href="<?= e(url('manutencoes/ver.php?id=' . $m['id'])) ?>">#<?= (int) $m['id'] ?></a></td>
                            <td class="px-4 py-2 font-mono whitespace-nowrap"><?= e($m['codigo']) ?></td>
                            <td class="max-w-[12rem] px-4 py-2"><span class="block truncate"><?= e(equipamento_descricao($m)) ?></span></td>
                            <td class="max-w-xs px-4 py-2"><span class="line-clamp-1"><?= e($m['defeito_relatado']) ?></span></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= e($m['tecnico_nome'] ?? '-') ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= e(data_curta($m['aberta_em'])) ?></td>
                            <td class="px-4 py-2"><?= status_badge((int) $m['status_id']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php render_paginacao($resultado); ?>
</div>
<?php render_footer();
