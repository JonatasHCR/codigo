<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\PromocaoModel;

final class PromocaoController
{
    public static function listar(Requisicao $req): void
    {
        Resposta::json(PromocaoModel::listar());
    }

    public static function buscar(Requisicao $req): void
    {
        $promocao = PromocaoModel::buscarPorId((int) $req->param('id'));
        if (!$promocao) throw new ErroApp('Promoção não encontrada', 404);
        Resposta::json($promocao);
    }

    public static function criar(Requisicao $req): void
    {
        Resposta::json(['idPromocao' => PromocaoModel::criar(Validacoes::promocao($req->corpo))], 201);
    }

    public static function atualizar(Requisicao $req): void
    {
        if (!PromocaoModel::atualizar((int) $req->param('id'), Validacoes::promocao($req->corpo))) {
            throw new ErroApp('Promoção não encontrada', 404);
        }
        Resposta::json(['mensagem' => 'Promoção atualizada']);
    }

    public static function desativar(Requisicao $req): void
    {
        if (!PromocaoModel::desativar((int) $req->param('id'))) throw new ErroApp('Promoção não encontrada', 404);
        Resposta::vazio();
    }
}
