<?php

declare(strict_types=1);

const POR_PAGINA_OPCOES = [25, 50, 100, 200];

/** Monta a cláusula WHERE das telas de listagem a partir dos filtros preenchidos. */
final class Filtro
{
    /** @var list<string> */
    private array $condicoes = [];

    /** @var list<mixed> */
    private array $params = [];

    public function igual(string $coluna, mixed $valor, bool $aplicar = true): self
    {
        if ($aplicar) {
            $this->condicoes[] = "{$coluna} = ?";
            $this->params[] = $valor;
        }

        return $this;
    }

    /** @param list<string> $colunas o termo pode estar em qualquer uma delas */
    public function contem(array $colunas, string $termo): self
    {
        if ($termo !== '') {
            $this->condicoes[] = '(' . implode(' OR ', array_map(fn ($c) => "{$c} LIKE ?", $colunas)) . ')';
            array_push($this->params, ...array_fill(0, count($colunas), '%' . $termo . '%'));
        }

        return $this;
    }

    /** Datas em AAAA-MM-DD (vazias são ignoradas), incluindo o dia final inteiro. */
    public function periodo(string $coluna, string $de, string $ate): self
    {
        if ($de !== '') {
            $this->condicoes[] = "{$coluna} >= ?";
            $this->params[] = $de . ' 00:00:00';
        }
        if ($ate !== '') {
            $this->condicoes[] = "{$coluna} <= ?";
            $this->params[] = $ate . ' 23:59:59';
        }

        return $this;
    }

    public function sql(): string
    {
        return $this->condicoes ? 'WHERE ' . implode(' AND ', $this->condicoes) : '';
    }

    public function params(): array
    {
        return $this->params;
    }

    public function ativo(): bool
    {
        return $this->condicoes !== [];
    }
}

final class ResultadoPaginado
{
    public function __construct(
        public readonly array $linhas,
        public readonly int $total,
        public readonly int $pagina,
        public readonly int $porPagina,
    ) {
    }

    public function paginas(): int
    {
        return max(1, (int) ceil($this->total / $this->porPagina));
    }
}

/**
 * Página atual e quantidade por página, validadas. A quantidade escolhida fica salva
 * na sessão por tela; a página é limitada à última existente.
 *
 * @return array{0: int, 1: int} [página, por página]
 */
function paginacao(int $total, int $padrao = 50): array
{
    $tela = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $escolha = input_int('por_pagina');
    if (in_array($escolha, POR_PAGINA_OPCOES, true)) {
        $_SESSION['por_pagina'][$tela] = $escolha;
    }
    $porPagina = (int) ($_SESSION['por_pagina'][$tela] ?? $padrao);
    $pagina = min(max(1, input_int('pagina')), max(1, (int) ceil($total / $porPagina)));

    return [$pagina, $porPagina];
}

/** $origem é o trecho FROM/JOIN sem a palavra FROM. */
function consulta_paginada(string $colunas, string $origem, Filtro $filtro, string $ordem): ResultadoPaginado
{
    $where = $filtro->sql();
    $total = (int) db_valor("SELECT COUNT(*) FROM {$origem} {$where}", $filtro->params());
    [$pagina, $porPagina] = paginacao($total);
    $offset = ($pagina - 1) * $porPagina;

    $linhas = db_lista(
        "SELECT {$colunas} FROM {$origem} {$where} ORDER BY {$ordem} LIMIT {$porPagina} OFFSET {$offset}",
        $filtro->params()
    );

    return new ResultadoPaginado($linhas, $total, $pagina, $porPagina);
}
