<?php

declare(strict_types=1);

const TIPOS_CODIGO = [
    'patrimonio' => 'Patrimônio',
    'ns'         => 'Número de série (NS)',
];

const TIPOS_EQUIPAMENTO = [
    'Desktop', 'Notebook', 'All-in-one', 'Monitor', 'Impressora', 'Nobreak',
    'Switch', 'Roteador', 'Teclado', 'Mouse', 'Leitor de código de barras', 'Outro',
];

const CAMPOS_EQUIPAMENTO = [
    'codigo'       => 'Código',
    'tipo_codigo'  => 'Tipo de código',
    'tipo'         => 'Tipo',
    'marca'        => 'Marca',
    'modelo'       => 'Modelo',
    'setor_origem' => 'Setor de origem',
    'responsavel'  => 'Responsável',
];

/** Colunas que podem alimentar listas de filtro. */
const COLUNAS_DISTINTAS = ['tipo', 'setor_origem', 'marca'];

function equipamento_por_id(int $id): ?array
{
    return db_linha('SELECT * FROM equipamentos WHERE id = ?', [$id]);
}

function equipamento_por_codigo(string $codigo): ?array
{
    return db_linha('SELECT * FROM equipamentos WHERE codigo = ?', [normalizar_codigo($codigo)]);
}

/** @return array{0: array, 1: array<string, string>} dados limpos e erros por campo */
function equipamento_validar(array $dados, ?int $ignorarId = null): array
{
    $texto = fn (string $campo) => trim((string) ($dados[$campo] ?? ''));
    $limpo = [
        'codigo'       => normalizar_codigo((string) ($dados['codigo'] ?? '')),
        'tipo_codigo'  => (string) ($dados['tipo_codigo'] ?? 'patrimonio'),
        'tipo'         => $texto('tipo'),
        'marca'        => $texto('marca'),
        'modelo'       => $texto('modelo'),
        'setor_origem' => $texto('setor_origem'),
        'responsavel'  => $texto('responsavel'),
    ];
    $erros = [];

    if ($erro = codigo_erro($limpo['codigo'], $ignorarId)) {
        $erros['codigo'] = $erro;
    }
    if (!array_key_exists($limpo['tipo_codigo'], TIPOS_CODIGO)) {
        $erros['tipo_codigo'] = 'Escolha se o código é patrimônio ou número de série.';
    }
    if ($limpo['tipo'] === '') {
        $erros['tipo'] = 'Informe o tipo do equipamento.';
    }

    return [$limpo, $erros];
}

function codigo_erro(string $codigo, ?int $ignorarId): ?string
{
    if ($codigo === '') {
        return 'Informe o patrimônio ou o número de série.';
    }
    if (mb_strlen($codigo) > 64) {
        return 'O código pode ter no máximo 64 caracteres.';
    }
    if (!preg_match('/^[\x20-\x7E]+$/', $codigo)) {
        return 'Use apenas letras sem acento, números e símbolos simples (o código de barras não aceita acentos).';
    }
    $existente = equipamento_por_codigo($codigo);
    if ($existente && (int) $existente['id'] !== $ignorarId) {
        return 'Já existe um equipamento com este código.';
    }

    return null;
}

function equipamento_criar(array $dados): int
{
    $id = db_inserir(
        'INSERT INTO equipamentos (codigo, tipo_codigo, tipo, marca, modelo, setor_origem, responsavel)
         VALUES (:codigo, :tipo_codigo, :tipo, :marca, :modelo, :setor_origem, :responsavel)',
        $dados
    );
    auditar('equipamento', 'Equipamento cadastrado', 'Equipamento ' . $dados['codigo'] . ' cadastrado: ' . equipamento_descricao($dados), $id);

    return $id;
}

function equipamento_atualizar(int $id, array $dados): void
{
    $antes = equipamento_por_id($id) ?? [];
    db_executar(
        'UPDATE equipamentos SET codigo = :codigo, tipo_codigo = :tipo_codigo, tipo = :tipo, marca = :marca,
         modelo = :modelo, setor_origem = :setor_origem, responsavel = :responsavel WHERE id = :id',
        $dados + ['id' => $id]
    );

    $mudou = diferencas($antes, $dados, CAMPOS_EQUIPAMENTO);
    if ($mudou) {
        auditar('equipamento', 'Equipamento editado', 'Dados do equipamento ' . $dados['codigo'] . ' alterados', $id, $mudou);
    }
}

function equipamento_descricao(array $eq): string
{
    return trim(implode(' ', array_filter([$eq['tipo'] ?? '', $eq['marca'] ?? '', $eq['modelo'] ?? ''])));
}

function valores_distintos(string $coluna): array
{
    if (!in_array($coluna, COLUNAS_DISTINTAS, true)) {
        return [];
    }

    return db_coluna("SELECT DISTINCT {$coluna} FROM equipamentos WHERE {$coluna} IS NOT NULL AND {$coluna} <> '' ORDER BY {$coluna}");
}
