<?php

require __DIR__ . '/../src/bootstrap.php';

$usuario = exigir_admin();
$erros = [];
$editando = input_int('editar') ? atualizacao_por_id(input_int('editar')) : null;
$dados = $editando ?? ['id' => 0, 'titulo' => '', 'versao' => '', 'resumo' => '', 'conteudo' => '', 'publicado' => 0];

if (is_post()) {
    csrf_verificar();
    $acao = input('acao');
    $id = input_int('id');
    $atual = $id ? atualizacao_por_id($id) : null;

    if ($acao === 'salvar') {
        $dados = atualizacao_dados_formulario($id);
        $erros = atualizacao_validar($dados);
        $editando = $atual;
        if (!$erros) {
            if ($atual) {
                atualizacao_editar($atual, $dados);
                flash('sucesso', 'Atualização salva.');
            } else {
                atualizacao_criar($dados, (int) $usuario['id']);
                flash('sucesso', $dados['publicado'] ? 'Atualização publicada. Os usuários verão o aviso no próximo acesso.' : 'Rascunho salvo. Publique quando estiver pronto.');
            }
            redirect('atualizacoes_admin.php');
        }
    } else {
        if (!$atual) {
            flash('erro', 'Atualização não encontrada.');
            redirect('atualizacoes_admin.php');
        }
        $acoes = [
            'publicar'    => ['atualizacao_publicar', 'Atualização publicada.'],
            'despublicar' => ['atualizacao_despublicar', 'Atualização despublicada. Ela não aparece mais para os usuários.'],
            'reanunciar'  => ['atualizacao_reanunciar', 'Aviso reiniciado: todos verão a atualização de novo no próximo acesso.'],
            'excluir'     => ['atualizacao_excluir', 'Atualização excluída.'],
        ];
        if (isset($acoes[$acao])) {
            [$executar, $mensagem] = $acoes[$acao];
            $executar($atual);
            flash('sucesso', $mensagem);
        }
        redirect('atualizacoes_admin.php');
    }
}

$lista = atualizacoes_todas();
$ativos = (int) db_valor('SELECT COUNT(*) FROM usuarios WHERE ativo = 1');

render_header('Anunciar atualizações', 'atualizacoes_admin');
?>
<p class="mb-4 max-w-3xl text-sm text-muted-foreground">
    Ao publicar, cada usuário vê um aviso <strong class="text-foreground">uma única vez</strong> logo após entrar no sistema, e um popup com o link para
    <a class="link" href="<?= e(url('atualizacoes.php')) ?>">Novidades</a> fica visível por <strong class="text-foreground"><?= ATUALIZACAO_POPUP_HORAS ?> horas</strong>.
</p>

<div class="grid gap-6 xl:grid-cols-5">
    <form method="post" class="card space-y-4 p-4 sm:p-6 xl:col-span-3" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int) $dados['id'] ?>">
        <h2 class="text-base font-semibold"><?= $editando ? 'Editar atualização' : 'Nova atualização' ?></h2>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-3">
                <label for="titulo" class="label">Título</label>
                <input id="titulo" name="titulo" class="input" maxlength="150" value="<?= e($dados['titulo']) ?>" placeholder="Ex.: Backup do banco e paginação nos logs" required<?= aria_erro($erros, 'titulo') ?>>
                <?= campo_erro($erros, 'titulo') ?>
            </div>
            <div>
                <label for="versao" class="label">Versão <span class="font-normal text-muted-foreground">(opcional)</span></label>
                <input id="versao" name="versao" class="input" maxlength="30" value="<?= e((string) $dados['versao']) ?>" placeholder="Ex.: 1.4">
            </div>
        </div>
        <div>
            <label for="resumo" class="label">Resumo</label>
            <input id="resumo" name="resumo" class="input" maxlength="300" value="<?= e($dados['resumo']) ?>" placeholder="Uma frase: aparece no aviso após o login e no popup" required<?= aria_erro($erros, 'resumo') ?>>
            <?= campo_erro($erros, 'resumo') ?>
        </div>
        <div>
            <label for="conteudo" class="label">Descrição detalhada</label>
            <textarea id="conteudo" name="conteudo" rows="12" class="input font-mono text-sm" required<?= aria_erro($erros, 'conteudo') ?> placeholder="## O que mudou&#10;- Novo botão de backup no menu do administrador&#10;- Paginação com escolha de quantidade nos logs&#10;&#10;## Como usar&#10;Acesse Backup no menu e clique em Fazer backup agora."><?= e($dados['conteudo']) ?></textarea>
            <?= campo_erro($erros, 'conteudo') ?>
            <p class="mt-1 text-xs text-muted-foreground">
                Formatação: <span class="font-mono">## Título</span> para subtítulo, <span class="font-mono">- item</span> para lista e uma linha em branco para separar parágrafos.
            </p>
        </div>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="checkbox" name="publicado" value="1" class="size-4 accent-primary" <?= $dados['publicado'] ? 'checked' : '' ?>>
            Publicar agora (os usuários verão o aviso)
        </label>
        <div class="flex gap-2 border-t border-border pt-4">
            <button type="submit" class="btn btn-primary"><?= icon('check', 'size-4') ?> Salvar</button>
            <?php if ($editando): ?>
                <a href="<?= e(url('atualizacoes_admin.php')) ?>" class="btn btn-secondary">Cancelar edição</a>
            <?php endif; ?>
        </div>
    </form>

    <section class="card overflow-hidden xl:col-span-2" aria-labelledby="t-anuncios">
        <h2 id="t-anuncios" class="p-4 pb-2 text-base font-semibold">Anúncios</h2>
        <?php if (!$lista): ?>
            <p class="border-t border-border p-4 text-sm text-muted-foreground">Nenhuma atualização anunciada ainda.</p>
        <?php else: ?>
            <ul class="divide-y divide-border border-t border-border">
                <?php foreach ($lista as $a): $aid = (int) $a['id']; $emDestaque = $a['publicado'] && atualizacao_popup_ate($a) >= time(); ?>
                    <li class="space-y-2 p-4 <?= $a['publicado'] ? '' : 'bg-slate-50' ?>">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium"><?= e($a['titulo']) ?></span>
                            <?php if ($a['versao']): ?><?= selo('v' . $a['versao']) ?><?php endif; ?>
                            <?php if (!$a['publicado']): ?>
                                <span class="rounded bg-slate-200 px-1.5 py-0.5 text-xs font-semibold text-slate-700">Rascunho</span>
                            <?php elseif ($emDestaque): ?>
                                <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-300 ring-inset">Popup até <?= e(date('d/m H:i', atualizacao_popup_ate($a))) ?></span>
                            <?php else: ?>
                                <span class="rounded bg-sky-50 px-1.5 py-0.5 text-xs font-semibold text-sky-800">Publicada</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-muted-foreground"><?= e($a['resumo']) ?></p>
                        <p class="text-xs text-muted-foreground">
                            <?= $a['publicado_em'] ? 'Publicada em ' . e(data_hora($a['publicado_em'])) . ' · ' : '' ?>
                            Por <?= e($a['criado_por_nome'] ?? '-') ?>
                            <?php if ($a['publicado']): ?> · Vista por <?= (int) $a['vistas'] ?> de <?= $ativos ?> usuário(s)<?php endif; ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= e(url('atualizacoes_admin.php?editar=' . $aid)) ?>" class="btn btn-dark btn-sm"><?= icon('pencil', 'size-3.5') ?> Editar</a>
                            <form method="post" class="flex flex-wrap gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $aid ?>">
                                <?php if ($a['publicado']): ?>
                                    <button type="submit" name="acao" value="despublicar" class="btn btn-secondary btn-sm">Despublicar</button>
                                    <button type="submit" name="acao" value="reanunciar" class="btn btn-secondary btn-sm" data-confirmar="Mostrar o aviso desta atualização de novo para todos e reiniciar o popup de 24h?">Anunciar de novo</button>
                                <?php else: ?>
                                    <button type="submit" name="acao" value="publicar" class="btn btn-secondary btn-sm">Publicar</button>
                                <?php endif; ?>
                                <button type="submit" name="acao" value="excluir" class="btn btn-sm btn-danger-outline" data-confirmar="Excluir esta atualização definitivamente?"><?= icon('trash', 'size-3.5') ?> Excluir</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php render_footer();
