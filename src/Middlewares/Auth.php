<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\ErroApp;
use App\Core\Requisicao;

/**
 * "Guardas" de acesso, usadas nas rotas (src/rotas.php).
 * Esconder o menu no front é só usabilidade: a proteção de verdade é esta.
 */
final class Auth
{
    /** 401 se não houver ninguém logado */
    public static function login(): \Closure
    {
        return function (Requisicao $req): void {
            if (!$req->usuario()) throw new ErroApp('Faça login para continuar', 401);
        };
    }

    /** 403 se o perfil do usuário logado não estiver na lista */
    public static function perfil(string ...$perfisPermitidos): \Closure
    {
        return function (Requisicao $req) use ($perfisPermitidos): void {
            if (!in_array($req->usuario()['perfil'] ?? null, $perfisPermitidos, true)) {
                throw new ErroApp('Você não tem permissão para esta ação', 403);
            }
        };
    }
}
