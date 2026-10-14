<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\UsuarioModel;

final class UsuarioController
{
    public static function listar(Requisicao $req): void
    {
        Resposta::json(UsuarioModel::listar());
    }

    // Lista de vendedores para o PDV (RF09: informar o vendedor)
    public static function vendedores(Requisicao $req): void
    {
        Resposta::json(UsuarioModel::listarVendedores());
    }

    public static function buscar(Requisicao $req): void
    {
        $usuario = UsuarioModel::buscarPorId((int) $req->param('id'));
        if (!$usuario) throw new ErroApp('Usuário não encontrado', 404);
        Resposta::json($usuario);
    }

    public static function criar(Requisicao $req): void
    {
        $dados = Validacoes::usuario($req->corpo, senhaObrigatoria: true);
        $senhaHash = password_hash($dados['senha'], PASSWORD_BCRYPT, ['cost' => 10]);
        Resposta::json(['idUsuario' => UsuarioModel::criar($dados, $senhaHash)], 201);
    }

    public static function atualizar(Requisicao $req): void
    {
        $dados = Validacoes::usuario($req->corpo, senhaObrigatoria: false);
        $senhaHash = !empty($dados['senha']) ? password_hash($dados['senha'], PASSWORD_BCRYPT, ['cost' => 10]) : null;
        if (!UsuarioModel::atualizar((int) $req->param('id'), $dados, $senhaHash)) throw new ErroApp('Usuário não encontrado', 404);
        Resposta::json(['mensagem' => 'Usuário atualizado']);
    }

    public static function inativar(Requisicao $req): void
    {
        if ((int) $req->param('id') === $req->usuario()['id']) {
            throw new ErroApp('Você não pode inativar o próprio usuário');
        }
        if (!UsuarioModel::alterarStatus((int) $req->param('id'), false)) throw new ErroApp('Usuário não encontrado', 404);
        Resposta::vazio();
    }

    public static function reativar(Requisicao $req): void
    {
        if (!UsuarioModel::alterarStatus((int) $req->param('id'), true)) throw new ErroApp('Usuário não encontrado', 404);
        Resposta::vazio();
    }

    public static function desbloquear(Requisicao $req): void
    {
        UsuarioModel::zerarFalhas((int) $req->param('id'));
        Resposta::vazio();
    }
}
