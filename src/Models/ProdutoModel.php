<?php
declare(strict_types=1);

namespace App\Models;

final class ProdutoModel extends Model
{
    private const SELECT_VARIACAO = '
        SELECT p.idProduto, p.codigoProduto, p.nomeProduto, p.idCategoria, c.nomeCategoria,
               p.marcaProduto, p.generoProduto, p.precoVenda, p.fotoProduto,
               v.idVariacao, v.tamanho, v.cor, v.codigoBarras, v.quantidadeEstoque,
               v.estoqueMinimo, v.localizacao
          FROM tb_produto p
          JOIN tb_categoria c ON c.idCategoria = p.idCategoria
          LEFT JOIN tb_produto_variacao v ON v.idProduto = p.idProduto';

    /**
     * RF17: consulta com filtros. Cada filtro só entra no SQL se foi informado,
     * mas os VALORES vão sempre pelos placeholders (?).
     */
    public static function buscar(array $filtros = []): array
    {
        $condicoes = ['p.statusProduto = TRUE'];
        $valores = [];

        if (!empty($filtros['termo'])) {
            $condicoes[] = '(p.nomeProduto LIKE ? OR p.codigoProduto = ? OR v.codigoBarras = ?)';
            array_push($valores, "%{$filtros['termo']}%", $filtros['termo'], $filtros['termo']);
        }
        if (!empty($filtros['idCategoria'])) { $condicoes[] = 'p.idCategoria = ?'; $valores[] = $filtros['idCategoria']; }
        if (!empty($filtros['tamanho'])) { $condicoes[] = 'v.tamanho = ?'; $valores[] = $filtros['tamanho']; }
        if (!empty($filtros['cor'])) { $condicoes[] = 'v.cor LIKE ?'; $valores[] = "%{$filtros['cor']}%"; }
        if (!empty($filtros['generoProduto'])) { $condicoes[] = 'p.generoProduto = ?'; $valores[] = $filtros['generoProduto']; }
        if (!empty($filtros['precoMinimo'])) { $condicoes[] = 'p.precoVenda >= ?'; $valores[] = $filtros['precoMinimo']; }
        if (!empty($filtros['precoMaximo'])) { $condicoes[] = 'p.precoVenda <= ?'; $valores[] = $filtros['precoMaximo']; }
        if (($filtros['somenteDisponivel'] ?? '') === 'true') $condicoes[] = 'v.quantidadeEstoque > 0';

        $linhas = self::todos(
            self::SELECT_VARIACAO . ' WHERE ' . implode(' AND ', $condicoes) . '
              ORDER BY p.nomeProduto, v.cor, v.tamanho LIMIT 300',
            $valores
        );

        // Agrupa as variações (grade) dentro de cada produto
        $produtos = [];
        foreach ($linhas as $l) {
            $id = $l['idProduto'];
            $produtos[$id] ??= [
                'idProduto' => $l['idProduto'], 'codigoProduto' => $l['codigoProduto'], 'nomeProduto' => $l['nomeProduto'],
                'idCategoria' => $l['idCategoria'], 'nomeCategoria' => $l['nomeCategoria'], 'marcaProduto' => $l['marcaProduto'],
                'generoProduto' => $l['generoProduto'], 'precoVenda' => $l['precoVenda'], 'fotoProduto' => $l['fotoProduto'],
                'variacoes' => [],
            ];
            if ($l['idVariacao']) {
                $produtos[$id]['variacoes'][] = [
                    'idVariacao' => $l['idVariacao'], 'tamanho' => $l['tamanho'], 'cor' => $l['cor'],
                    'codigoBarras' => $l['codigoBarras'], 'quantidadeEstoque' => $l['quantidadeEstoque'],
                    'estoqueMinimo' => $l['estoqueMinimo'], 'localizacao' => $l['localizacao'],
                ];
            }
        }
        return array_values($produtos);
    }

    public static function buscarPorId(int $id): ?array
    {
        $produto = self::um('SELECT * FROM tb_produto WHERE idProduto = ?', [$id]);
        if (!$produto) return null;
        $produto['variacoes'] = self::todos('SELECT * FROM tb_produto_variacao WHERE idProduto = ? ORDER BY cor, tamanho', [$id]);
        return $produto;
    }

    // PDV: leitor de código de barras → variação com o preço já com promoção (prévia)
    public static function buscarPorCodigoBarras(string $codigo): ?array
    {
        $variacao = self::um(self::SELECT_VARIACAO . ' WHERE v.codigoBarras = ? AND p.statusProduto = TRUE', [$codigo]);
        return $variacao ? self::comPromocao($variacao) : null;
    }

    public static function buscarVariacao(int $idVariacao): ?array
    {
        $variacao = self::um(self::SELECT_VARIACAO . ' WHERE v.idVariacao = ? AND p.statusProduto = TRUE', [$idVariacao]);
        return $variacao ? self::comPromocao($variacao) : null;
    }

    private static function comPromocao(array $variacao): array
    {
        $promo = PromocaoModel::descontoUnitario($variacao);
        return [...$variacao, 'descontoPromocao' => $promo['desconto'], 'nomePromocao' => $promo['promocao']];
    }

    private static function valores(array $p): array
    {
        return [
            self::ouNulo($p['codigoProduto'] ?? null), $p['nomeProduto'], self::ouNulo($p['descricaoProduto'] ?? null),
            $p['idCategoria'], $p['idFornecedor'], self::ouNulo($p['marcaProduto'] ?? null),
            self::ouNulo($p['generoProduto'] ?? null), self::ouNulo($p['materialProduto'] ?? null),
            $p['precoCusto'], $p['precoVenda'], self::ouNulo($p['fotoProduto'] ?? null),
        ];
    }

    public static function criar(array $p): int
    {
        return self::inserir(
            'INSERT INTO tb_produto
               (codigoProduto, nomeProduto, descricaoProduto, idCategoria, idFornecedor, marcaProduto,
                generoProduto, materialProduto, precoCusto, precoVenda, fotoProduto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            self::valores($p)
        );
    }

    public static function atualizar(int $id, array $p): bool
    {
        return self::executar(
            'UPDATE tb_produto
                SET codigoProduto = ?, nomeProduto = ?, descricaoProduto = ?, idCategoria = ?,
                    idFornecedor = ?, marcaProduto = ?, generoProduto = ?, materialProduto = ?,
                    precoCusto = ?, precoVenda = ?, fotoProduto = ?
              WHERE idProduto = ?',
            [...self::valores($p), $id]
        ) > 0;
    }

    public static function inativar(int $id): bool
    {
        return self::executar('UPDATE tb_produto SET statusProduto = FALSE WHERE idProduto = ?', [$id]) > 0;
    }

    // RF05: a quantidade NÃO é editada aqui; só muda por entrada (RF08), venda, troca ou cancelamento.
    public static function criarVariacao(int $idProduto, array $v): int
    {
        return self::inserir(
            'INSERT INTO tb_produto_variacao (idProduto, tamanho, cor, codigoBarras, estoqueMinimo, localizacao)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$idProduto, $v['tamanho'], $v['cor'], self::ouNulo($v['codigoBarras'] ?? null),
             self::ouNulo($v['estoqueMinimo'] ?? null) ?? 2, self::ouNulo($v['localizacao'] ?? null)]
        );
    }

    public static function atualizarVariacao(int $idVariacao, array $v): bool
    {
        return self::executar(
            'UPDATE tb_produto_variacao
                SET tamanho = ?, cor = ?, codigoBarras = ?, estoqueMinimo = ?, localizacao = ?
              WHERE idVariacao = ?',
            [$v['tamanho'], $v['cor'], self::ouNulo($v['codigoBarras'] ?? null),
             self::ouNulo($v['estoqueMinimo'] ?? null) ?? 2, self::ouNulo($v['localizacao'] ?? null), $idVariacao]
        ) > 0;
    }
}
