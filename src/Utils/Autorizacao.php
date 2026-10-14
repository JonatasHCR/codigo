<?php
declare(strict_types=1);

namespace App\Utils;

use App\Config\Database;
use App\Core\ErroApp;

final class Autorizacao
{
    private const PERFIS_AUTORIZADORES = ['GERENTE', 'ADMIN'];

    /**
     * Regras que exigem "senha do gerente" (RF11 desconto > 10%, RF12 cancelamento).
     * Se quem está logado já é gerente/admin, ele mesmo autoriza.
     * Senão, o front envia { login, senha } de um gerente, conferidos com bcrypt.
     * Devolve o idUsuario de quem autorizou.
     */
    public static function autorizarGerente(array $usuarioLogado, ?array $autorizacao): int
    {
        if (in_array($usuarioLogado['perfil'], self::PERFIS_AUTORIZADORES, true)) return $usuarioLogado['id'];

        if (empty($autorizacao['login']) || empty($autorizacao['senha'])) {
            throw new ErroApp('Esta operação precisa da autorização de um gerente', 403);
        }
        $consulta = Database::conexao()->prepare(
            'SELECT idUsuario, senhaHash, perfilAcesso FROM tb_usuario
              WHERE loginUsuario = ? AND statusUsuario = TRUE AND tentativasFalhas < 3'
        );
        $consulta->execute([$autorizacao['login']]);
        $gerente = $consulta->fetch();

        $senhaCorreta = $gerente && password_verify((string) $autorizacao['senha'], $gerente['senhaHash']);
        if (!$senhaCorreta || !in_array($gerente['perfilAcesso'], self::PERFIS_AUTORIZADORES, true)) {
            throw new ErroApp('Autorização do gerente inválida', 403);
        }
        return (int) $gerente['idUsuario'];
    }
}
