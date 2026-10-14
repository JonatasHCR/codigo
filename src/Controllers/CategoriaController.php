<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\CategoriaModel;
use PDOException;

final class CategoriaController
{
    public static function listar(Requisicao $req): void
    {
        Resposta::json(CategoriaModel::listar());
    }

    public static function buscar(Requisicao $req): void
    {
        $categoria = CategoriaModel::buscarPorId((int) $req->param('id'));
        if (!$categoria) throw new ErroApp('Categoria não encontrada', 404);
        Resposta::json($categoria);
    }

    public static function criar(Requisicao $req): void
    {
        $dados = Validacoes::categoria($req->corpo);
        try {
            Resposta::json(['idCategoria' => CategoriaModel::criar($dados)], 201);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('Já existe uma categoria com esse nome', 409);
            throw $erro;
        }
    }

    public static function atualizar(Requisicao $req): void
    {
        $dados = Validacoes::categoria($req->corpo);
        try {
            if (!CategoriaModel::atualizar((int) $req->param('id'), $dados)) throw new ErroApp('Categoria não encontrada', 404);
            Resposta::json(['mensagem' => 'Categoria atualizada']);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('Já existe uma categoria com esse nome', 409);
            throw $erro;
        }
    }

    public static function inativar(Requisicao $req): void
    {
        if (!CategoriaModel::inativar((int) $req->param('id'))) throw new ErroApp('Categoria não encontrada', 404);
        Resposta::vazio();
    }
}
