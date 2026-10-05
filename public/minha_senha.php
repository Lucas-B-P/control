<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_login();
$erros = [];

if (is_post()) {
    csrf_verificar();
    $atual = (string) ($_POST['senha_atual'] ?? '');
    $nova = (string) ($_POST['senha_nova'] ?? '');
    $confirmacao = (string) ($_POST['senha_confirmacao'] ?? '');

    if (!password_verify($atual, (string) db_valor('SELECT senha_hash FROM usuarios WHERE id = ?', [$usuario['id']]))) {
        $erros['senha_atual'] = 'Senha atual incorreta.';
    }
    if ($erro = senha_erro($nova, $usuario['login'])) {
        $erros['senha_nova'] = $erro;
    } elseif (hash_equals($atual, $nova)) {
        $erros['senha_nova'] = 'A nova senha precisa ser diferente da atual.';
    }
    if ($nova !== $confirmacao) {
        $erros['senha_confirmacao'] = 'A confirmação não confere com a nova senha.';
    }

    if (!$erros) {
        db_executar('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $usuario['id']]);
        sessoes_invalidar((int) $usuario['id']);
        session_regenerate_id(true);
        auditar('usuario', 'Senha alterada pelo próprio usuário', 'Usuário ' . $usuario['nome'] . ' (' . $usuario['login'] . ') alterou a própria senha', (int) $usuario['id']);
        flash('sucesso', 'Senha alterada. As sessões abertas em outros computadores foram encerradas.');
        redirect(pagina_inicial());
    }
}

render_header('Alterar minha senha', '');
?>
<form method="post" class="card max-w-md space-y-4 p-4 sm:p-6" novalidate>
    <?= csrf_field() ?>
    <div>
        <label for="senha_atual" class="label">Senha atual</label>
        <input id="senha_atual" name="senha_atual" type="password" class="input" autocomplete="current-password" required<?= aria_erro($erros, 'senha_atual') ?>>
        <?= campo_erro($erros, 'senha_atual') ?>
    </div>
    <div>
        <label for="senha_nova" class="label">Nova senha</label>
        <input id="senha_nova" name="senha_nova" type="password" class="input" minlength="8" autocomplete="new-password" required<?= aria_erro($erros, 'senha_nova') ?>>
        <?= campo_erro($erros, 'senha_nova') ?>
        <p class="mt-1 text-xs text-muted-foreground">Mínimo de 8 caracteres, com letras e números, sem conter o seu login.</p>
    </div>
    <div>
        <label for="senha_confirmacao" class="label">Confirme a nova senha</label>
        <input id="senha_confirmacao" name="senha_confirmacao" type="password" class="input" minlength="8" autocomplete="new-password" required<?= aria_erro($erros, 'senha_confirmacao') ?>>
        <?= campo_erro($erros, 'senha_confirmacao') ?>
    </div>
    <button type="submit" class="btn btn-primary w-full"><?= icon('check', 'size-4') ?> Alterar senha</button>
</form>
<?php render_footer();
