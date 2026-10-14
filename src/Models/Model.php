<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Atalhos de consulta usados por todos os models.
 * Os valores vão SEMPRE pelos placeholders (?) — proteção contra SQL injection.
 */
abstract class Model
{
    protected static function db(): PDO
    {
        return Database::conexao();
    }

    /** Várias linhas: [ ['idCliente' => 1, ...], ... ] */
    protected static function todos(string $sql, array $valores = []): array
    {
        $consulta = self::db()->prepare($sql);
        $consulta->execute($valores);
        return $consulta->fetchAll();
    }

    /** Uma linha ou null */
    protected static function um(string $sql, array $valores = []): ?array
    {
        $consulta = self::db()->prepare($sql);
        $consulta->execute($valores);
        return $consulta->fetch() ?: null;
    }

    /** INSERT: devolve o id gerado pelo AUTO_INCREMENT */
    protected static function inserir(string $sql, array $valores = []): int
    {
        self::db()->prepare($sql)->execute($valores);
        return (int) self::db()->lastInsertId();
    }

    /** UPDATE/DELETE: devolve quantas linhas foram encontradas */
    protected static function executar(string $sql, array $valores = []): int
    {
        $consulta = self::db()->prepare($sql);
        $consulta->execute($valores);
        return $consulta->rowCount();
    }

    /** '' vira null (campos opcionais vazios do formulário) */
    protected static function ouNulo(mixed $valor): mixed
    {
        return $valor === '' || $valor === null ? null : $valor;
    }
}
