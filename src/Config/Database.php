<?php
declare(strict_types=1);

namespace App\Config;

use PDO;

/**
 * Conexão com o MySQL usando PDO.
 * No PHP cada requisição é um processo próprio: abre UMA conexão, usa e fecha no fim.
 * (No Node.js existia um "pool" porque o servidor fica sempre ligado atendendo todos.)
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexao(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST'), getenv('DB_PORT') ?: '3306', getenv('DB_NAME')
            );
            self::$pdo = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,     // erro de SQL vira exceção
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // linhas como arrays ['coluna' => valor]
                PDO::ATTR_EMULATE_PREPARES => false,              // prepared statements de verdade (no MySQL)
                PDO::MYSQL_ATTR_FOUND_ROWS => true,               // UPDATE conta linhas ENCONTRADAS, não só alteradas
            ]);
        }
        return self::$pdo;
    }

    /**
     * Executa $passos dentro de uma TRANSAÇÃO: tudo ou nada (RNF09).
     * Se qualquer passo lançar um erro, faz rollBack e repassa o erro.
     */
    public static function transacao(callable $passos): mixed
    {
        $pdo = self::conexao();
        $pdo->beginTransaction();
        try {
            $resultado = $passos($pdo);
            $pdo->commit();
            return $resultado;
        } catch (\Throwable $erro) {
            $pdo->rollBack();
            throw $erro;
        }
    }
}
