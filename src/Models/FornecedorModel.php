<?php
declare(strict_types=1);

namespace App\Models;

final class FornecedorModel extends Model
{
    public static function listar(string $busca = ''): array
    {
        return self::todos(
            'SELECT idFornecedor, razaoSocial, nomeFantasia, cnpjFornecedor, telefoneFornecedor,
                    emailFornecedor, nomeContato, cidadeFornecedor, ufFornecedor
               FROM tb_fornecedor
              WHERE statusFornecedor = TRUE AND (razaoSocial LIKE ? OR nomeFantasia LIKE ?)
              ORDER BY razaoSocial',
            ["%$busca%", "%$busca%"]
        );
    }

    public static function buscarPorId(int $id): ?array
    {
        return self::um('SELECT * FROM tb_fornecedor WHERE idFornecedor = ?', [$id]);
    }

    private static function valores(array $f): array
    {
        return [
            $f['razaoSocial'], self::ouNulo($f['nomeFantasia'] ?? null), $f['cnpjFornecedor'],
            self::ouNulo($f['inscricaoEstadual'] ?? null), self::ouNulo($f['telefoneFornecedor'] ?? null),
            self::ouNulo($f['emailFornecedor'] ?? null), self::ouNulo($f['nomeContato'] ?? null),
            self::ouNulo($f['enderecoFornecedor'] ?? null), self::ouNulo($f['cidadeFornecedor'] ?? null),
            self::ouNulo($f['ufFornecedor'] ?? null),
        ];
    }

    public static function criar(array $f): int
    {
        return self::inserir(
            'INSERT INTO tb_fornecedor
               (razaoSocial, nomeFantasia, cnpjFornecedor, inscricaoEstadual, telefoneFornecedor,
                emailFornecedor, nomeContato, enderecoFornecedor, cidadeFornecedor, ufFornecedor)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            self::valores($f)
        );
    }

    public static function atualizar(int $id, array $f): bool
    {
        return self::executar(
            'UPDATE tb_fornecedor
                SET razaoSocial = ?, nomeFantasia = ?, cnpjFornecedor = ?, inscricaoEstadual = ?,
                    telefoneFornecedor = ?, emailFornecedor = ?, nomeContato = ?,
                    enderecoFornecedor = ?, cidadeFornecedor = ?, ufFornecedor = ?
              WHERE idFornecedor = ?',
            [...self::valores($f), $id]
        ) > 0;
    }

    public static function inativar(int $id): bool
    {
        return self::executar('UPDATE tb_fornecedor SET statusFornecedor = FALSE WHERE idFornecedor = ?', [$id]) > 0;
    }
}
