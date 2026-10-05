<?php

require __DIR__ . '/../src/bootstrap.php';

exigir_admin();

$usuarioId = input_int('usuario');
$categoria = input('categoria');
$busca = input('busca');
$de = input_data('de');
$ate = input_data('ate');

$filtro = (new Filtro())
    ->igual('usuario_id', $usuarioId, $usuarioId > 0)
    ->igual('categoria', $categoria, array_key_exists($categoria, CATEGORIAS_LOG))
    ->contem(['descricao', 'acao'], $busca)
    ->periodo('criado_em', $de, $ate);

$resultado = consulta_paginada('*', 'logs_sistema', $filtro, 'criado_em DESC, id DESC');

$hoje = db_linha('SELECT COUNT(*) AS acoes, COUNT(DISTINCT usuario_id) AS usuarios FROM logs_sistema WHERE criado_em >= CURDATE()');
$opcoesUsuario = array_column(usuarios_para_filtro(), 'nome', 'id');

render_header('Logs do sistema', 'logs_sistema');
?>
<p class="mb-4 max-w-3xl text-sm text-muted-foreground">
    Registro de todas as ações feitas no sistema: OS, equipamentos, etiquetas, usuários, status e configurações.
    Os logins e saídas ficam em <a class="link" href="<?= e(url('logs.php')) ?>">Logs de acesso</a>.
</p>

<div class="mb-4 grid gap-3 sm:grid-cols-2">
    <?= cartao_indicador('Ações hoje', (int) $hoje['acoes']) ?>
    <?= cartao_indicador('Usuários que fizeram ações hoje', (int) $hoje['usuarios']) ?>
</div>

<form method="get" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-6" role="search">
    <div class="lg:col-span-2">
        <label for="busca" class="label">Buscar</label>
        <input id="busca" name="busca" type="search" class="input" value="<?= e($busca) ?>" placeholder="Código, OS #, nome, ação...">
    </div>
    <div>
        <label for="usuario" class="label">Usuário</label>
        <select id="usuario" name="usuario" class="input">
            <option value="">Todos</option>
            <?= opcoes($opcoesUsuario, $usuarioId) ?>
        </select>
    </div>
    <div>
        <label for="categoria" class="label">Área</label>
        <select id="categoria" name="categoria" class="input">
            <option value="">Todas</option>
            <?= opcoes(CATEGORIAS_LOG, $categoria) ?>
        </select>
    </div>
    <div>
        <label for="de" class="label">De</label>
        <input id="de" name="de" type="date" class="input" value="<?= e($de) ?>">
    </div>
    <div>
        <label for="ate" class="label">Até</label>
        <input id="ate" name="ate" type="date" class="input" value="<?= e($ate) ?>">
    </div>
    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
        <button type="submit" class="btn btn-dark"><?= icon('search', 'size-4') ?> Filtrar</button>
        <a href="<?= e(url('logs_sistema.php')) ?>" class="btn btn-secondary">Limpar</a>
        <span class="ml-auto self-center text-sm text-muted-foreground"><?= $resultado->total ?> registro(s)</span>
    </div>
</form>

<div class="card overflow-hidden">
    <?php if (!$resultado->linhas): ?>
        <p class="p-6 text-sm text-muted-foreground">Nenhuma ação registrada<?= $filtro->ativo() ? ' com esses filtros' : '' ?>.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Data e hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Descrição</th>
                        <th>Computador (IP)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($resultado->linhas as $l): $href = log_link($l); $alteracoes = $l['detalhes'] ? json_decode($l['detalhes'], true) : []; ?>
                        <tr class="align-top hover:bg-slate-50">
                            <td class="px-4 py-2 whitespace-nowrap"><?= e(data_hora($l['criado_em'], true)) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <span class="font-medium"><?= e($l['usuario_nome']) ?></span>
                                <span class="block text-xs text-muted-foreground"><?= e(SETORES[$l['setor']] ?? '') ?><?= $l['usuario_id'] ? '' : ' · excluído' ?></span>
                            </td>
                            <td class="px-4 py-2">
                                <span class="font-medium whitespace-nowrap"><?= e($l['acao']) ?></span>
                                <span class="block text-xs text-muted-foreground"><?= e(CATEGORIAS_LOG[$l['categoria']] ?? $l['categoria']) ?></span>
                            </td>
                            <td class="max-w-xl px-4 py-2">
                                <?php if ($href): ?>
                                    <a class="link" href="<?= e($href) ?>"><?= e($l['descricao']) ?></a>
                                <?php else: ?>
                                    <?= e($l['descricao']) ?>
                                <?php endif; ?>
                                <?php if ($alteracoes): ?>
                                    <details class="mt-1">
                                        <summary class="cursor-pointer text-xs font-medium text-primary">Ver alterações (<?= count($alteracoes) ?>)</summary>
                                        <table class="mt-2 w-full text-xs">
                                            <thead class="text-muted-foreground"><tr><th class="py-1 pr-2 font-semibold">Campo</th><th class="py-1 pr-2 font-semibold">Antes</th><th class="py-1 font-semibold">Depois</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($alteracoes as $campo => [$antes, $depois]): ?>
                                                    <tr class="border-t border-border">
                                                        <td class="py-1 pr-2 font-medium"><?= e($campo) ?></td>
                                                        <td class="py-1 pr-2 whitespace-pre-line text-red-800"><?= e($antes !== '' ? $antes : '(vazio)') ?></td>
                                                        <td class="py-1 whitespace-pre-line text-emerald-800"><?= e($depois !== '' ? $depois : '(vazio)') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 font-mono whitespace-nowrap"><?= e(ip_legivel($l['ip'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php render_paginacao($resultado, true); ?>
</div>
<?php render_footer();
