<?php
declare(strict_types=1);

namespace App\Config;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Sessão de login guardada na tabela `sessions` do MySQL (RNF05).
 * - Reiniciar o servidor não desloga ninguém.
 * - Cada requisição renova o prazo: a sessão só expira após 15 minutos SEM USO.
 */
final class Sessao implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public const DURACAO = 15 * 60;   // 15 minutos
    public const NOME_COOKIE = 'loja_sid';   // o PHP não aceita ponto no nome da sessão

    /**
     * Chamado em toda requisição da API: só abre a sessão se o navegador já mandou o cookie.
     * Visitante sem login não cria sessão nenhuma no banco.
     */
    public static function iniciar(): void
    {
        if (isset($_COOKIE[self::NOME_COOKIE])) self::abrir();
    }

    /**
     * Chamado no login: garante uma sessão com id NOVO
     * (evita "session fixation": um id plantado antes do login não é reaproveitado).
     */
    public static function novaSessaoDeLogin(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        else self::abrir();
    }

    private static function abrir(): void
    {
        ini_set('session.use_strict_mode', '1');   // não aceita ids de sessão inventados
        ini_set('session.lazy_write', '0');        // grava a cada requisição → prazo sempre renovado
        ini_set('session.gc_maxlifetime', (string) self::DURACAO);
        session_name(self::NOME_COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,          // o JavaScript da página não lê o cookie
            'samesite' => 'Lax',         // proteção contra CSRF
            'secure' => producao(),      // em produção, só por HTTPS
        ]);
        session_set_save_handler(new self(), true);
        session_start();
    }

    public function open(string $caminho, string $nome): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $consulta = Database::conexao()->prepare(
            'SELECT data FROM sessions WHERE session_id = ? AND expires > UNIX_TIMESTAMP()'
        );
        $consulta->execute([$id]);
        return (string) ($consulta->fetchColumn() ?: '');
    }

    public function write(string $id, string $dados): bool
    {
        return Database::conexao()->prepare(
            'REPLACE INTO sessions (session_id, expires, data) VALUES (?, UNIX_TIMESTAMP() + ?, ?)'
        )->execute([$id, self::DURACAO, $dados]);
    }

    public function destroy(string $id): bool
    {
        return Database::conexao()->prepare('DELETE FROM sessions WHERE session_id = ?')->execute([$id]);
    }

    // Modo estrito: só aceita ids que existem no banco e não expiraram
    public function validateId(string $id): bool
    {
        $consulta = Database::conexao()->prepare(
            'SELECT 1 FROM sessions WHERE session_id = ? AND expires > UNIX_TIMESTAMP()'
        );
        $consulta->execute([$id]);
        return (bool) $consulta->fetchColumn();
    }

    public function updateTimestamp(string $id, string $dados): bool
    {
        return $this->write($id, $dados);
    }

    public function gc(int $tempoMaximo): int|false
    {
        return Database::conexao()->exec('DELETE FROM sessions WHERE expires < UNIX_TIMESTAMP()');
    }
}
