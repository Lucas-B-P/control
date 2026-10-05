<?php

declare(strict_types=1);

const STATUS_INICIAL = 'inicial';
const STATUS_ENVIADO_ERP = 'enviado_erp';
const STATUS_RECEBIDO_ERP = 'recebido_erp';

/** Papéis em que a OS está com a ADM e o Hardware não pode mais alterá-la. */
const STATUS_COM_ERP = [STATUS_ENVIADO_ERP, STATUS_RECEBIDO_ERP];

const PAPEIS_SISTEMA = [
    STATUS_INICIAL      => 'Entrada de uma nova OS (e devolução pela ADM)',
    STATUS_ENVIADO_ERP  => 'Envio do aparelho à ADM',
    STATUS_RECEBIDO_ERP => 'Chegada confirmada pela Automação ERP',
];

const CORES_STATUS = [
    'slate'   => ['Cinza', 'bg-slate-100 text-slate-800 ring-slate-300'],
    'sky'     => ['Azul claro', 'bg-sky-50 text-sky-800 ring-sky-300'],
    'blue'    => ['Azul', 'bg-blue-50 text-blue-800 ring-blue-300'],
    'indigo'  => ['Índigo', 'bg-indigo-50 text-indigo-800 ring-indigo-300'],
    'violet'  => ['Violeta', 'bg-violet-50 text-violet-800 ring-violet-300'],
    'pink'    => ['Rosa', 'bg-pink-50 text-pink-800 ring-pink-300'],
    'red'     => ['Vermelho', 'bg-red-50 text-red-800 ring-red-300'],
    'orange'  => ['Laranja', 'bg-orange-50 text-orange-900 ring-orange-300'],
    'amber'   => ['Amarelo', 'bg-amber-50 text-amber-900 ring-amber-300'],
    'emerald' => ['Verde', 'bg-emerald-50 text-emerald-800 ring-emerald-300'],
    'teal'    => ['Verde-azulado', 'bg-teal-50 text-teal-800 ring-teal-300'],
];

/** Todos os status (inclusive inativos), indexados por id e ordenados. */
function status_todos(bool $recarregar = false): array
{
    static $cache = null;
    if ($cache === null || $recarregar) {
        $cache = array_column(db_lista('SELECT * FROM status_manutencao ORDER BY ordem, id'), null, 'id');
    }

    return $cache;
}

function status_por_id(?int $id): ?array
{
    return $id === null ? null : (status_todos()[$id] ?? null);
}

function status_sistema(string $papel): array
{
    foreach (status_todos() as $s) {
        if ($s['sistema'] === $papel) {
            return $s;
        }
    }
    throw new RuntimeException('Status do sistema "' . $papel . '" não encontrado. Execute php database/setup.php.');
}

function status_sistema_id(string $papel): int
{
    return (int) status_sistema($papel)['id'];
}

function status_nome(?int $id): string
{
    return status_por_id($id)['nome'] ?? '-';
}

function status_cor_nome(string $cor): string
{
    return CORES_STATUS[$cor][0] ?? $cor;
}

function status_classes(string $cor): string
{
    return CORES_STATUS[$cor][1] ?? CORES_STATUS['slate'][1];
}

function status_classe_texto(string $cor): string
{
    return explode(' ', status_classes($cor))[1];
}

function status_badge(?int $id, string $tamanho = 'sm'): string
{
    $s = status_por_id($id);
    if (!$s) {
        return '<span class="text-muted-foreground">-</span>';
    }
    $tam = $tamanho === 'lg' ? 'px-3 py-1.5 text-base' : 'px-2 py-0.5 text-xs';

    return '<span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full font-semibold ring-1 ring-inset ' . $tam . ' ' . status_classes($s['cor']) . '">'
        . '<span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>'
        . e($s['nome']) . '</span>';
}

/** Ids dos status em que a OS ainda está em andamento no Hardware. */
function status_ids_abertos(): array
{
    return array_keys(array_filter(status_todos(), fn ($s) => !$s['encerra']));
}

/** @return array{0: array, 1: list<string>} dados limpos e mensagens de erro */
function status_validar(array $dados, ?int $ignorarId = null): array
{
    $limpo = [
        'nome'    => mb_substr(trim((string) ($dados['nome'] ?? '')), 0, 60),
        'cor'     => (string) ($dados['cor'] ?? 'slate'),
        'ordem'   => (int) ($dados['ordem'] ?? 0),
        'encerra' => empty($dados['encerra']) ? 0 : 1,
    ];
    $erros = [];

    if ($limpo['nome'] === '') {
        $erros[] = 'Informe o nome do status.';
    } elseif (db_valor('SELECT id FROM status_manutencao WHERE nome = ? AND id <> ?', [$limpo['nome'], $ignorarId ?? 0])) {
        $erros[] = 'Já existe um status chamado "' . $limpo['nome'] . '".';
    }
    if (!array_key_exists($limpo['cor'], CORES_STATUS)) {
        $erros[] = 'Escolha uma cor válida.';
    }

    return [$limpo, $erros];
}

function status_validado(array $dados, ?int $ignorarId = null): array
{
    [$limpo, $erros] = status_validar($dados, $ignorarId);
    if ($erros) {
        throw new RuntimeException(implode(' ', $erros));
    }

    return $limpo;
}

function status_em_uso(int $id): bool
{
    return (int) db_valor(
        'SELECT (SELECT COUNT(*) FROM manutencoes WHERE status_id = ?)
              + (SELECT COUNT(*) FROM movimentacoes WHERE status_novo_id = ? OR status_anterior_id = ?)',
        [$id, $id, $id]
    ) > 0;
}

function status_criar(array $dados): string
{
    $dados = status_validado($dados);
    $id = db_inserir('INSERT INTO status_manutencao (nome, cor, ordem, encerra) VALUES (:nome, :cor, :ordem, :encerra)', $dados);
    auditar(
        'status',
        'Status criado',
        'Status "' . $dados['nome'] . '" criado (cor ' . status_cor_nome($dados['cor']) . ', ordem ' . $dados['ordem'] . ', encerra o serviço: ' . sim_nao($dados['encerra']) . ')',
        $id
    );

    return $dados['nome'];
}

function status_salvar(array $status, array $dados): string
{
    $id = (int) $status['id'];
    $dados = status_validado($dados, $id);
    // O papel do status no fluxo (encerrar ou não) é fixo para os status do sistema.
    if ($status['sistema']) {
        $dados['encerra'] = (int) $status['encerra'];
    }
    db_executar('UPDATE status_manutencao SET nome = :nome, cor = :cor, ordem = :ordem, encerra = :encerra WHERE id = :id', $dados + ['id' => $id]);

    $legivel = fn (array $s) => ['cor' => status_cor_nome($s['cor']), 'encerra' => sim_nao($s['encerra'])] + $s;
    $mudou = diferencas($legivel($status), $legivel($dados), ['nome' => 'Nome', 'cor' => 'Cor', 'ordem' => 'Ordem', 'encerra' => 'Encerra o serviço']);
    if ($mudou) {
        auditar('status', 'Status editado', 'Status "' . $dados['nome'] . '": ' . implode(', ', array_keys($mudou)) . ' alterado(s)', $id, $mudou);
    }

    return $dados['nome'];
}

function status_alternar(array $status): void
{
    if ($status['sistema']) {
        throw new RuntimeException('Status do sistema não podem ser desativados.');
    }
    $id = (int) $status['id'];
    if ($status['ativo'] && (int) db_valor('SELECT COUNT(*) FROM manutencoes WHERE status_id = ?', [$id]) > 0) {
        throw new RuntimeException('Há OS neste status agora. Mude essas OS de status antes de desativá-lo.');
    }
    db_executar('UPDATE status_manutencao SET ativo = 1 - ativo WHERE id = ?', [$id]);
    $acao = $status['ativo'] ? 'desativado' : 'reativado';
    auditar('status', 'Status ' . $acao, 'Status "' . $status['nome'] . '" ' . $acao, $id);
}

function status_excluir(array $status): void
{
    if ($status['sistema']) {
        throw new RuntimeException('Status do sistema não podem ser excluídos.');
    }
    $id = (int) $status['id'];
    if (status_em_uso($id)) {
        throw new RuntimeException('"' . $status['nome'] . '" já foi usado em OS e faz parte do histórico. Desative-o em vez de excluir.');
    }
    db_executar('DELETE FROM status_manutencao WHERE id = ?', [$id]);
    auditar('status', 'Status excluído', 'Status "' . $status['nome'] . '" excluído', $id);
}
