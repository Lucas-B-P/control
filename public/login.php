<?php

require __DIR__ . '/../src/bootstrap.php';

if (usuario_atual()) {
    redirect(pagina_inicial());
}

$erro = '';
$login = '';
$aviso = $_SESSION['aviso_login'] ?? '';
unset($_SESSION['aviso_login']);

if (is_post()) {
    csrf_verificar();
    $login = input('login');
    $erro = tentar_login($login, (string) ($_POST['senha'] ?? '')) ?? '';
    if ($erro === '') {
        // Login certo: tela de carregamento por 2 s e depois entra (replace evita reenviar o formulário ao voltar).
        $destino = url(pagina_inicial());
        render_head('Entrando');
        ?>
<body class="bg-white">
    <meta http-equiv="refresh" content="3;url=<?= e($destino) ?>">
    <div class="flex min-h-screen items-center justify-center" role="status" aria-label="Entrando no sistema">
        <img src="<?= e(asset('loading.svg')) ?>" alt="" class="h-auto w-32">
    </div>
    <script>setTimeout(function () { location.replace(<?= json_encode($destino, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); }, 2000);</script>
</body>
</html>
        <?php
        exit;
    }
}

render_header('Entrar', '');
?>
<div class="flex min-h-screen items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex flex-col items-center gap-2">
            <img src="<?= e(asset('logo.png')) ?>" alt="" width="96" height="96" class="size-24">
            <span class="font-heading text-xl font-bold text-primary"><?= e(app_config('nome')) ?></span>
        </div>
        <form method="post" class="card space-y-4 p-6" novalidate>
            <?= csrf_field() ?>
            <div>
                <h1 class="text-xl font-semibold">Entrar</h1>
                <p class="text-sm text-muted-foreground">Rastreio de manutenção · Hardware e Automação</p>
            </div>
            <?php if ($aviso && !$erro): ?>
                <div role="status" class="flex items-center gap-2 rounded-md border border-sky-300 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                    <?= icon('alert', 'size-4') ?> <?= e($aviso) ?>
                </div>
            <?php endif; ?>
            <?php if ($erro): ?>
                <div role="alert" class="flex items-center gap-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
                    <?= icon('alert', 'size-4') ?> <?= e($erro) ?>
                </div>
            <?php endif; ?>
            <div>
                <label for="login" class="label">Login</label>
                <input id="login" name="login" class="input" value="<?= e($login) ?>" autocomplete="username" required autofocus>
            </div>
            <div>
                <label for="senha" class="label">Senha</label>
                <input id="senha" name="senha" type="password" class="input" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-dark w-full">Entrar</button>
        </form>
    </div>
</div>
<?php render_footer();
