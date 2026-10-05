<?php

require __DIR__ . '/../src/bootstrap.php';

exigir_admin();

const EVENTOS_ACESSO = [
    'login'  => ['Entrou', 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
    'logout' => ['Saiu', 'bg-slate-50 text-slate-700 ring-slate-300'],
    'falha'  => ['Falha no login', 'bg-red-50 text-red-800 ring-red-300'],
];

$usuarioId = input_int('usuario');
$evento = input('evento');
$de = input_data('de');
$ate = input_data('ate');

$filtro = (new Filtro())
    ->igual('l.usuario_id', $usuarioId, $usuarioId > 0)
    ->igual('l.evento', $evento, array_key_exists($evento, EVENTOS_ACESSO))
    ->periodo('l.criado_em', $de, $ate);

$resultado = consulta_paginada(
    'l.*, u.nome AS usuario_nome, u.setor',
    'logs_acesso l LEFT JOIN usuarios u ON u.id = l.usuario_id',
    $filtro,
    'l.criado_em DESC, l.id DESC'
);

$hoje = db_linha(
    "SELECT SUM(evento = 'login') AS logins, SUM(evento = 'falha') AS falhas,
            COUNT(DISTINCT CASE WHEN evento = 'login' THEN usuario_id END) AS usuarios
     FROM logs_acesso WHERE criado_em >= CURDATE()"
);
$opcoesUsuario = [];
foreach (usuarios_para_filtro() as $u) {
    $opcoesUsuario[$u['id']] = "{$u['nome']} ({$u['login']})";
}

render_header('Logs de acesso', 'logs');
?>
<div class="mb-4 grid gap-3 sm:grid-cols-3">
    <?= cartao_indicador('Logins hoje', (int) $hoje['logins']) ?>
    <?= cartao_indicador('Usuários que entraram hoje', (int) $hoje['usuarios']) ?>
    <?= cartao_indicador('Falhas de login hoje', (int) $hoje['falhas'], (int) $hoje['falhas'] > 0) ?>
</div>

<form method="get" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5" role="search">
    <div class="lg:col-span-2">
        <label for="usuario" class="label">Usuário</label>
        <select id="usuario" name="usuario" class="input">
            <option value="">Todos</option>
            <?= opcoes($opcoesUsuario, $usuarioId) ?>
        </select>
    </div>
    <div>
        <label for="evento" class="label">Evento</label>
        <select id="evento" name="evento" class="input">
            <option value="">Todos</option>
            <?= opcoes(array_map(fn ($e) => $e[0], EVENTOS_ACESSO), $evento) ?>
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
    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
        <button type="submit" class="btn btn-dark"><?= icon('search', 'size-4') ?> Filtrar</button>
        <a href="<?= e(url('logs.php')) ?>" class="btn btn-secondary">Limpar</a>
        <span class="ml-auto self-center text-sm text-muted-foreground"><?= $resultado->total ?> registro(s)</span>
    </div>
</form>

<div class="card overflow-hidden">
    <?php if (!$resultado->linhas): ?>
        <p class="p-6 text-sm text-muted-foreground">Nenhum acesso registrado<?= $filtro->ativo() ? ' com esses filtros' : '' ?>.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Data e hora</th>
                        <th>Usuário</th>
                        <th>Evento</th>
                        <th>Computador (IP)</th>
                        <th>Navegador</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($resultado->linhas as $l): [$rotulo, $classes] = EVENTOS_ACESSO[$l['evento']]; ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 whitespace-nowrap"><?= e(data_hora($l['criado_em'], true)) ?></td>
                            <td class="px-4 py-2">
                                <?php if ($l['usuario_nome']): ?>
                                    <span class="font-medium"><?= e($l['usuario_nome']) ?></span>
                                    <span class="block text-xs text-muted-foreground"><?= e($l['login']) ?> · <?= e(SETORES[$l['setor']] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="font-mono"><?= e($l['login']) ?></span>
                                    <span class="block text-xs text-muted-foreground"><?= $l['detalhe'] === 'Usuário inexistente' ? 'não cadastrado' : 'usuário excluído' ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold ring-1 ring-inset <?= $classes ?>"><?= e($rotulo) ?></span>
                                <?php if ($l['detalhe']): ?><span class="block text-xs text-muted-foreground"><?= e($l['detalhe']) ?></span><?php endif; ?>
                            </td>
                            <td class="px-4 py-2 font-mono whitespace-nowrap"><?= e(ip_legivel($l['ip'])) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap" title="<?= e($l['navegador'] ?? '') ?>"><?= e(navegador_resumo($l['navegador'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?php render_paginacao($resultado, true); ?>
</div>
<?php render_footer();
