<?php
declare(strict_types=1);

namespace App\Core;

/** Envia respostas para o navegador */
final class Resposta
{
    public static function json(mixed $dados, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** 204 No Content: deu certo e não há nada para devolver */
    public static function vazio(): void
    {
        http_response_code(204);
    }
}
