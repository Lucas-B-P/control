<?php

declare(strict_types=1);

/** Itens de menu: [arquivo, chave da página ativa, rótulo, ícone]. */
const MENU_HARDWARE = [
    ['index.php', 'dashboard', 'Dashboard', 'dashboard'],
    ['manutencoes/nova.php', 'nova', 'Abrir OS', 'plus'],
    ['manutencoes/index.php', 'manutencoes', 'Ordens de serviço', 'wrench'],
    ['equipamentos/index.php', 'equipamentos', 'Equipamentos', 'monitor'],
];

const MENU_ERP = [
    ['consulta.php', 'consulta', 'Confirmar chegada', 'barcode'],
];

const MENU_ADMIN = [
    ['usuarios.php', 'usuarios', 'Usuários e setores', 'users'],
    ['status.php', 'status', 'Status', 'list'],
    ['logs.php', 'logs', 'Logs de acesso', 'history'],
    ['logs_sistema.php', 'logs_sistema', 'Logs do sistema', 'file-text'],
    ['atualizacoes_admin.php', 'atualizacoes_admin', 'Anunciar atualizações', 'megaphone'],
    ['backup.php', 'backup', 'Backup', 'database'],
    ['configuracoes.php', 'configuracoes', 'Configurações', 'settings'],
];

function render_head(string $titulo): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · <?= e(app_config('nome')) ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('favicon-32x32.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('apple-touch-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@500;600;700&family=Noto+Sans:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
    <?php
}

function menu_principal(array $usuario): array
{
    return match (true) {
        (bool) $usuario['admin'] => [...MENU_HARDWARE, ...MENU_ERP],
        $usuario['setor'] === 'hardware' => MENU_HARDWARE,
        $usuario['setor'] === 'erp' => MENU_ERP,
        default => [],
    };
}

function render_header(string $titulo, string $ativo): void
{
    $usuario = usuario_atual();
    if ($usuario) {
        layout_contexto([
            'ativo'      => $ativo,
            'nao_vistas' => atualizacoes_nao_vistas((int) $usuario['id']),
            'destaque'   => atualizacao_em_destaque(),
        ]);
    }
    render_head($titulo);
    ?>
<body class="min-h-screen">
<a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">Pular para o conteúdo</a>

<?php if ($usuario): ?>
<?php render_menu_lateral($usuario, $ativo); ?>
<div data-menu-fundo class="fixed inset-0 z-30 hidden bg-slate-900/50 lg:hidden"></div>

<div class="lg:pl-60">
    <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-border bg-white px-4 lg:px-6">
        <button type="button" data-menu-abrir class="inline-flex size-10 cursor-pointer items-center justify-center rounded-md text-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring lg:hidden" aria-label="Abrir menu" aria-controls="menu-lateral" aria-expanded="false">
            <?= icon('menu') ?>
        </button>
        <h1 class="truncate text-lg font-semibold"><?= e($titulo) ?></h1>
        <form method="get" action="<?= e(url('buscar.php')) ?>" class="ml-auto hidden w-72 md:block" role="search">
            <label for="busca-topo" class="sr-only">Consultar código</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-500"><?= icon('barcode', 'size-4') ?></span>
                <input id="busca-topo" name="codigo" type="search" class="input h-9 pl-9 text-sm" placeholder="Bipar ou digitar código">
            </div>
        </form>
    </header>
    <main id="conteudo" class="mx-auto max-w-7xl p-4 lg:p-6">
        <?php render_flash(); ?>
<?php else: ?>
<main id="conteudo">
<?php endif; ?>
    <?php
}

function render_menu_lateral(array $usuario, string $ativo): void
{
    $novidades = count(layout_contexto()['nao_vistas'] ?? []);
    ?>
<aside id="menu-lateral" class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col max-lg:invisible bg-primary text-white transition-transform duration-200 lg:translate-x-0" aria-label="Menu principal">
    <div class="flex h-14 items-center gap-2 border-b border-border bg-white px-4">
        <a href="<?= e(url(pagina_inicial())) ?>" class="flex items-center gap-2 rounded-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
            <img src="<?= e(asset('logo.png')) ?>" alt="" width="36" height="36" class="size-9">
            <span class="font-heading text-base font-bold text-primary"><?= e(app_config('nome')) ?></span>
        </a>
        <button type="button" data-menu-fechar class="ml-auto inline-flex size-10 cursor-pointer items-center justify-center rounded-md text-primary hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring lg:hidden" aria-label="Fechar menu">
            <?= icon('x') ?>
        </button>
    </div>
    <nav class="flex-1 overflow-y-auto px-2 py-3">
        <ul class="space-y-1">
            <?php foreach (menu_principal($usuario) as [$href, $chave, $rotulo, $icone]): ?>
                <li><?= menu_item($href, $chave, $rotulo, $icone, $ativo) ?></li>
            <?php endforeach; ?>
            <li><?= menu_item('atualizacoes.php', 'atualizacoes', 'Novidades', 'bell', $ativo, $novidades) ?></li>
        </ul>
        <?php if ($usuario['admin']): ?>
            <p class="mt-6 mb-2 px-3 text-xs font-semibold tracking-wide text-white uppercase">Administração</p>
            <ul class="space-y-1">
                <?php foreach (MENU_ADMIN as [$href, $chave, $rotulo, $icone]): ?>
                    <li><?= menu_item($href, $chave, $rotulo, $icone, $ativo) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </nav>
    <div class="border-t border-white/25 p-3">
        <p class="truncate px-1 text-sm font-medium" title="<?= e($usuario['nome']) ?>"><?= e($usuario['nome']) ?></p>
        <p class="truncate px-1 text-xs text-white"><?= e(SETORES[$usuario['setor']] ?? '') ?><?= $usuario['admin'] ? ' · Admin' : '' ?></p>
        <a href="<?= e(url('minha_senha.php')) ?>" class="mt-2 flex h-9 items-center gap-2 rounded-md px-3 text-sm text-white transition-colors duration-150 hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-white">
            <?= icon('settings', 'size-4') ?> Alterar minha senha
        </a>
        <form method="post" action="<?= e(url('logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="flex h-10 w-full cursor-pointer items-center gap-2 rounded-md px-3 text-sm text-white transition-colors duration-150 hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-white">
                <?= icon('logout', 'size-4') ?> Sair
            </button>
        </form>
    </div>
</aside>
    <?php
}

function render_footer(): void
{
    $logado = usuario_atual() !== null;
    ?>
    </main>
<?php if ($logado): ?>
</div>
<?php render_aviso_atualizacoes(); ?>
<script>
(function () {
    var menu = document.getElementById('menu-lateral');
    var fundo = document.querySelector('[data-menu-fundo]');
    var abrir = document.querySelector('[data-menu-abrir]');
    function alternar(aberto) {
        menu.classList.toggle('-translate-x-full', !aberto);
        menu.classList.toggle('max-lg:invisible', !aberto);
        fundo.classList.toggle('hidden', !aberto);
        abrir.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    }
    abrir.addEventListener('click', function () { alternar(true); });
    fundo.addEventListener('click', function () { alternar(false); });
    document.querySelector('[data-menu-fechar]').addEventListener('click', function () { alternar(false); });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') alternar(false); });
})();
</script>
<?php endif; ?>
<script>
document.addEventListener('click', function (ev) {
    var alvo = ev.target.closest('[data-confirmar]');
    if (alvo && !window.confirm(alvo.getAttribute('data-confirmar'))) {
        ev.preventDefault();
    }
});
</script>
</body>
</html>
    <?php
}

function menu_item(string $href, string $chave, string $rotulo, string $icone, string $ativo, int $contador = 0): string
{
    $atual = $chave === $ativo;
    $classes = $atual ? 'bg-white text-primary font-semibold' : 'text-white hover:bg-white/15';
    $badge = $contador > 0
        ? '<span class="ml-auto rounded-full bg-amber-400 px-1.5 text-xs font-bold text-slate-900 tabular-nums" aria-label="' . $contador . ' não lida(s)">' . $contador . '</span>'
        : '';

    return '<a href="' . e(url($href)) . '" class="flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white ' . $classes . '"'
        . ($atual ? ' aria-current="page"' : '') . '>' . icon($icone, 'size-4') . e($rotulo) . $badge . '</a>';
}

/** Dados que o cabeçalho calcula e o rodapé reaproveita (aviso de atualizações). */
function layout_contexto(?array $definir = null): array
{
    static $contexto = [];
    if ($definir !== null) {
        $contexto = $definir;
    }

    return $contexto;
}

function render_flash(): void
{
    foreach (flash_pegar() as $f) {
        $erro = $f['tipo'] === 'erro';
        $estilo = match ($f['tipo']) {
            'sucesso' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
            'erro'    => 'border-red-300 bg-red-50 text-red-900',
            default   => 'border-sky-300 bg-sky-50 text-sky-900',
        };
        echo '<div role="' . ($erro ? 'alert' : 'status') . '" class="mb-4 flex items-start gap-2 rounded-md border px-4 py-3 text-sm ' . $estilo . '">'
            . icon($erro ? 'alert' : 'check', 'mt-0.5 size-4') . '<span>' . e($f['mensagem']) . '</span></div>';
    }
}
