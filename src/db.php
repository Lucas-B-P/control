<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $c = require __DIR__ . '/../config/database.php';
        $pdo = new PDO(
            "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",
            $c['user'],
            $c['password'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // Mantém CURRENT_TIMESTAMP do MySQL no mesmo fuso do PHP.
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }

    return $pdo;
}

function db_executar(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt;
}

function db_linha(string $sql, array $params = []): ?array
{
    return db_executar($sql, $params)->fetch() ?: null;
}

function db_lista(string $sql, array $params = []): array
{
    return db_executar($sql, $params)->fetchAll();
}

function db_valor(string $sql, array $params = []): mixed
{
    return db_executar($sql, $params)->fetchColumn();
}

function db_coluna(string $sql, array $params = []): array
{
    return db_executar($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
}

/** Primeira coluna como chave e segunda como valor. */
function db_pares(string $sql, array $params = []): array
{
    return db_executar($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
}

function db_inserir(string $sql, array $params = []): int
{
    db_executar($sql, $params);

    return (int) db()->lastInsertId();
}

/** "?, ?, ?" para usar em IN (...). */
function marcadores(array $valores): string
{
    return implode(', ', array_fill(0, count($valores), '?'));
}

/**
 * @template T
 * @param callable(): T $operacao
 * @return T
 */
function transacao(callable $operacao): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $resultado = $operacao();
        $pdo->commit();

        return $resultado;
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    }
}
