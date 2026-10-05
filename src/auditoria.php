<?php

declare(strict_types=1);

const CATEGORIAS_LOG = [
    'os'           => 'Ordens de serviço',
    'equipamento'  => 'Equipamentos',
    'etiqueta'     => 'Etiquetas',
    'usuario'      => 'Usuários',
    'status'       => 'Status',
    'configuracao' => 'Configurações',
    'atualizacao'  => 'Atualizações do sistema',
];

/**
 * Registra uma ação no log do sistema.
 * $alteracoes: ['Campo' => [antes, depois]], normalmente vindo de diferencas().
 */
function auditar(string $categoria, string $acao, string $descricao, ?int $referenciaId = null, array $alteracoes = []): void
{
    $usuario = usuario_atual();
    db_executar(
        'INSERT INTO logs_sistema (usuario_id, usuario_nome, setor, categoria, acao, descricao, referencia_id, detalhes, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $usuario ? (int) $usuario['id'] : null,
            $usuario['nome'] ?? 'Sistema',
            $usuario['setor'] ?? null,
            $categoria,
            mb_substr($acao, 0, 60),
            mb_substr($descricao, 0, 500),
            $referenciaId,
            $alteracoes ? json_encode($alteracoes, JSON_UNESCAPED_UNICODE) : null,
            ip_cliente('linha de comando'),
        ]
    );
}

/** Compara dois registros e devolve só os campos alterados, com rótulos legíveis. */
function diferencas(array $antes, array $depois, array $rotulos): array
{
    $mudou = [];
    foreach ($rotulos as $campo => $rotulo) {
        $de = (string) ($antes[$campo] ?? '');
        $para = (string) ($depois[$campo] ?? '');
        if ($de !== $para) {
            $mudou[$rotulo] = [$de, $para];
        }
    }

    return $mudou;
}

/** Página do registro citado no log, quando ele tem uma. */
function log_link(array $log): ?string
{
    $id = $log['referencia_id'];
    if (!$id) {
        return null;
    }

    return match ($log['categoria']) {
        'os'                      => url('manutencoes/ver.php?id=' . $id),
        'equipamento', 'etiqueta' => url('equipamentos/ver.php?id=' . $id),
        'usuario'                 => url('usuarios_editar.php?id=' . $id),
        default                   => null,
    };
}

function sim_nao(mixed $valor): string
{
    return $valor ? 'Sim' : 'Não';
}
