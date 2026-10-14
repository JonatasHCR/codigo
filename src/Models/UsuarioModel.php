<?php
declare(strict_types=1);

namespace App\Models;

final class UsuarioModel extends Model
{
    // ---------- Login (RF07) ----------
    public static function buscarPorLogin(string $login): ?array
    {
        return self::um(
            'SELECT idUsuario, nomeUsuario, senhaHash, perfilAcesso, tentativasFalhas, statusUsuario
               FROM tb_usuario WHERE loginUsuario = ?', [$login]
        );
    }

    public static function registrarFalha(int $idUsuario): void
    {
        // A soma é feita no banco: duas tentativas ao mesmo tempo contam as duas
        self::executar('UPDATE tb_usuario SET tentativasFalhas = tentativasFalhas + 1 WHERE idUsuario = ?', [$idUsuario]);
    }

    public static function zerarFalhas(int $idUsuario): void
    {
        self::executar('UPDATE tb_usuario SET tentativasFalhas = 0 WHERE idUsuario = ?', [$idUsuario]);
    }

    public static function registrarAcesso(int $idUsuario, string $ip, bool $sucesso): int
    {
        return self::inserir(
            'INSERT INTO tb_log_acesso (idUsuario, dataHoraLogin, enderecoIp, sucessoLogin) VALUES (?, NOW(), ?, ?)',
            [$idUsuario, $ip, (int) $sucesso]
        );
    }

    public static function registrarLogout(int $idLog): void
    {
        self::executar('UPDATE tb_log_acesso SET dataHoraLogout = NOW() WHERE idLog = ?', [$idLog]);
    }

    // ---------- Cadastro de funcionários (RF06) ----------
    private const CAMPOS_LISTA = 'idUsuario, nomeUsuario, cpfUsuario, loginUsuario, perfilAcesso,
        percentualComissao, dataAdmissao, tentativasFalhas, statusUsuario';

    public static function listar(): array
    {
        return self::todos('SELECT ' . self::CAMPOS_LISTA . ' FROM tb_usuario ORDER BY nomeUsuario');
    }

    public static function listarVendedores(): array
    {
        return self::todos(
            "SELECT idUsuario, nomeUsuario, perfilAcesso FROM tb_usuario
              WHERE statusUsuario = TRUE AND perfilAcesso IN ('VENDEDOR', 'GERENTE', 'CAIXA', 'ADMIN')
              ORDER BY nomeUsuario"
        );
    }

    public static function buscarPorId(int $id): ?array
    {
        return self::um('SELECT ' . self::CAMPOS_LISTA . ' FROM tb_usuario WHERE idUsuario = ?', [$id]);
    }

    public static function criar(array $u, string $senhaHash): int
    {
        return self::inserir(
            'INSERT INTO tb_usuario (nomeUsuario, cpfUsuario, loginUsuario, senhaHash, perfilAcesso, percentualComissao, dataAdmissao)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$u['nomeUsuario'], self::ouNulo($u['cpfUsuario'] ?? null), $u['loginUsuario'], $senhaHash, $u['perfilAcesso'],
             ($u['percentualComissao'] ?? 0) ?: 0, self::ouNulo($u['dataAdmissao'] ?? null)]
        );
    }

    /** $senhaHash = null mantém a senha atual (COALESCE) */
    public static function atualizar(int $id, array $u, ?string $senhaHash): bool
    {
        return self::executar(
            'UPDATE tb_usuario
                SET nomeUsuario = ?, cpfUsuario = ?, loginUsuario = ?, perfilAcesso = ?,
                    percentualComissao = ?, dataAdmissao = ?, senhaHash = COALESCE(?, senhaHash)
              WHERE idUsuario = ?',
            [$u['nomeUsuario'], self::ouNulo($u['cpfUsuario'] ?? null), $u['loginUsuario'], $u['perfilAcesso'],
             ($u['percentualComissao'] ?? 0) ?: 0, self::ouNulo($u['dataAdmissao'] ?? null), $senhaHash, $id]
        ) > 0;
    }

    public static function alterarStatus(int $id, bool $ativo): bool
    {
        return self::executar('UPDATE tb_usuario SET statusUsuario = ? WHERE idUsuario = ?', [(int) $ativo, $id]) > 0;
    }
}
