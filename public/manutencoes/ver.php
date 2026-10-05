<?php

require __DIR__ . '/../../src/bootstrap.php';

$usuario = exigir_setor('hardware');
$admin = (bool) $usuario['admin'];

$id = input_int('id');
$manutencao = manutencao_por_id($id) ?? nao_encontrado('OS não encontrada.');
$equipamento = equipamento_por_id((int) $manutencao['equipamento_id']);
$editavel = !manutencao_com_erp($manutencao);

if (is_post()) {
    csrf_verificar();
    $acao = input('acao');

    try {
        if (in_array($acao, ['reverter', 'desfazer', 'excluir'], true) && !$admin) {
            acesso_negado();
        }

        switch ($acao) {
            case 'desfazer':
                $ultima = manutencao_ultima_mudanca($manutencao) ?? throw new RuntimeException('Não há mudança de status para desfazer.');
                manutencao_forcar_status($manutencao, (int) $ultima['status_anterior_id'], $usuario, input('motivo'));
                flash('sucesso', 'Última mudança desfeita. A OS voltou para "' . status_nome((int) $ultima['status_anterior_id']) . '".');
                break;

            case 'reverter':
                $novoId = input_int('status_id');
                manutencao_forcar_status($manutencao, $novoId, $usuario, input('motivo'));
                flash('sucesso', 'Status alterado para "' . status_nome($novoId) . '".');
                break;

            case 'excluir':
                manutencao_excluir($id);
                flash('sucesso', 'OS #' . $id . ' do equipamento ' . $equipamento['codigo'] . ' excluída.');
                redirect('manutencoes/index.php');

            case 'status':
                $novoId = input_int('status_id');
                manutencao_mudar_status($manutencao, $novoId, $usuario, input('observacao'));
                flash('sucesso', 'Status alterado para "' . status_nome($novoId) . '".');
                break;

            case 'enviar_erp':
                manutencao_mudar_status($manutencao, status_sistema_id(STATUS_ENVIADO_ERP), $usuario, input('observacao'));
                flash('sucesso', 'Aparelho enviado à ADM. A Automação ERP vai confirmar a chegada.');
                break;

            case 'detalhes':
                manutencao_atualizar_detalhes($manutencao, input('defeito'), input('servico'), input_int('tecnico_id'));
                flash('sucesso', 'Informações da OS salvas.');
                break;

            case 'peca_adicionar':
                peca_adicionar($manutencao, input('descricao'), input_int('quantidade'));
                flash('sucesso', 'Peça adicionada.');
                break;

            case 'peca_remover':
                peca_remover($manutencao, input_int('peca_id'));
                flash('sucesso', 'Peça removida.');
                break;

            case 'observacao':
                manutencao_observacao($manutencao, $usuario, input('observacao'));
                flash('sucesso', 'Observação registrada.');
                break;
        }
    } catch (RuntimeException $ex) {
        flash('erro', $ex->getMessage());
    }

    redirect('manutencoes/ver.php?id=' . $id);
}

$opcoesStatus = status_opcoes_hardware($manutencao);
$podeEnviar = pode_enviar_erp($manutencao);
$ultimaMudanca = $admin ? manutencao_ultima_mudanca($manutencao) : null;
$pecas = pecas_da_manutencao($id);
$movimentacoes = movimentacoes_da_manutencao($id);
$tecnicos = tecnicos_ativos();
$tecnicoInativo = $manutencao['tecnico_id'] && !tecnico_valido((int) $manutencao['tecnico_id']);

render_header('OS #' . $id, 'manutencoes');
?>
<?php if (input('imprimir') === '1'): ?>
    <div class="card mb-4 flex flex-wrap items-center gap-3 border-emerald-300 bg-emerald-50 p-4 text-emerald-900">
        <?= icon('printer') ?>
        <p class="flex-1 text-sm font-medium">Imprima a etiqueta com o código <span class="font-mono"><?= e($equipamento['codigo']) ?></span> e cole no equipamento.</p>
        <a href="<?= e(url('etiqueta.php?id=' . $equipamento['id'] . '&auto=1')) ?>" target="_blank" rel="noopener" class="btn btn-primary">Imprimir etiqueta</a>
    </div>
<?php endif; ?>

<div class="card mb-6 flex flex-wrap items-start gap-4 p-4">
    <div class="min-w-0 flex-1">
        <p class="text-sm text-muted-foreground"><?= e(TIPOS_CODIGO[$equipamento['tipo_codigo']]) ?></p>
        <a href="<?= e(url('equipamentos/ver.php?id=' . $equipamento['id'])) ?>" class="link font-mono text-xl font-bold"><?= e($equipamento['codigo']) ?></a>
        <p class="mt-1 truncate text-sm"><?= e(equipamento_descricao($equipamento)) ?><?= $equipamento['setor_origem'] ? ' · ' . e($equipamento['setor_origem']) : '' ?></p>
        <p class="mt-1 text-xs text-muted-foreground">OS aberta em <?= e(data_hora($manutencao['aberta_em'])) ?><?= $manutencao['concluida_em'] ? ' · Encerrada em ' . e(data_hora($manutencao['concluida_em'])) : '' ?></p>
    </div>
    <div class="flex flex-col items-end gap-3">
        <?= status_badge((int) $manutencao['status_id'], 'lg') ?>
        <a href="<?= e(url('etiqueta.php?id=' . $equipamento['id'])) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><?= icon('printer', 'size-4') ?> Imprimir etiqueta</a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="card p-4" aria-labelledby="t-etapa">
            <h2 id="t-etapa" class="text-base font-semibold">Status da OS</h2>
            <?php if ($opcoesStatus): ?>
                <form method="post" class="mt-3 space-y-3">
                    <?= csrf_field() ?>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="status_id" class="label">Novo status</label>
                            <select id="status_id" name="status_id" class="input">
                                <?php foreach ($opcoesStatus as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"><?= e($s['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="obs-etapa" class="label">Observação <span class="font-normal text-muted-foreground">(opcional)</span></label>
                            <input id="obs-etapa" name="observacao" class="input" placeholder="Ex.: aguardando fonte 500W">
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="acao" value="status" class="btn btn-primary"><?= icon('arrow-right', 'size-4') ?> Alterar status</button>
                        <?php if ($podeEnviar): ?>
                            <button type="submit" name="acao" value="enviar_erp" class="btn btn-dark" data-confirmar="Enviar o aparelho à ADM? Depois disso só a Automação ERP pode movimentar esta OS."><?= icon('arrow-right', 'size-4') ?> Enviar à ADM</button>
                        <?php endif; ?>
                    </div>
                    <?php if (!$podeEnviar): ?>
                        <p class="text-sm text-muted-foreground">O envio à ADM fica disponível quando a OS estiver num status que encerra o serviço (ex.: Concluído ou Sem conserto).</p>
                    <?php endif; ?>
                </form>
            <?php elseif (aguardando_erp($manutencao)): ?>
                <p class="mt-2 text-sm text-muted-foreground">Aparelho enviado à ADM. Aguardando a Automação ERP confirmar a chegada.</p>
            <?php elseif (manutencao_com_erp($manutencao)): ?>
                <p class="mt-2 text-sm text-muted-foreground">Chegada confirmada na ADM. OS finalizada.</p>
            <?php else: ?>
                <p class="mt-2 text-sm text-muted-foreground">Não há outros status ativos. Um administrador pode cadastrá-los em Status.</p>
            <?php endif; ?>
        </section>

        <section class="card p-4" aria-labelledby="t-detalhes">
            <h2 id="t-detalhes" class="text-base font-semibold">Diagnóstico e serviço</h2>
            <form method="post" class="mt-3 space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="detalhes">
                <fieldset class="space-y-4" <?= $editavel ? '' : 'disabled' ?>>
                    <div>
                        <label for="defeito" class="label">Defeito relatado</label>
                        <textarea id="defeito" name="defeito" rows="2" class="input" required><?= e($manutencao['defeito_relatado']) ?></textarea>
                    </div>
                    <div>
                        <label for="servico" class="label">Serviço executado / solução</label>
                        <textarea id="servico" name="servico" rows="3" class="input" placeholder="O que foi feito no equipamento"><?= e($manutencao['servico_executado']) ?></textarea>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="w-full sm:w-64">
                            <label for="tecnico_id" class="label">Técnico responsável</label>
                            <select id="tecnico_id" name="tecnico_id" class="input">
                                <?php if ($tecnicoInativo): ?>
                                    <option value="<?= (int) $manutencao['tecnico_id'] ?>" selected><?= e($manutencao['tecnico_nome']) ?> (inativo)</option>
                                <?php endif; ?>
                                <?php foreach ($tecnicos as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $manutencao['tecnico_id'] ? 'selected' : '' ?>><?= e($t['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($editavel): ?>
                            <button type="submit" class="btn btn-dark ml-auto">Salvar</button>
                        <?php endif; ?>
                    </div>
                </fieldset>
            </form>
        </section>

        <section class="card overflow-hidden" aria-labelledby="t-pecas">
            <h2 id="t-pecas" class="p-4 pb-2 text-base font-semibold">Peças trocadas</h2>
            <?php if ($pecas): ?>
                <table class="tabela">
                    <thead class="border-t">
                        <tr><th>Peça</th><th class="w-20">Qtd.</th><th class="w-16"><span class="sr-only">Ações</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php foreach ($pecas as $p): ?>
                            <tr>
                                <td class="px-4 py-2"><?= e($p['descricao']) ?></td>
                                <td class="px-4 py-2"><?= (int) $p['quantidade'] ?></td>
                                <td class="px-4 py-2 text-right">
                                    <?php if ($editavel): ?>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="acao" value="peca_remover">
                                            <input type="hidden" name="peca_id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="inline-flex size-8 cursor-pointer items-center justify-center rounded-md text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-ring" aria-label="Remover <?= e($p['descricao']) ?>" data-confirmar="Remover esta peça?">
                                                <?= icon('trash', 'size-4') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="px-4 pb-2 text-sm text-muted-foreground">Nenhuma peça registrada.</p>
            <?php endif; ?>
            <?php if ($editavel): ?>
                <form method="post" class="flex flex-wrap items-end gap-2 border-t border-border p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="peca_adicionar">
                    <div class="min-w-48 flex-1">
                        <label for="peca-descricao" class="label">Peça</label>
                        <input id="peca-descricao" name="descricao" class="input" maxlength="200" placeholder="Ex.: SSD 240GB Kingston" required>
                    </div>
                    <div class="w-24">
                        <label for="peca-qtd" class="label">Qtd.</label>
                        <input id="peca-qtd" name="quantidade" type="number" min="1" value="1" class="input" required>
                    </div>
                    <button type="submit" class="btn btn-secondary"><?= icon('plus', 'size-4') ?> Adicionar</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <div class="space-y-6">
        <section class="card p-4" aria-labelledby="t-traj">
            <h2 id="t-traj" class="mb-4 text-base font-semibold">Trajetória</h2>
            <?php render_timeline($movimentacoes); ?>
        </section>

        <form method="post" class="card space-y-3 p-4">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="observacao">
            <label for="obs-livre" class="label">Adicionar observação</label>
            <textarea id="obs-livre" name="observacao" rows="2" class="input" required></textarea>
            <button type="submit" class="btn btn-secondary w-full"><?= icon('message', 'size-4') ?> Registrar observação</button>
        </form>

        <?php if ($admin): ?>
            <section class="card space-y-4 border-amber-300 p-4" aria-labelledby="t-admin">
                <div>
                    <h2 id="t-admin" class="flex items-center gap-2 text-base font-semibold"><?= icon('alert', 'size-4 text-amber-600') ?> Zona do administrador</h2>
                    <p class="mt-1 text-xs text-muted-foreground">Estas ações ignoram as regras do fluxo. A reversão fica registrada na trajetória com o seu nome e o motivo.</p>
                </div>

                <?php if ($ultimaMudanca): ?>
                    <form method="post" class="space-y-2 border-t border-border pt-4">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="desfazer">
                        <p class="text-sm font-medium">Desfazer a última mudança</p>
                        <p class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                            Voltar de <?= status_badge((int) $manutencao['status_id']) ?> para <?= status_badge((int) $ultimaMudanca['status_anterior_id']) ?>
                        </p>
                        <label for="motivo-desfazer" class="sr-only">Motivo</label>
                        <input id="motivo-desfazer" name="motivo" class="input" placeholder="Motivo (obrigatório)" required>
                        <button type="submit" class="btn btn-secondary w-full" data-confirmar="Desfazer a última mudança de status?"><?= icon('undo', 'size-4') ?> Desfazer última mudança</button>
                    </form>
                <?php endif; ?>

                <form method="post" class="space-y-2 border-t border-border pt-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="reverter">
                    <label for="status-admin" class="text-sm font-medium">Definir qualquer status</label>
                    <select id="status-admin" name="status_id" class="input">
                        <?php foreach (status_todos() as $s): if ((int) $s['id'] === (int) $manutencao['status_id']) { continue; } ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['nome']) ?><?= $s['ativo'] ? '' : ' (inativo)' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="motivo-reverter" class="sr-only">Motivo</label>
                    <input id="motivo-reverter" name="motivo" class="input" placeholder="Motivo (obrigatório)" required>
                    <button type="submit" class="btn btn-secondary w-full" data-confirmar="Alterar o status desta OS como administrador?"><?= icon('arrow-right', 'size-4') ?> Aplicar status</button>
                </form>

                <form method="post" class="space-y-2 border-t border-border pt-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <p class="text-xs text-muted-foreground">Apaga a OS, as peças e a trajetória dela. O equipamento continua cadastrado.</p>
                    <button type="submit" class="btn btn-danger w-full" data-confirmar="Excluir a OS #<?= $id ?> definitivamente? Esta ação não pode ser desfeita."><?= icon('trash', 'size-4') ?> Excluir OS</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php render_footer();
