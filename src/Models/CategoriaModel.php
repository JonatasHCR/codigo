<?php
declare(strict_types=1);

namespace App\Models;

final class CategoriaModel extends Model
{
    public static function listar(): array
    {
        return self::todos(
            'SELECT idCategoria, nomeCategoria, tipoCategoria, descricaoCategoria
               FROM tb_categoria WHERE statusCategoria = TRUE ORDER BY tipoCategoria, nomeCategoria'
        );
    }

    public static function buscarPorId(int $id): ?array
    {
        return self::um('SELECT * FROM tb_categoria WHERE idCategoria = ?', [$id]);
    }

    public static function criar(array $c): int
    {
        return self::inserir(
            'INSERT INTO tb_categoria (nomeCategoria, tipoCategoria, descricaoCategoria) VALUES (?, ?, ?)',
            [$c['nomeCategoria'], $c['tipoCategoria'], self::ouNulo($c['descricaoCategoria'] ?? null)]
        );
    }

    public static function atualizar(int $id, array $c): bool
    {
        return self::executar(
            'UPDATE tb_categoria SET nomeCategoria = ?, tipoCategoria = ?, descricaoCategoria = ? WHERE idCategoria = ?',
            [$c['nomeCategoria'], $c['tipoCategoria'], self::ouNulo($c['descricaoCategoria'] ?? null), $id]
        ) > 0;
    }

    public static function inativar(int $id): bool
    {
        return self::executar('UPDATE tb_categoria SET statusCategoria = FALSE WHERE idCategoria = ?', [$id]) > 0;
    }
}
