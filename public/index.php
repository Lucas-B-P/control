<?php

require __DIR__ . '/../src/bootstrap.php';

$logado = exigir_login();
if ($logado['setor'] === 'erp' && !$logado['admin']) {
    redirect('consulta.php');
}
exigir_setor('hardware');

$contagens = manutencoes_por_status();
$ultimas = movimentacoes_recentes(12);

$cartoes = [];
foreach (status_todos() as $s) {
    if (($s['ativo'] && !$s['encerra']) || $s['sistema'] === STATUS_ENVIADO_ERP) {
        $cartoes[] = [$s['cor'], $s['nome'], (int) ($contagens[$s['id']] ?? 0), 'manutencoes/index.php?status=' . $s['id']];
    }
}
$cartoes[] = ['emerald', 'Encerradas hoje', manutencoes_encerradas_hoje(), 'manutencoes/index.php'];

$graficos = [
    'situacao' => ['Situação atual', 'Onde estão as OS agora, por status.', grafico_rosca(grafico_dados_situacao($contagens))],
    'evolucao' => ['Evolução', 'OS abertas x encerradas por dia nos últimos 14 dias: o setor está dando conta da demanda?', grafico_linhas(grafico_dados_evolucao())],
    'tempo'    => ['Tempo em aberto', 'Há quanto tempo as OS em andamento estão abertas: quais estão atrasando?', grafico_tempo(grafico_dados_tempo())],
];

render_header('Dashboard', 'dashboard');
?>
<div class="mb-6 flex flex-wrap gap-2">
    <a href="<?= e(url('manutencoes/nova.php')) ?>" class="btn btn-primary"><?= icon('plus', 'size-4') ?> Abrir OS</a>
    <a href="<?= e(url('equipamentos/index.php')) ?>" class="btn btn-secondary"><?= icon('monitor', 'size-4') ?> Equipamentos</a>
</div>

<ul class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
    <?php foreach ($cartoes as [$cor, $rotulo, $valor, $href]): ?>
        <li>
            <a href="<?= e(url($href)) ?>" class="card block h-full p-4 transition-colors duration-150 hover:border-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                <span class="flex items-center gap-2 text-sm text-muted-foreground">
                    <span class="size-2 rounded-full <?= e(status_classe_texto($cor)) ?> bg-current" aria-hidden="true"></span>
                    <?= e($rotulo) ?>
                </span>
                <span class="mt-2 block font-heading text-3xl font-bold tabular-nums"><?= $valor ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<section class="card mb-6 p-4" aria-labelledby="t-graficos">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="t-graficos" class="text-base font-semibold">Indicadores</h2>
        <div class="inline-flex rounded-md border border-border bg-muted p-1" role="tablist" aria-label="Tipo de gráfico">
            <?php foreach ($graficos as $chave => [$rotulo]): ?>
                <button type="button" role="tab" id="aba-<?= $chave ?>" aria-controls="painel-<?= $chave ?>" data-grafico="<?= $chave ?>"
                        class="rounded px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors duration-150 hover:text-foreground aria-selected:bg-white aria-selected:text-primary aria-selected:shadow-sm">
                    <?= e($rotulo) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php foreach ($graficos as $chave => [$rotulo, $descricao, $html]): ?>
        <div role="tabpanel" id="painel-<?= $chave ?>" aria-labelledby="aba-<?= $chave ?>" data-painel="<?= $chave ?>" hidden>
            <p class="mb-4 text-sm text-muted-foreground"><?= e($descricao) ?></p>
            <?= $html ?>
        </div>
    <?php endforeach; ?>
</section>

<script>
(function () {
    var abas = document.querySelectorAll('[data-grafico]');
    function mostrar(chave) {
        abas.forEach(function (aba) {
            var ativa = aba.getAttribute('data-grafico') === chave;
            aba.setAttribute('aria-selected', ativa ? 'true' : 'false');
            aba.tabIndex = ativa ? 0 : -1;
            document.getElementById('painel-' + aba.getAttribute('data-grafico')).hidden = !ativa;
        });
        try { localStorage.setItem('control_grafico', chave); } catch (e) {}
    }
    abas.forEach(function (aba, i) {
        aba.addEventListener('click', function () { mostrar(aba.getAttribute('data-grafico')); });
        aba.addEventListener('keydown', function (ev) {
            if (ev.key !== 'ArrowRight' && ev.key !== 'ArrowLeft') return;
            var proxima = abas[(i + (ev.key === 'ArrowRight' ? 1 : abas.length - 1)) % abas.length];
            proxima.focus();
            mostrar(proxima.getAttribute('data-grafico'));
        });
    });
    var salvo = null;
    try { salvo = localStorage.getItem('control_grafico'); } catch (e) {}
    mostrar(document.getElementById('painel-' + salvo) ? salvo : abas[0].getAttribute('data-grafico'));
})();
</script>

<section class="card overflow-hidden" aria-labelledby="t-ultimas">
    <h2 id="t-ultimas" class="p-4 pb-2 text-base font-semibold">Últimas movimentações</h2>
    <?php if (!$ultimas): ?>
        <p class="px-4 pb-4 text-sm text-muted-foreground">Nenhuma movimentação ainda. Comece <a class="link" href="<?= e(url('manutencoes/nova.php')) ?>">abrindo uma OS</a>.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="tabela">
                <thead class="border-t">
                    <tr>
                        <th>Quando</th>
                        <th>Código</th>
                        <th>Equipamento</th>
                        <th>Movimentação</th>
                        <th>Por</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <?php foreach ($ultimas as $mv): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2 whitespace-nowrap text-muted-foreground"><?= e(data_hora($mv['criado_em'])) ?></td>
                            <td class="px-4 py-2 whitespace-nowrap"><a class="link font-mono" href="<?= e(url('manutencoes/ver.php?id=' . $mv['manutencao_id'])) ?>"><?= e($mv['codigo']) ?></a></td>
                            <td class="max-w-[14rem] px-4 py-2"><span class="block truncate"><?= e(equipamento_descricao($mv)) ?></span></td>
                            <td class="px-4 py-2">
                                <?= $mv['status_anterior_id'] !== $mv['status_novo_id'] ? status_badge((int) $mv['status_novo_id']) : '<span class="text-muted-foreground">Observação</span>' ?>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap"><?= e($mv['usuario_nome']) ?> <span class="text-xs text-muted-foreground">(<?= e(SETORES[$mv['setor']]) ?>)</span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php render_footer();
