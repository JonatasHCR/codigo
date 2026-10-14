<?php
declare(strict_types=1);

namespace App\Models;

final class ClienteModel extends Model
{
    public static function listar(string $busca = ''): array
    {
        $cpf = preg_replace('/\D/', '', $busca);
        return self::todos(
            "SELECT idCliente, nomeCliente, cpfCliente, telefoneCliente, emailCliente
               FROM tb_cliente
              WHERE statusCliente = TRUE AND (nomeCliente LIKE ? OR (? <> '' AND cpfCliente LIKE ?))
              ORDER BY nomeCliente
              LIMIT 50",
            ["%$busca%", $cpf, "$cpf%"]
        );
    }

    public static function buscarPorId(int $id): ?array
    {
        return self::um('SELECT * FROM tb_cliente WHERE idCliente = ?', [$id]);
    }

    private static function valores(array $c): array
    {
        return [
            $c['nomeCliente'], $c['cpfCliente'], self::ouNulo($c['dataNascimento'] ?? null), $c['telefoneCliente'],
            self::ouNulo($c['emailCliente'] ?? null), self::ouNulo($c['cepCliente'] ?? null),
            self::ouNulo($c['enderecoCliente'] ?? null), self::ouNulo($c['numeroEndereco'] ?? null),
            self::ouNulo($c['bairroCliente'] ?? null), self::ouNulo($c['cidadeCliente'] ?? null),
            self::ouNulo($c['ufCliente'] ?? null),
        ];
    }

    public static function criar(array $c): int
    {
        $consentimento = (int) !empty($c['consentimentoLgpd']);
        $id = self::inserir(
            'INSERT INTO tb_cliente
               (nomeCliente, cpfCliente, dataNascimento, telefoneCliente, emailCliente,
                cepCliente, enderecoCliente, numeroEndereco, bairroCliente, cidadeCliente, ufCliente,
                consentimentoLgpd, dataConsentimento)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(?, NOW(), NULL))',
            [...self::valores($c), $consentimento, $consentimento]
        );
        // Todo cliente cadastrado já entra no programa de fidelidade (RF18)
        self::inserir('INSERT INTO tb_fidelidade (idCliente) VALUES (?)', [$id]);
        return $id;
    }

    public static function atualizar(int $id, array $c): bool
    {
        $consentimento = (int) !empty($c['consentimentoLgpd']);
        return self::executar(
            'UPDATE tb_cliente
                SET nomeCliente = ?, cpfCliente = ?, dataNascimento = ?, telefoneCliente = ?,
                    emailCliente = ?, cepCliente = ?, enderecoCliente = ?, numeroEndereco = ?,
                    bairroCliente = ?, cidadeCliente = ?, ufCliente = ?,
                    dataConsentimento = IF(? AND NOT consentimentoLgpd, NOW(), dataConsentimento),
                    consentimentoLgpd = ?
              WHERE idCliente = ?',
            [...self::valores($c), $consentimento, $consentimento, $id]
        ) > 0;
    }

    public static function inativar(int $id): bool
    {
        return self::executar('UPDATE tb_cliente SET statusCliente = FALSE WHERE idCliente = ?', [$id]) > 0;
    }

    /**
     * RNF06 (LGPD): apaga os dados pessoais, mas mantém o registro para o histórico de vendas.
     * O CPF vira um código único que não é um CPF válido.
     */
    public static function anonimizar(int $id): bool
    {
        return self::executar(
            "UPDATE tb_cliente
                SET nomeCliente = CONCAT('Cliente anonimizado #', idCliente),
                    cpfCliente = LPAD(idCliente, 11, '0'),
                    dataNascimento = NULL, telefoneCliente = '00000000000', emailCliente = NULL,
                    cepCliente = NULL, enderecoCliente = NULL, numeroEndereco = NULL,
                    bairroCliente = NULL, cidadeCliente = NULL, ufCliente = NULL,
                    consentimentoLgpd = FALSE, statusCliente = FALSE
              WHERE idCliente = ?", [$id]
        ) > 0;
    }
}
