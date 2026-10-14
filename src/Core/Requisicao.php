<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tudo o que chegou do navegador numa requisição:
 *  - metodo e caminho (ex.: GET /api/clientes/5)
 *  - params: partes variáveis da rota (:id → 5)
 *  - query:  o que vem depois do ? na URL ($_GET)
 *  - corpo:  o JSON enviado (já convertido em array)
 */
final class Requisicao
{
    public array $params = [];

    public function __construct(
        public readonly string $metodo,
        public readonly string $caminho,
        public readonly array $query,
        public readonly array $corpo,
    ) {}

    public static function atual(): self
    {
        $caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $corpo = json_decode(file_get_contents('php://input') ?: '', true);
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rtrim($caminho, '/') ?: '/',
            $_GET,
            is_array($corpo) ? $corpo : [],
        );
    }

    public function param(string $nome): string
    {
        return $this->params[$nome] ?? '';
    }

    /** Usuário logado (guardado na sessão pelo login) ou null */
    public function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
