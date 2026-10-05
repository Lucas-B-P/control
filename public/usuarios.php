<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_admin();
$erros = [];
$novo = ['nome' => '', 'login' => '', 'setor' => 'hardware', 'admin' => 0];

if (is_post()) {
    csrf_verificar();
    $acao = input('acao');

    if ($acao === 'criar') {
        $novo = usuario_dados_formulario();
        $senha = (string) ($_POST['senha'] ?? '');
        $erros = usuario_validar($novo, $senha);
        if (!$erros) {
            usuario_criar($novo, $senha);
            flash('sucesso', 'Usuário ' . $novo['nome'] . ' criado no setor ' . SETORES[$novo['setor']] . '.');
            redirect('usuarios.php');
        }
    } else {
        try {
            if ($acao === 'alternar') {
                usuario_alternar(input_int('id'), $usuario);
                flash('sucesso', 'Situação do usuário atualizada.');
            } elseif ($acao === 'excluir') {
                flash('sucesso', 'Usuário ' . usuario_excluir(input_int('id'), $usuario) . ' excluído.');
            }
        } catch (RuntimeException $ex) {
            flash('erro', $ex->getMessage());
        }
        redirect('usuarios.php');
    }
}

$usuarios = usuarios_listagem();

render_header('Usuários e setores', 'usuarios');
?>
<p class="mb-4 max-w-3xl text-sm text-muted-foreground">
    <strong class="text-foreground">Automação Hardware</strong> abre OS, altera status, registra peças e imprime etiquetas.
    <strong class="text-foreground">Automação ERP</strong> bipa o NS ou patrimônio e confirma a chegada do aparelho na ADM.
    Cada setor vê apenas as suas funções. <strong class="text-foreground">Administrador</strong> também gerencia usuários, status e configurações.
</p>

<div class="grid gap-6 xl:grid-cols-3">
    <section class="card overflow-hidden xl:col-span-2" aria-labelledby="t-lista">
        <h2 id="t-lista" class="p-4 pb-2 text-base font-semibold">Usuários cadastrados</h2>
        <ul class="divide-y divide-border border-t border-border">
            <?php foreach ($usuarios as $u): $uid = (int) $u['id']; $voce = $uid === (int) $usuario['id']; ?>
                <li class="p-4 <?= $u['ativo'] ? '' : 'bg-slate-50' ?>">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium"><?= e($u['nome']) ?></span>
                        <span class="font-mono text-sm text-muted-foreground"><?= e($u['login']) ?></span>
                        <?php if ($voce): ?><span class="text-xs text-muted-foreground">(você)</span><?php endif; ?>
                        <span class="rounded px-1.5 py-0.5 text-xs font-semibold <?= $u['setor'] === 'erp' ? 'bg-violet-50 text-violet-800' : 'bg-primary/10 text-primary' ?>"><?= e(SETORES[$u['setor']]) ?></span>
                        <?php if ($u['admin']): ?><span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs font-semibold text-white">Admin</span><?php endif; ?>
                        <?php if (!$u['ativo']): ?><span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs font-semibold text-slate-700">Inativo</span><?php endif; ?>
                        <a href="<?= e(url('logs.php?usuario=' . $uid)) ?>" class="link text-xs" title="Ver logs de acesso">Último acesso: <?= $u['ultimo_acesso'] ? e(data_hora($u['ultimo_acesso'])) : 'nunca' ?></a>
                        <div class="ml-auto flex gap-2">
                            <a href="<?= e(url('usuarios_editar.php?id=' . $uid)) ?>" class="btn btn-dark btn-sm"><?= icon('pencil', 'size-3.5') ?> Editar</a>
                            <?php if (!$voce): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="acao" value="alternar">
                                    <input type="hidden" name="id" value="<?= $uid ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm"><?= $u['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                                </form>
                                <?php if ((int) $u['registros'] === 0): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= $uid ?>">
                                        <button type="submit" class="btn btn-sm btn-danger-outline" data-confirmar="Excluir o usuário <?= e($u['nome']) ?> definitivamente?"><?= icon('trash', 'size-3.5') ?> Excluir</button>
                                    </form>
                                <?php else: ?>
                                    <span class="self-center text-xs text-muted-foreground" title="Usuários com histórico não podem ser excluídos, apenas desativados"><?= (int) $u['registros'] ?> registro(s) no histórico</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <form method="post" class="card h-fit space-y-4 p-4" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="criar">
        <h2 class="text-base font-semibold">Novo usuário</h2>
        <div>
            <label for="nome" class="label">Nome</label>
            <input id="nome" name="nome" class="input" value="<?= e($novo['nome']) ?>" maxlength="100" required<?= aria_erro($erros, 'nome') ?>>
            <?= campo_erro($erros, 'nome') ?>
        </div>
        <div>
            <label for="login" class="label">Login</label>
            <input id="login" name="login" class="input" value="<?= e($novo['login']) ?>" maxlength="50" autocomplete="off" required<?= aria_erro($erros, 'login') ?>>
            <?= campo_erro($erros, 'login') ?>
        </div>
        <fieldset>
            <legend class="label">Setor</legend>
            <div class="space-y-1">
                <?php foreach (SETORES as $valor => $rotulo): ?>
                    <label class="flex h-9 cursor-pointer items-center gap-2 text-sm">
                        <input type="radio" name="setor" value="<?= e($valor) ?>" class="size-4 accent-primary" <?= $novo['setor'] === $valor ? 'checked' : '' ?>>
                        <?= e($rotulo) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?= campo_erro($erros, 'setor') ?>
        </fieldset>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" name="admin" value="1" class="size-4 accent-primary" <?= $novo['admin'] ? 'checked' : '' ?>>
            Administrador
        </label>
        <div>
            <label for="senha" class="label">Senha</label>
            <input id="senha" name="senha" type="password" class="input" minlength="8" autocomplete="new-password" required<?= aria_erro($erros, 'senha') ?>>
            <?= campo_erro($erros, 'senha') ?>
        </div>
        <button type="submit" class="btn btn-primary w-full"><?= icon('plus', 'size-4') ?> Criar usuário</button>
    </form>
</div>
<?php render_footer();
