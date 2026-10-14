<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Dinheiro;

final class PromocaoModel extends Model
{
    public static function listar(): array
    {
        return self::todos(
            'SELECT pr.*, c.nomeCategoria, p.nomeProduto,
                    (pr.statusPromocao AND CURDATE() BETWEEN pr.dataInicio AND pr.dataFim) AS ativaHoje
               FROM tb_promocao pr
               LEFT JOIN tb_categoria c ON c.idCategoria = pr.idCategoria
               LEFT JOIN tb_produto p   ON p.idProduto   = pr.idProduto
              ORDER BY pr.dataFim DESC'
        );
    }

    public static function buscarPorId(int $id): ?array
    {
        return self::um('SELECT * FROM tb_promocao WHERE idPromocao = ?', [$id]);
    }

    private static function valores(array $p): array
    {
        return [$p['nomePromocao'], $p['tipoDesconto'], $p['valorDesconto'], $p['dataInicio'], $p['dataFim'],
                self::ouNulo($p['idCategoria'] ?? null), self::ouNulo($p['idProduto'] ?? null)];
    }

    public static function criar(array $p): int
    {
        return self::inserir(
            'INSERT INTO tb_promocao (nomePromocao, tipoDesconto, valorDesconto, dataInicio, dataFim, idCategoria, idProduto)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            self::valores($p)
        );
    }

    public static function atualizar(int $id, array $p): bool
    {
        return self::executar(
            'UPDATE tb_promocao
                SET nomePromocao = ?, tipoDesconto = ?, valorDesconto = ?, dataInicio = ?, dataFim = ?,
                    idCategoria = ?, idProduto = ?
              WHERE idPromocao = ?',
            [...self::valores($p), $id]
        ) > 0;
    }

    public static function desativar(int $id): bool
    {
        return self::executar('UPDATE tb_promocao SET statusPromocao = FALSE WHERE idPromocao = ?', [$id]) > 0;
    }

    /**
     * RF11: melhor promoção ativa HOJE para um produto (direta ou pela categoria).
     * Devolve o desconto por unidade, nunca maior que o próprio preço.
     * Dentro de uma venda, roda na mesma conexão — e portanto na mesma transação.
     */
    public static function descontoUnitario(array $produto): array
    {
        $promocoes = self::todos(
            'SELECT idPromocao, nomePromocao, tipoDesconto, valorDesconto
               FROM tb_promocao
              WHERE statusPromocao = TRUE
                AND CURDATE() BETWEEN dataInicio AND dataFim
                AND (idProduto = ? OR idCategoria = ?)',
            [$produto['idProduto'], $produto['idCategoria']]
        );
        $preco = (float) $produto['precoVenda'];
        $melhor = ['desconto' => 0.0, 'promocao' => null];
        foreach ($promocoes as $p) {
            $valor = (float) $p['valorDesconto'];
            $desconto = Dinheiro::arredondar($p['tipoDesconto'] === 'PERCENTUAL' ? $preco * $valor / 100 : $valor);
            if ($desconto > $melhor['desconto']) {
                $melhor = ['desconto' => min($desconto, $preco), 'promocao' => $p['nomePromocao']];
            }
        }
        return $melhor;
    }
}
