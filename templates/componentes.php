<?php

declare(strict_types=1);

/** @param array<string|int, string|int> $opcoes valor => rótulo */
function opcoes(array $opcoes, string|int|null $selecionado): string
{
    $html = '';
    foreach ($opcoes as $valor => $rotulo) {
        $marcado = (string) $valor === (string) $selecionado ? ' selected' : '';
        $html .= '<option value="' . e((string) $valor) . '"' . $marcado . '>' . e((string) $rotulo) . '</option>';
    }

    return $html;
}

/** Lista simples (valor igual ao rótulo) no formato aceito por opcoes(). */
function opcoes_lista(array $valores): array
{
    return array_combine($valores, $valores) ?: [];
}

function select_cor(string $id, string $atual, string $classe = 'input'): string
{
    return '<select id="' . e($id) . '" name="cor" class="' . e($classe) . '">'
        . opcoes(array_map(fn ($cor) => $cor[0], CORES_STATUS), $atual)
        . '</select>';
}

function selo(string $texto, string $titulo = ''): string
{
    return '<span class="selo"' . ($titulo !== '' ? ' title="' . e($titulo) . '"' : '') . '>' . e($texto) . '</span>';
}

function cartao_indicador(string $rotulo, int $valor, bool $alerta = false): string
{
    return '<div class="card p-4' . ($alerta ? ' border-red-300' : '') . '">'
        . '<p class="text-sm text-muted-foreground">' . e($rotulo) . '</p>'
        . '<p class="font-heading text-2xl font-bold' . ($alerta ? ' text-red-700' : '') . '">' . $valor . '</p>'
        . '</div>';
}

function campo_erro(array $erros, string $campo): string
{
    if (empty($erros[$campo])) {
        return '';
    }

    return '<p id="erro-' . e($campo) . '" class="mt-1 text-sm text-red-700">' . e($erros[$campo]) . '</p>';
}

function aria_erro(array $erros, string $campo): string
{
    return empty($erros[$campo]) ? '' : ' aria-invalid="true" aria-describedby="erro-' . e($campo) . '"';
}

/** Campos ocultos com os filtros atuais, para formulários GET que não podem perdê-los. */
function campos_filtro(array $ignorar): string
{
    $html = '';
    foreach ($_GET as $chave => $valor) {
        if (is_string($valor) && $valor !== '' && !in_array($chave, $ignorar, true)) {
            $html .= '<input type="hidden" name="' . e($chave) . '" value="' . e($valor) . '">';
        }
    }

    return $html;
}

/** Primeira, última e duas vizinhas da atual; null marca o intervalo omitido ("…"). */
function paginas_visiveis(int $atual, int $ultima): array
{
    $numeros = [];
    foreach (range(1, $ultima) as $p) {
        if ($p === 1 || $p === $ultima || abs($p - $atual) <= 2) {
            if ($numeros && $p - end($numeros) > 1) {
                $numeros[] = null;
            }
            $numeros[] = $p;
        }
    }

    return $numeros;
}

/** Com $escolherQuantidade, mostra sempre a barra com o seletor de itens por página e o campo "Ir para". */
function render_paginacao(ResultadoPaginado $resultado, bool $escolherQuantidade = false): void
{
    $pagina = $resultado->pagina;
    $porPagina = $resultado->porPagina;
    $total = $resultado->total;
    $paginas = $resultado->paginas();
    if ($paginas <= 1 && !$escolherQuantidade) {
        return;
    }
    $link = fn (int $p): string => '?' . http_build_query(['pagina' => $p] + $_GET);
    $inicio = $total ? ($pagina - 1) * $porPagina + 1 : 0;
    $fim = min($total, $pagina * $porPagina);
    $seta = fn (bool $ativa, int $destino, string $simbolo, string $titulo): string => $ativa
        ? '<a class="pagina" href="' . e($link($destino)) . '" title="' . $titulo . '" aria-label="' . $titulo . '">' . $simbolo . '</a>'
        : '<span class="pagina pagina-desabilitada" aria-hidden="true">' . $simbolo . '</span>';
    ?>
    <nav class="flex flex-wrap items-center gap-x-4 gap-y-3 border-t border-border px-4 py-3 text-sm" aria-label="Paginação">
        <span class="text-muted-foreground">
            <?= $total ? "Mostrando <strong class=\"text-foreground tabular-nums\">{$inicio}–{$fim}</strong> de <strong class=\"text-foreground tabular-nums\">{$total}</strong>" : 'Nenhum registro' ?>
        </span>

        <?php if ($escolherQuantidade): ?>
            <form method="get" class="flex items-center gap-2">
                <?= campos_filtro(['pagina', 'por_pagina']) ?>
                <label for="por_pagina" class="text-muted-foreground">Por página</label>
                <select id="por_pagina" name="por_pagina" class="input h-8 w-auto py-0" onchange="this.form.submit()">
                    <?= opcoes(opcoes_lista(POR_PAGINA_OPCOES), $porPagina) ?>
                </select>
                <noscript><button type="submit" class="btn btn-secondary btn-sm">Aplicar</button></noscript>
            </form>
        <?php endif; ?>

        <?php if ($paginas > 1): ?>
            <ul class="flex flex-wrap items-center gap-1 sm:ml-auto">
                <li><?= $seta($pagina > 1, 1, '&laquo;', 'Primeira página') ?></li>
                <li><?= $seta($pagina > 1, $pagina - 1, '&lsaquo;', 'Página anterior') ?></li>
                <?php foreach (paginas_visiveis($pagina, $paginas) as $p): ?>
                    <li>
                        <?php if ($p === null): ?>
                            <span class="px-1 text-muted-foreground" aria-hidden="true">…</span>
                        <?php elseif ($p === $pagina): ?>
                            <span class="pagina pagina-atual" aria-current="page"><?= $p ?></span>
                        <?php else: ?>
                            <a class="pagina" href="<?= e($link($p)) ?>" aria-label="Página <?= $p ?>"><?= $p ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <li><?= $seta($pagina < $paginas, $pagina + 1, '&rsaquo;', 'Próxima página') ?></li>
                <li><?= $seta($pagina < $paginas, $paginas, '&raquo;', 'Última página') ?></li>
            </ul>
            <?php if ($escolherQuantidade && $paginas > 5): ?>
                <form method="get" class="flex items-center gap-2">
                    <?= campos_filtro(['pagina']) ?>
                    <label for="ir_pagina" class="text-muted-foreground">Ir para</label>
                    <input id="ir_pagina" name="pagina" type="number" min="1" max="<?= $paginas ?>" value="<?= $pagina ?>" class="input h-8 w-20 py-0" inputmode="numeric">
                    <button type="submit" class="btn btn-secondary btn-sm">Ir</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
    <?php
}

/** Caminho da página atual relativo à base do sistema, para voltar a ela depois de um POST. */
function pagina_atual_relativa(): string
{
    $base = rtrim(app_config('base_url'), '/') . '/';
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

    return str_starts_with($uri, $base) ? substr($uri, strlen($base)) : '';
}

function render_timeline(array $movimentacoes, bool $mostrarManutencao = false): void
{
    if (!$movimentacoes) {
        echo '<p class="text-sm text-muted-foreground">Nenhuma movimentação registrada.</p>';
        return;
    }
    $usuario = usuario_atual();
    $voltar = pagina_atual_relativa();
    $podeAbrirOs = e_setor('hardware') || e_admin();
    ?>
    <ol class="relative space-y-5 border-l-2 border-border pl-5">
        <?php foreach ($movimentacoes as $mv):
            $mudouStatus = $mv['status_anterior_id'] !== $mv['status_novo_id'];
            $mvId = (int) $mv['id'];
            $osId = (int) $mv['manutencao_id'];
        ?>
        <li class="relative">
            <span class="absolute top-1.5 -left-[27px] size-3 rounded-full border-2 border-white <?= $mudouStatus ? 'bg-primary' : 'bg-slate-400' ?>" aria-hidden="true"></span>
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($mudouStatus): ?>
                    <?= status_badge((int) $mv['status_novo_id']) ?>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-muted-foreground"><?= icon('message', 'size-3.5') ?> Observação</span>
                <?php endif; ?>
                <span class="rounded bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground uppercase"><?= e($mv['setor'] === 'erp' ? 'ERP' : 'Hardware') ?></span>
                <?php if ($mostrarManutencao && $podeAbrirOs): ?>
                    <a class="link text-xs" href="<?= e(url('manutencoes/ver.php?id=' . $osId)) ?>">OS #<?= $osId ?></a>
                <?php elseif ($mostrarManutencao): ?>
                    <span class="text-xs text-muted-foreground">OS #<?= $osId ?></span>
                <?php endif; ?>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                <time datetime="<?= e(date('c', strtotime($mv['criado_em']))) ?>"><?= e(data_hora($mv['criado_em'])) ?></time>
                · <?= e($mv['usuario_nome']) ?>
            </p>
            <?php if ($mv['observacao']): ?>
                <p class="mt-1 text-sm whitespace-pre-line"><?= e($mv['observacao']) ?></p>
                <?php if ($mv['editado_em']): ?>
                    <p class="mt-0.5 text-xs text-muted-foreground italic">
                        Editada<?= $mv['editado_por_nome'] ? ' por ' . e($mv['editado_por_nome']) : '' ?> em <?= e(data_hora($mv['editado_em'])) ?>
                    </p>
                <?php endif; ?>
                <?php if (observacao_pode_editar($mv, $usuario)): ?>
                    <details class="mt-1 group">
                        <summary class="inline-flex cursor-pointer items-center gap-1 text-xs font-medium text-primary hover:underline group-open:hidden">
                            <?= icon('pencil', 'size-3') ?> Editar
                        </summary>
                        <form method="post" action="<?= e(url('observacao_editar.php')) ?>" class="mt-2 space-y-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $mvId ?>">
                            <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
                            <label for="obs-editar-<?= $mvId ?>" class="sr-only">Editar observação</label>
                            <textarea id="obs-editar-<?= $mvId ?>" name="observacao" rows="3" maxlength="2000" class="input text-sm" required><?= e($mv['observacao']) ?></textarea>
                            <p class="text-xs text-muted-foreground">A alteração fica registrada nos logs do sistema, com o texto anterior.</p>
                            <div class="flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 'size-3.5') ?> Salvar</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="this.closest('details').open = false">Cancelar</button>
                            </div>
                        </form>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php
}

/** Aviso único pós-login (atualizações não vistas) ou, sem ele, o popup da atualização em destaque. */
function render_aviso_atualizacoes(): void
{
    $ctx = layout_contexto();
    $usuario = usuario_atual();
    if (!$usuario || ($ctx['ativo'] ?? '') === 'atualizacoes') {
        return;
    }

    $naoVistas = $ctx['nao_vistas'] ?? [];
    if ($naoVistas) {
        atualizacoes_marcar_vistas((int) $usuario['id'], array_column($naoVistas, 'id'));
        render_modal_atualizacoes($naoVistas);
    } elseif (!empty($ctx['destaque'])) {
        render_popup_atualizacao($ctx['destaque']);
    }
}

function render_modal_atualizacoes(array $naoVistas): void
{
    $mostrar = array_slice($naoVistas, 0, 3);
    $restantes = count($naoVistas) - count($mostrar);
    ?>
<dialog id="aviso-atualizacao" class="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-xl border border-border bg-white p-0 shadow-2xl backdrop:bg-slate-900/50" aria-labelledby="t-aviso-atualizacao">
    <div class="flex items-center gap-3 border-b border-border bg-primary px-5 py-4 text-white">
        <?= icon('megaphone', 'size-5') ?>
        <h2 id="t-aviso-atualizacao" class="text-lg font-semibold">Novidades no sistema</h2>
    </div>
    <div class="max-h-[60vh] space-y-4 overflow-y-auto px-5 py-4">
        <?php foreach ($mostrar as $a): ?>
            <div>
                <p class="flex flex-wrap items-center gap-2 font-semibold">
                    <?= e($a['titulo']) ?>
                    <?php if ($a['versao']): ?><?= selo('v' . $a['versao']) ?><?php endif; ?>
                </p>
                <p class="mt-1 text-sm text-muted-foreground"><?= e($a['resumo']) ?></p>
                <a class="link mt-1 inline-block text-sm" href="<?= e(url('atualizacoes.php#atualizacao-' . (int) $a['id'])) ?>">Ver detalhes &rarr;</a>
            </div>
        <?php endforeach; ?>
        <?php if ($restantes > 0): ?>
            <p class="text-sm text-muted-foreground">E mais <?= $restantes ?> atualização(ões) em Novidades.</p>
        <?php endif; ?>
    </div>
    <form method="dialog" class="flex flex-wrap justify-end gap-2 border-t border-border px-5 py-3">
        <a href="<?= e(url('atualizacoes.php')) ?>" class="btn btn-secondary">Ver todas as novidades</a>
        <button type="submit" class="btn btn-primary" autofocus>Entendi</button>
    </form>
</dialog>
<script>
(function () {
    var d = document.getElementById('aviso-atualizacao');
    if (d.showModal) { d.showModal(); } else { d.setAttribute('open', ''); }
})();
</script>
    <?php
}

function render_popup_atualizacao(array $destaque): void
{
    // A chave muda quando a atualização é reanunciada, para o popup voltar a aparecer.
    $chave = 'control_popup_' . (int) $destaque['id'] . '_' . strtotime((string) $destaque['publicado_em']);
    ?>
<aside id="popup-atualizacao" class="fixed right-4 bottom-4 z-50 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-primary/30 bg-white p-4 shadow-xl" aria-labelledby="t-popup-atualizacao" hidden>
    <div class="flex items-start gap-3">
        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"><?= icon('megaphone', 'size-4') ?></span>
        <div class="min-w-0 flex-1">
            <p id="t-popup-atualizacao" class="text-sm font-semibold">Nova atualização<?= $destaque['versao'] ? ' · v' . e($destaque['versao']) : '' ?></p>
            <p class="mt-0.5 text-sm font-medium"><?= e($destaque['titulo']) ?></p>
            <p class="mt-1 line-clamp-2 text-xs text-muted-foreground"><?= e($destaque['resumo']) ?></p>
            <a class="link mt-2 inline-block text-sm" href="<?= e(url('atualizacoes.php#atualizacao-' . (int) $destaque['id'])) ?>">Ver o que mudou &rarr;</a>
        </div>
        <button type="button" data-fechar-popup class="inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring" aria-label="Fechar aviso">
            <?= icon('x', 'size-4') ?>
        </button>
    </div>
</aside>
<script>
(function () {
    var p = document.getElementById('popup-atualizacao');
    var chave = <?= json_encode($chave) ?>;
    var fechado = false;
    try { fechado = sessionStorage.getItem(chave) === '1'; } catch (e) {}
    if (!fechado) { p.hidden = false; }
    p.querySelector('[data-fechar-popup]').addEventListener('click', function () {
        p.hidden = true;
        try { sessionStorage.setItem(chave, '1'); } catch (e) {}
    });
})();
</script>
    <?php
}
