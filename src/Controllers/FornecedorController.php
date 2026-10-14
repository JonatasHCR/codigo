<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\FornecedorModel;
use PDOException;

final class FornecedorController
{
    public static function listar(Requisicao $req): void
    {
        Resposta::json(FornecedorModel::listar((string) ($req->query['busca'] ?? '')));
    }

    public static function buscar(Requisicao $req): void
    {
        $fornecedor = FornecedorModel::buscarPorId((int) $req->param('id'));
        if (!$fornecedor) throw new ErroApp('Fornecedor não encontrado', 404);
        Resposta::json($fornecedor);
    }

    public static function criar(Requisicao $req): void
    {
        $dados = Validacoes::fornecedor($req->corpo);
        try {
            Resposta::json(['idFornecedor' => FornecedorModel::criar($dados)], 201);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('CNPJ já cadastrado', 409);
            throw $erro;
        }
    }

    public static function atualizar(Requisicao $req): void
    {
        $dados = Validacoes::fornecedor($req->corpo);
        try {
            if (!FornecedorModel::atualizar((int) $req->param('id'), $dados)) throw new ErroApp('Fornecedor não encontrado', 404);
            Resposta::json(['mensagem' => 'Fornecedor atualizado']);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('CNPJ já cadastrado', 409);
            throw $erro;
        }
    }

    public static function inativar(Requisicao $req): void
    {
        if (!FornecedorModel::inativar((int) $req->param('id'))) throw new ErroApp('Fornecedor não encontrado', 404);
        Resposta::vazio();
    }
}
