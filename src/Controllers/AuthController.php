<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Sessao;
use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Models\UsuarioModel;

final class AuthController
{
    private const LOGIN_INVALIDO = 'Login ou senha inválidos';

    // RF07: login com bcrypt, bloqueio na 3ª falha e registro de acesso
    public static function login(Requisicao $req): void
    {
        $login = (string) ($req->corpo['login'] ?? '');
        $senha = (string) ($req->corpo['senha'] ?? '');
        if ($login === '' || $senha === '') throw new ErroApp('Informe login e senha', 400);

        $usuario = UsuarioModel::buscarPorLogin($login);
        if (!$usuario || !$usuario['statusUsuario']) throw new ErroApp(self::LOGIN_INVALIDO, 401);
        if ($usuario['tentativasFalhas'] >= 3) {
            throw new ErroApp('Usuário bloqueado. Procure o administrador.', 423);
        }

        $senhaCorreta = password_verify($senha, $usuario['senhaHash']);   // bcrypt
        $idLog = UsuarioModel::registrarAcesso($usuario['idUsuario'], $req->ip(), $senhaCorreta);

        if (!$senhaCorreta) {
            UsuarioModel::registrarFalha($usuario['idUsuario']);
            throw new ErroApp(self::LOGIN_INVALIDO, 401);
        }
        UsuarioModel::zerarFalhas($usuario['idUsuario']);

        Sessao::novaSessaoDeLogin();   // novo id de sessão: evita "session fixation"
        $_SESSION['usuario'] = [
            'id' => $usuario['idUsuario'],
            'nome' => $usuario['nomeUsuario'],
            'perfil' => $usuario['perfilAcesso'],
        ];
        $_SESSION['idLog'] = $idLog;

        Resposta::json(['nome' => $usuario['nomeUsuario'], 'perfil' => $usuario['perfilAcesso']]);
    }

    public static function logout(Requisicao $req): void
    {
        if (!empty($_SESSION['idLog'])) UsuarioModel::registrarLogout($_SESSION['idLog']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        setcookie(Sessao::NOME_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        Resposta::vazio();
    }

    public static function eu(Requisicao $req): void
    {
        Resposta::json($req->usuario());
    }
}
