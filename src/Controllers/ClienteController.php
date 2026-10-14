<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\ClienteModel;
use App\Utils\Cpf;
use PDOException;

final class ClienteController
{
    public static function listar(Requisicao $req): void
    {
        $clientes = ClienteModel::listar((string) ($req->query['busca'] ?? ''));
        // LGPD (RNF06): a listagem mostra o CPF mascarado
        Resposta::json(array_map(fn ($c) => [...$c, 'cpfCliente' => Cpf::mascarar($c['cpfCliente'])], $clientes));
    }

    public static function buscar(Requisicao $req): void
    {
        $cliente = ClienteModel::buscarPorId((int) $req->param('id'));
        if (!$cliente) throw new ErroApp('Cliente não encontrado', 404);
        Resposta::json($cliente);
    }

    public static function criar(Requisicao $req): void
    {
        $dados = Validacoes::cliente($req->corpo);
        try {
            Resposta::json(['idCliente' => ClienteModel::criar($dados)], 201);
        } catch (PDOException $erro) {
            // UNIQUE do CPF: o banco garante, mesmo com dois cadastros ao mesmo tempo
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('CPF já cadastrado', 409);
            throw $erro;
        }
    }

    public static function atualizar(Requisicao $req): void
    {
        $dados = Validacoes::cliente($req->corpo);
        try {
            if (!ClienteModel::atualizar((int) $req->param('id'), $dados)) throw new ErroApp('Cliente não encontrado', 404);
            Resposta::json(['mensagem' => 'Cliente atualizado']);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('CPF já cadastrado', 409);
            throw $erro;
        }
    }

    public static function inativar(Requisicao $req): void
    {
        if (!ClienteModel::inativar((int) $req->param('id'))) throw new ErroApp('Cliente não encontrado', 404);
        Resposta::vazio();
    }

    public static function anonimizar(Requisicao $req): void
    {
        if (!ClienteModel::anonimizar((int) $req->param('id'))) throw new ErroApp('Cliente não encontrado', 404);
        Resposta::vazio();
    }
}
