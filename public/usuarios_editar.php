<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_admin();
$id = input_int('id');
$editado = usuario_por_id($id) ?? nao_encontrado('Usuário não encontrado.');
$voce = $id === (int) $usuario['id'];
$erros = [];
$dados = $editado;

if (is_post()) {
    csrf_verificar();
    $senha = (string) ($_POST['senha'] ?? '');
    $dados = usuario_dados_formulario() + ['ativo' => $voce || !empty($_POST['ativo']) ? 1 : 0];
    $erros = usuario_validar($dados, $senha, $id);

    if (!$erros) {
        usuario_atualizar($editado, $dados, $senha);
        flash('sucesso', 'Usuário ' . $dados['nome'] . ' atualizado.');
        redirect($voce && !$dados['admin'] ? pagina_inicial() : 'usuarios.php');
    }
}

render_header('Editar usuário', 'usuarios');
?>
<a href="<?= e(url('usuarios.php')) ?>" class="link mb-4 inline-block text-sm">&larr; Voltar para usuários</a>

<form method="post" class="card max-w-xl space-y-4 p-4 sm:p-6" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <h2 class="text-base font-semibold"><?= e($editado['nome']) ?><?= $voce ? ' <span class="text-xs font-normal text-muted-foreground">(você)</span>' : '' ?></h2>

    <div>
        <label for="nome" class="label">Nome</label>
        <input id="nome" name="nome" class="input" value="<?= e($dados['nome']) ?>" maxlength="100" required<?= aria_erro($erros, 'nome') ?>>
        <?= campo_erro($erros, 'nome') ?>
    </div>
    <div>
        <label for="login" class="label">Nome de usuário (login)</label>
        <input id="login" name="login" class="input" value="<?= e($dados['login']) ?>" maxlength="50" autocomplete="off" required<?= aria_erro($erros, 'login') ?>>
        <?= campo_erro($erros, 'login') ?>
    </div>
    <div>
        <label for="senha" class="label">Nova senha <span class="font-normal text-muted-foreground">(deixe em branco para manter a atual)</span></label>
        <input id="senha" name="senha" type="password" class="input" minlength="8" autocomplete="new-password"<?= aria_erro($erros, 'senha') ?>>
        <?= campo_erro($erros, 'senha') ?>
    </div>
    <fieldset>
        <legend class="label">Setor</legend>
        <div class="space-y-1">
            <?php foreach (SETORES as $valor => $rotulo): ?>
                <label class="flex h-9 cursor-pointer items-center gap-2 text-sm">
                    <input type="radio" name="setor" value="<?= e($valor) ?>" class="size-4 accent-primary" <?= $dados['setor'] === $valor ? 'checked' : '' ?>>
                    <?= e($rotulo) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= campo_erro($erros, 'setor') ?>
    </fieldset>
    <div class="space-y-2">
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" name="admin" value="1" class="size-4 accent-primary" <?= $dados['admin'] ? 'checked' : '' ?>>
            Administrador
        </label>
        <label class="flex items-center gap-2 text-sm <?= $voce ? 'cursor-not-allowed text-muted-foreground' : 'cursor-pointer' ?>">
            <input type="checkbox" name="ativo" value="1" class="size-4 accent-primary" <?= $dados['ativo'] ? 'checked' : '' ?> <?= $voce ? 'disabled' : '' ?>>
            Ativo (pode entrar no sistema)<?= $voce ? ' — você não pode desativar o próprio usuário' : '' ?>
        </label>
        <?= campo_erro($erros, 'admin') ?>
    </div>
    <div class="flex gap-2 border-t border-border pt-4">
        <button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?> Salvar alterações</button>
        <a href="<?= e(url('usuarios.php')) ?>" class="btn btn-secondary">Cancelar</a>
    </div>
</form>
<?php render_footer();
