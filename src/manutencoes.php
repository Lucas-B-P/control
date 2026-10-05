<?php

declare(strict_types=1);

const SQL_MANUTENCAO = 'SELECT m.*, u.nome AS tecnico_nome FROM manutencoes m LEFT JOIN usuarios u ON u.id = m.tecnico_id';

const SQL_MOVIMENTACAO = 'SELECT mv.*, u.nome AS usuario_nome, ed.nome AS editado_por_nome
    FROM movimentacoes mv
    JOIN manutencoes m ON m.id = mv.manutencao_id
    JOIN usuarios u ON u.id = mv.usuario_id
    LEFT JOIN usuarios ed ON ed.id = mv.editado_por';

function manutencao_por_id(int $id): ?array
{
    return db_linha(SQL_MANUTENCAO . ' WHERE m.id = ?', [$id]);
}

function manutencao_ultima(int $equipamentoId): ?array
{
    return db_linha(SQL_MANUTENCAO . ' WHERE m.equipamento_id = ? ORDER BY m.id DESC LIMIT 1', [$equipamentoId]);
}

function manutencoes_do_equipamento(int $equipamentoId): array
{
    return db_lista(SQL_MANUTENCAO . ' WHERE m.equipamento_id = ? ORDER BY m.id DESC', [$equipamentoId]);
}

/** @return array<int, int> id do status => quantidade de OS */
function manutencoes_por_status(): array
{
    return db_pares('SELECT status_id, COUNT(*) FROM manutencoes GROUP BY status_id');
}

/** OS que passaram hoje de um status em andamento para um que encerra o serviço. */
function manutencoes_encerradas_hoje(): int
{
    return (int) db_valor(
        'SELECT COUNT(DISTINCT mv.manutencao_id) FROM movimentacoes mv
         JOIN status_manutencao sn ON sn.id = mv.status_novo_id
         LEFT JOIN status_manutencao sa ON sa.id = mv.status_anterior_id
         WHERE sn.encerra = 1 AND sn.sistema IS NULL AND (sa.encerra = 0 OR sa.id IS NULL) AND mv.criado_em >= CURDATE()'
    );
}

/** Enviadas pelo Hardware e ainda sem confirmação de chegada, da mais antiga para a mais nova. */
function manutencoes_aguardando_erp(): array
{
    return db_lista(
        'SELECT m.id, m.status_id, e.codigo, e.tipo, e.marca, e.modelo, e.setor_origem,
            (SELECT MAX(mv.criado_em) FROM movimentacoes mv WHERE mv.manutencao_id = m.id) AS enviado_em
         FROM manutencoes m JOIN equipamentos e ON e.id = m.equipamento_id
         WHERE m.status_id = ? ORDER BY enviado_em',
        [status_sistema_id(STATUS_ENVIADO_ERP)]
    );
}

function manutencao_aberta(int $equipamentoId, ?int $ignorarId = null): ?array
{
    $abertos = status_ids_abertos() ?: [0];

    return db_linha(
        'SELECT * FROM manutencoes WHERE equipamento_id = ? AND status_id IN (' . marcadores($abertos) . ') AND id <> ? ORDER BY id DESC LIMIT 1',
        [$equipamentoId, ...$abertos, $ignorarId ?? 0]
    );
}

function manutencao_abrir(int $equipamentoId, string $defeito, ?int $tecnicoId, int $usuarioId): int
{
    $inicial = status_sistema_id(STATUS_INICIAL);

    return transacao(function () use ($equipamentoId, $defeito, $tecnicoId, $usuarioId, $inicial) {
        // Trava o equipamento até o fim da transação: duas aberturas simultâneas não criam duas OS em andamento.
        db_executar('SELECT id FROM equipamentos WHERE id = ? FOR UPDATE', [$equipamentoId]);
        if (manutencao_aberta($equipamentoId)) {
            throw new RuntimeException('Este equipamento já tem uma OS em andamento.');
        }

        $id = db_inserir(
            'INSERT INTO manutencoes (equipamento_id, defeito_relatado, status_id, tecnico_id) VALUES (?, ?, ?, ?)',
            [$equipamentoId, $defeito, $inicial, $tecnicoId]
        );
        movimentacao_registrar($id, null, $inicial, $usuarioId, 'hardware', 'OS aberta no setor de Hardware');
        auditar('os', 'OS aberta', os_rotulo($id) . ' aberta. Defeito relatado: ' . $defeito, $id);

        return $id;
    });
}

function manutencao_papel(array $manutencao): ?string
{
    return status_por_id((int) $manutencao['status_id'])['sistema'] ?? null;
}

function manutencao_com_erp(array $manutencao): bool
{
    return in_array(manutencao_papel($manutencao), STATUS_COM_ERP, true);
}

function aguardando_erp(array $manutencao): bool
{
    return manutencao_papel($manutencao) === STATUS_ENVIADO_ERP;
}

function pode_enviar_erp(array $manutencao): bool
{
    $atual = status_por_id((int) $manutencao['status_id']);

    return $atual && $atual['encerra'] && !manutencao_com_erp($manutencao);
}

/** Status que o Hardware pode escolher livremente (sem os papéis controlados pela ADM). */
function status_opcoes_hardware(array $manutencao): array
{
    if (manutencao_com_erp($manutencao)) {
        return [];
    }

    return array_filter(
        status_todos(),
        fn ($s) => $s['ativo']
            && (int) $s['id'] !== (int) $manutencao['status_id']
            && !in_array($s['sistema'], STATUS_COM_ERP, true)
    );
}

function manutencao_mudar_status(array $manutencao, int $novoId, array $usuario, string $observacao = ''): void
{
    $atual = status_por_id((int) $manutencao['status_id']);
    $novo = status_por_id($novoId);
    if (!$novo || !$novo['ativo']) {
        throw new RuntimeException('Status inválido.');
    }

    if ($usuario['setor'] === 'hardware') {
        validar_mudanca_hardware($manutencao, $atual, $novo);
    } else {
        validar_mudanca_erp($atual, $novo, $observacao);
    }

    $acao = match (true) {
        $novo['sistema'] === STATUS_ENVIADO_ERP => 'Enviado à ADM',
        $novo['sistema'] === STATUS_RECEBIDO_ERP => 'Chegada confirmada na ADM',
        $usuario['setor'] === 'erp' && $novo['sistema'] === STATUS_INICIAL => 'Devolvido ao Hardware',
        default => 'Status alterado',
    };
    manutencao_aplicar_status($manutencao, $atual, $novo, $usuario, $observacao, $acao);
}

function validar_mudanca_hardware(array $manutencao, array $atual, array $novo): void
{
    if (manutencao_com_erp($manutencao)) {
        throw new RuntimeException('Esta OS já foi enviada à ADM e não pode ser alterada pelo Hardware.');
    }
    if ($novo['sistema'] === STATUS_RECEBIDO_ERP) {
        throw new RuntimeException('Somente a Automação ERP confirma a chegada na ADM.');
    }
    if ($novo['sistema'] === STATUS_ENVIADO_ERP && !$atual['encerra']) {
        throw new RuntimeException('Mude a OS para um status que encerra o serviço (ex.: Concluído) antes de enviar à ADM.');
    }
    if ((int) $novo['id'] === (int) $atual['id']) {
        throw new RuntimeException('A OS já está neste status.');
    }
}

function validar_mudanca_erp(array $atual, array $novo, string $observacao): void
{
    if ($atual['sistema'] !== STATUS_ENVIADO_ERP || !in_array($novo['sistema'], [STATUS_RECEBIDO_ERP, STATUS_INICIAL], true)) {
        throw new RuntimeException('A Automação ERP só pode confirmar a chegada ou devolver aparelhos enviados à ADM.');
    }
    if ($novo['sistema'] === STATUS_INICIAL && $observacao === '') {
        throw new RuntimeException('Informe o motivo da devolução ao Hardware.');
    }
}

/** Grava a troca de status (com checagem de concorrência) e registra a movimentação. */
function manutencao_aplicar_status(array $manutencao, array $atual, array $novo, array $usuario, string $observacao, string $acao): void
{
    $id = (int) $manutencao['id'];
    $reabrindo = $atual['encerra'] && !$novo['encerra'];
    if ($reabrindo && manutencao_aberta((int) $manutencao['equipamento_id'], $id)) {
        throw new RuntimeException('Este equipamento já tem outra OS em andamento no Hardware.');
    }

    $concluidaEm = match (true) {
        !$novo['encerra'] => null,
        !$atual['encerra'] => date('Y-m-d H:i:s'),
        default => $manutencao['concluida_em'],
    };

    transacao(function () use ($id, $atual, $novo, $usuario, $observacao, $acao, $concluidaEm) {
        $alterou = db_executar(
            'UPDATE manutencoes SET status_id = ?, concluida_em = ? WHERE id = ? AND status_id = ?',
            [$novo['id'], $concluidaEm, $id, $atual['id']]
        )->rowCount();
        if ($alterou === 0) {
            throw new RuntimeException('A OS foi alterada por outra pessoa. Recarregue a página.');
        }

        movimentacao_registrar($id, (int) $atual['id'], (int) $novo['id'], (int) $usuario['id'], $usuario['setor'], $observacao);
        $complemento = $observacao !== '' ? '. ' . $observacao : '';
        auditar('os', $acao, os_rotulo($id) . ': ' . $atual['nome'] . ' → ' . $novo['nome'] . $complemento, $id);
    });
}

/** Última troca de status que levou a OS ao status atual (ignora observações). */
function manutencao_ultima_mudanca(array $manutencao): ?array
{
    $mov = db_linha(
        'SELECT * FROM movimentacoes
         WHERE manutencao_id = ? AND status_anterior_id IS NOT NULL AND status_anterior_id <> status_novo_id
         ORDER BY criado_em DESC, id DESC LIMIT 1',
        [$manutencao['id']]
    );

    return $mov && (int) $mov['status_novo_id'] === (int) $manutencao['status_id'] ? $mov : null;
}

/** Troca de status feita pelo administrador, sem as regras de fluxo dos setores. */
function manutencao_forcar_status(array $manutencao, int $novoId, array $usuario, string $motivo): void
{
    if (!$usuario['admin']) {
        throw new RuntimeException('Somente administradores podem reverter status.');
    }
    if ($motivo === '') {
        throw new RuntimeException('Informe o motivo da reversão.');
    }
    $atual = status_por_id((int) $manutencao['status_id']);
    $novo = status_por_id($novoId) ?? throw new RuntimeException('Status inválido.');
    if ((int) $novo['id'] === (int) $atual['id']) {
        throw new RuntimeException('A OS já está neste status.');
    }

    manutencao_aplicar_status($manutencao, $atual, $novo, $usuario, 'Status revertido pelo administrador: ' . $motivo, 'Status revertido (admin)');
}

function manutencao_excluir(int $id): void
{
    $manutencao = manutencao_por_id($id);
    if (!$manutencao) {
        return;
    }
    $rotulo = os_rotulo($id);

    transacao(function () use ($id, $manutencao, $rotulo) {
        db_executar('DELETE FROM pecas_trocadas WHERE manutencao_id = ?', [$id]);
        db_executar('DELETE FROM movimentacoes WHERE manutencao_id = ?', [$id]);
        db_executar('DELETE FROM manutencoes WHERE id = ?', [$id]);
        auditar(
            'os',
            'OS excluída',
            $rotulo . ' excluída. Status na exclusão: ' . status_nome((int) $manutencao['status_id']) . '. Defeito: ' . $manutencao['defeito_relatado'],
            $id
        );
    });
}

function manutencao_observacao(array $manutencao, array $usuario, string $observacao): void
{
    if ($observacao === '') {
        throw new RuntimeException('Escreva a observação.');
    }
    $id = (int) $manutencao['id'];
    $status = (int) $manutencao['status_id'];
    movimentacao_registrar($id, $status, $status, (int) $usuario['id'], $usuario['setor'], $observacao);
    auditar('os', 'Observação registrada', os_rotulo($id) . ': ' . $observacao, $id);
}

function manutencao_exigir_editavel(array $manutencao): void
{
    if (manutencao_com_erp($manutencao)) {
        throw new RuntimeException('Esta OS já foi enviada à ADM e não pode ser alterada.');
    }
}

function manutencao_atualizar_detalhes(array $manutencao, string $defeito, string $servico, int $tecnicoId): void
{
    manutencao_exigir_editavel($manutencao);
    if ($defeito === '') {
        throw new RuntimeException('O defeito relatado não pode ficar vazio.');
    }
    if (!tecnico_valido($tecnicoId)) {
        throw new RuntimeException('Escolha um técnico válido.');
    }

    $id = (int) $manutencao['id'];
    db_executar(
        'UPDATE manutencoes SET defeito_relatado = ?, servico_executado = ?, tecnico_id = ? WHERE id = ?',
        [$defeito, $servico !== '' ? $servico : null, $tecnicoId, $id]
    );

    $mudou = diferencas($manutencao, manutencao_por_id($id) ?? [], [
        'defeito_relatado'  => 'Defeito relatado',
        'servico_executado' => 'Serviço executado',
        'tecnico_nome'      => 'Técnico',
    ]);
    if ($mudou) {
        auditar('os', 'Diagnóstico e serviço editados', os_rotulo($id) . ': ' . implode(', ', array_keys($mudou)) . ' alterado(s)', $id, $mudou);
    }
}

/** "OS #12 (PAT-00123)", usado nas descrições do log. */
function os_rotulo(int $id): string
{
    $codigo = db_valor('SELECT e.codigo FROM manutencoes m JOIN equipamentos e ON e.id = m.equipamento_id WHERE m.id = ?', [$id]);

    return 'OS #' . $id . ($codigo ? ' (' . $codigo . ')' : '');
}

function movimentacao_registrar(int $manutencaoId, ?int $anteriorId, int $novoId, int $usuarioId, string $setor, string $observacao = ''): void
{
    db_executar(
        'INSERT INTO movimentacoes (manutencao_id, status_anterior_id, status_novo_id, usuario_id, setor, observacao) VALUES (?, ?, ?, ?, ?, ?)',
        [$manutencaoId, $anteriorId, $novoId, $usuarioId, $setor, $observacao !== '' ? $observacao : null]
    );
}

function movimentacoes_da_manutencao(int $manutencaoId): array
{
    return db_lista(SQL_MOVIMENTACAO . ' WHERE mv.manutencao_id = ? ORDER BY mv.criado_em DESC, mv.id DESC', [$manutencaoId]);
}

function movimentacoes_do_equipamento(int $equipamentoId): array
{
    return db_lista(SQL_MOVIMENTACAO . ' WHERE m.equipamento_id = ? ORDER BY mv.criado_em DESC, mv.id DESC', [$equipamentoId]);
}

function movimentacoes_recentes(int $limite): array
{
    return db_lista(
        'SELECT mv.*, u.nome AS usuario_nome, e.codigo, e.tipo, e.marca, e.modelo
         FROM movimentacoes mv
         JOIN usuarios u ON u.id = mv.usuario_id
         JOIN manutencoes m ON m.id = mv.manutencao_id
         JOIN equipamentos e ON e.id = m.equipamento_id
         ORDER BY mv.criado_em DESC, mv.id DESC LIMIT ' . $limite
    );
}

/** Só o autor da observação ou um administrador pode editá-la. */
function observacao_pode_editar(array $movimentacao, ?array $usuario): bool
{
    return $usuario !== null
        && $movimentacao['observacao'] !== null
        && ((int) $movimentacao['usuario_id'] === (int) $usuario['id'] || $usuario['admin']);
}

function observacao_editar(int $movimentacaoId, string $texto, array $usuario): int
{
    $mv = db_linha(SQL_MOVIMENTACAO . ' WHERE mv.id = ?', [$movimentacaoId])
        ?? throw new RuntimeException('Observação não encontrada.');

    if (!observacao_pode_editar($mv, $usuario)) {
        throw new RuntimeException('Só quem escreveu a observação ou um administrador pode editá-la.');
    }
    if ($texto === '') {
        throw new RuntimeException('A observação não pode ficar vazia.');
    }
    $texto = mb_substr($texto, 0, 2000);
    if ($texto === $mv['observacao']) {
        throw new RuntimeException('Nenhuma alteração na observação.');
    }

    db_executar('UPDATE movimentacoes SET observacao = ?, editado_em = NOW(), editado_por = ? WHERE id = ?', [$texto, $usuario['id'], $movimentacaoId]);

    $manutencaoId = (int) $mv['manutencao_id'];
    auditar(
        'os',
        'Observação editada',
        os_rotulo($manutencaoId) . ': observação de ' . $mv['usuario_nome'] . ' (' . data_hora($mv['criado_em']) . ') editada',
        $manutencaoId,
        ['Observação' => [$mv['observacao'], $texto]]
    );

    return $manutencaoId;
}

function pecas_da_manutencao(int $manutencaoId): array
{
    return db_lista('SELECT * FROM pecas_trocadas WHERE manutencao_id = ? ORDER BY id', [$manutencaoId]);
}

function peca_adicionar(array $manutencao, string $descricao, int $quantidade): void
{
    manutencao_exigir_editavel($manutencao);
    $manutencaoId = (int) $manutencao['id'];
    if ($descricao === '') {
        throw new RuntimeException('Descreva a peça trocada.');
    }
    $descricao = mb_substr($descricao, 0, 200);
    $quantidade = max(1, $quantidade);
    db_executar('INSERT INTO pecas_trocadas (manutencao_id, descricao, quantidade) VALUES (?, ?, ?)', [$manutencaoId, $descricao, $quantidade]);
    auditar('os', 'Peça adicionada', os_rotulo($manutencaoId) . ': ' . $quantidade . 'x ' . $descricao, $manutencaoId);
}

function peca_remover(array $manutencao, int $pecaId): void
{
    manutencao_exigir_editavel($manutencao);
    $manutencaoId = (int) $manutencao['id'];
    $peca = db_linha('SELECT descricao, quantidade FROM pecas_trocadas WHERE id = ? AND manutencao_id = ?', [$pecaId, $manutencaoId]);
    if (!$peca) {
        return;
    }
    db_executar('DELETE FROM pecas_trocadas WHERE id = ? AND manutencao_id = ?', [$pecaId, $manutencaoId]);
    auditar('os', 'Peça removida', os_rotulo($manutencaoId) . ': ' . $peca['quantidade'] . 'x ' . $peca['descricao'], $manutencaoId);
}
