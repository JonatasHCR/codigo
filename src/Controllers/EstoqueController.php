<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\EstoqueModel;

final class EstoqueController
{
    // RF08
    public static function registrarEntrada(Requisicao $req): void
    {
        $dados = Validacoes::entrada($req->corpo);
        Resposta::json(EstoqueModel::registrarEntrada($dados, $req->usuario()['id']), 201);
    }

    public static function listarEntradas(Requisicao $req): void
    {
        Resposta::json(EstoqueModel::listarEntradas());
    }

    // RF16
    public static function listarAlertas(Requisicao $req): void
    {
        Resposta::json(EstoqueModel::listarAlertas());
    }
}
