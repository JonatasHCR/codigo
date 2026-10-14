<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\TrocaModel;

final class TrocaController
{
    // RF13
    public static function registrar(Requisicao $req): void
    {
        $dados = Validacoes::troca($req->corpo);
        Resposta::json(TrocaModel::registrar($dados, $req->usuario()['id']), 201);
    }

    public static function listar(Requisicao $req): void
    {
        Resposta::json(TrocaModel::listar());
    }

    // RF14
    public static function vale(Requisicao $req): void
    {
        $vale = TrocaModel::buscarVale(strtoupper($req->param('codigo')));
        if (!$vale) throw new ErroApp('Vale-troca não encontrado', 404);
        Resposta::json($vale);
    }
}
