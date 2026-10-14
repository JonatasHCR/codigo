<?php
declare(strict_types=1);

namespace App\Models;

/**
 * RF20: cada relatório = { titulo, colunas, sql }.
 * Uma consulta → três saídas (tela/JSON, PDF, CSV).
 */
final class RelatorioModel extends Model
{
    // Filtro de período que não perde o último dia (ver: a armadilha do BETWEEN com DATETIME)
    private static function periodo(string $coluna): string
    {
        return "$coluna >= ? AND $coluna < DATE_ADD(?, INTERVAL 1 DAY)";
    }

    public static function relatorios(): array
    {
        return [
            'vendas-periodo' => [
                'titulo' => 'Vendas por período',
                'colunas' => [
                    ['campo' => 'dia', 'titulo' => 'Dia', 'tipo' => 'data'],
                    ['campo' => 'quantidadeVendas', 'titulo' => 'Vendas', 'tipo' => 'numero'],
                    ['campo' => 'valorBruto', 'titulo' => 'Valor bruto', 'tipo' => 'moeda'],
                    ['campo' => 'valorDesconto', 'titulo' => 'Descontos', 'tipo' => 'moeda'],
                    ['campo' => 'valorTotal', 'titulo' => 'Total', 'tipo' => 'moeda'],
                ],
                'sql' => "SELECT DATE(dataHoraVenda) AS dia, COUNT(*) AS quantidadeVendas,
                                 SUM(valorBruto) AS valorBruto, SUM(valorDesconto) AS valorDesconto, SUM(valorTotal) AS valorTotal
                            FROM tb_venda
                           WHERE statusVenda = 'FINALIZADA' AND " . self::periodo('dataHoraVenda') . '
                           GROUP BY DATE(dataHoraVenda)
                           ORDER BY dia',
                'totalizar' => ['quantidadeVendas', 'valorBruto', 'valorDesconto', 'valorTotal'],
            ],
            'mais-vendidos' => [
                'titulo' => 'Produtos mais vendidos',
                'colunas' => [
                    ['campo' => 'nomeProduto', 'titulo' => 'Produto', 'peso' => 3],
                    ['campo' => 'tamanho', 'titulo' => 'Tamanho'],
                    ['campo' => 'cor', 'titulo' => 'Cor'],
                    ['campo' => 'quantidade', 'titulo' => 'Quantidade', 'tipo' => 'numero'],
                    ['campo' => 'total', 'titulo' => 'Total', 'tipo' => 'moeda'],
                ],
                'sql' => "SELECT p.nomeProduto, v.tamanho, v.cor, SUM(i.quantidade) AS quantidade, SUM(i.subtotal) AS total
                            FROM tb_item_venda i
                            JOIN tb_venda ve           ON ve.idVenda   = i.idVenda
                            JOIN tb_produto_variacao v ON v.idVariacao = i.idVariacao
                            JOIN tb_produto p          ON p.idProduto  = v.idProduto
                           WHERE ve.statusVenda = 'FINALIZADA' AND " . self::periodo('ve.dataHoraVenda') . '
                           GROUP BY v.idVariacao, p.nomeProduto, v.tamanho, v.cor
                           ORDER BY quantidade DESC
                           LIMIT 20',
                'totalizar' => ['quantidade', 'total'],
            ],
            'vendas-vendedor' => [
                'titulo' => 'Vendas por vendedor',
                'colunas' => [
                    ['campo' => 'nomeUsuario', 'titulo' => 'Vendedor', 'peso' => 2],
                    ['campo' => 'quantidadeVendas', 'titulo' => 'Vendas', 'tipo' => 'numero'],
                    ['campo' => 'valorTotal', 'titulo' => 'Total vendido', 'tipo' => 'moeda'],
                    ['campo' => 'ticketMedio', 'titulo' => 'Ticket médio', 'tipo' => 'moeda'],
                ],
                'sql' => "SELECT u.nomeUsuario, COUNT(v.idVenda) AS quantidadeVendas, SUM(v.valorTotal) AS valorTotal,
                                 ROUND(AVG(v.valorTotal), 2) AS ticketMedio
                            FROM tb_venda v JOIN tb_usuario u ON u.idUsuario = v.idUsuario
                           WHERE v.statusVenda = 'FINALIZADA' AND " . self::periodo('v.dataHoraVenda') . '
                           GROUP BY u.idUsuario, u.nomeUsuario
                           ORDER BY valorTotal DESC',
                'totalizar' => ['quantidadeVendas', 'valorTotal'],
            ],
            'comissoes' => [
                'titulo' => 'Comissões por vendedor',
                'colunas' => [
                    ['campo' => 'nomeUsuario', 'titulo' => 'Vendedor', 'peso' => 2],
                    ['campo' => 'valorTotal', 'titulo' => 'Total vendido', 'tipo' => 'moeda'],
                    ['campo' => 'percentualComissao', 'titulo' => 'Comissão (%)', 'tipo' => 'numero'],
                    ['campo' => 'comissao', 'titulo' => 'Valor da comissão', 'tipo' => 'moeda'],
                ],
                'sql' => "SELECT u.nomeUsuario, SUM(v.valorTotal) AS valorTotal, u.percentualComissao,
                                 ROUND(SUM(v.valorTotal) * u.percentualComissao / 100, 2) AS comissao
                            FROM tb_venda v JOIN tb_usuario u ON u.idUsuario = v.idUsuario
                           WHERE v.statusVenda = 'FINALIZADA' AND " . self::periodo('v.dataHoraVenda') . '
                           GROUP BY u.idUsuario, u.nomeUsuario, u.percentualComissao
                           ORDER BY comissao DESC',
                'totalizar' => ['valorTotal', 'comissao'],
            ],
            'estoque-atual' => [
                'titulo' => 'Estoque atual por grade',
                'semPeriodo' => true,
                'colunas' => [
                    ['campo' => 'nomeProduto', 'titulo' => 'Produto', 'peso' => 3],
                    ['campo' => 'tamanho', 'titulo' => 'Tam.'],
                    ['campo' => 'cor', 'titulo' => 'Cor'],
                    ['campo' => 'codigoBarras', 'titulo' => 'Código de barras', 'peso' => 1.6],
                    ['campo' => 'quantidadeEstoque', 'titulo' => 'Estoque', 'tipo' => 'numero'],
                    ['campo' => 'estoqueMinimo', 'titulo' => 'Mínimo', 'tipo' => 'numero'],
                    ['campo' => 'situacao', 'titulo' => 'Situação'],
                    ['campo' => 'valorEmEstoque', 'titulo' => 'Valor (custo)', 'tipo' => 'moeda'],
                ],
                'sql' => "SELECT p.nomeProduto, v.tamanho, v.cor, v.codigoBarras, v.quantidadeEstoque, v.estoqueMinimo,
                                 IF(v.quantidadeEstoque <= v.estoqueMinimo, 'REPOR', 'OK') AS situacao,
                                 ROUND(v.quantidadeEstoque * p.precoCusto, 2) AS valorEmEstoque
                            FROM tb_produto_variacao v JOIN tb_produto p ON p.idProduto = v.idProduto
                           WHERE p.statusProduto = TRUE
                           ORDER BY situacao DESC, p.nomeProduto, v.cor, v.tamanho",
                'totalizar' => ['quantidadeEstoque', 'valorEmEstoque'],
            ],
            'trocas' => [
                'titulo' => 'Trocas e devoluções',
                'colunas' => [
                    ['campo' => 'dataTroca', 'titulo' => 'Data', 'tipo' => 'data', 'peso' => 1.4],
                    ['campo' => 'idVenda', 'titulo' => 'Venda', 'tipo' => 'numero', 'peso' => 0.7],
                    ['campo' => 'tipoOperacao', 'titulo' => 'Operação'],
                    ['campo' => 'motivoTroca', 'titulo' => 'Motivo', 'peso' => 1.3],
                    ['campo' => 'nomeProduto', 'titulo' => 'Produto', 'peso' => 2.5],
                    ['campo' => 'quantidade', 'titulo' => 'Qtd.', 'tipo' => 'numero', 'peso' => 0.6],
                    ['campo' => 'valorDiferenca', 'titulo' => 'Diferença', 'tipo' => 'moeda'],
                    ['campo' => 'nomeUsuario', 'titulo' => 'Atendente', 'peso' => 1.5],
                ],
                'sql' => "SELECT t.dataTroca, t.idVenda, t.tipoOperacao, t.motivoTroca,
                                 CONCAT(p.nomeProduto, ' ', pv.tamanho, '/', pv.cor) AS nomeProduto,
                                 t.quantidade, t.valorDiferenca, u.nomeUsuario
                            FROM tb_troca t
                            JOIN tb_item_venda i        ON i.idItemVenda = t.idItemVenda
                            JOIN tb_produto_variacao pv ON pv.idVariacao = i.idVariacao
                            JOIN tb_produto p           ON p.idProduto   = pv.idProduto
                            JOIN tb_usuario u           ON u.idUsuario   = t.idUsuario
                           WHERE " . self::periodo('t.dataTroca') . '
                           ORDER BY t.dataTroca',
                'totalizar' => ['quantidade'],
            ],
            'caixa' => [
                'titulo' => 'Fechamentos de caixa',
                'colunas' => [
                    ['campo' => 'idCaixa', 'titulo' => 'Caixa', 'tipo' => 'numero', 'peso' => 0.6],
                    ['campo' => 'nomeUsuario', 'titulo' => 'Operador', 'peso' => 1.6],
                    ['campo' => 'dataHoraAbertura', 'titulo' => 'Abertura', 'tipo' => 'data', 'peso' => 1.4],
                    ['campo' => 'dataHoraFechamento', 'titulo' => 'Fechamento', 'tipo' => 'data', 'peso' => 1.4],
                    ['campo' => 'valorAbertura', 'titulo' => 'Valor inicial', 'tipo' => 'moeda'],
                    ['campo' => 'valorSistema', 'titulo' => 'Sistema', 'tipo' => 'moeda'],
                    ['campo' => 'valorInformado', 'titulo' => 'Informado', 'tipo' => 'moeda'],
                    ['campo' => 'diferencaCaixa', 'titulo' => 'Diferença', 'tipo' => 'moeda'],
                    ['campo' => 'statusCaixa', 'titulo' => 'Situação', 'peso' => 0.9],
                ],
                'sql' => 'SELECT c.idCaixa, u.nomeUsuario, c.dataHoraAbertura, c.dataHoraFechamento, c.valorAbertura,
                                 c.valorSistema, c.valorInformado, c.diferencaCaixa, c.statusCaixa
                            FROM tb_caixa c JOIN tb_usuario u ON u.idUsuario = c.idUsuario
                           WHERE ' . self::periodo('c.dataHoraAbertura') . '
                           ORDER BY c.idCaixa',
                'totalizar' => ['diferencaCaixa'],
            ],
        ];
    }

    public static function gerar(string $tipo, ?string $inicio, ?string $fim): array
    {
        $relatorio = self::relatorios()[$tipo];
        $semPeriodo = $relatorio['semPeriodo'] ?? false;
        $linhas = self::todos($relatorio['sql'], $semPeriodo ? [] : [$inicio, $fim]);

        $totais = [];
        foreach ($relatorio['totalizar'] as $campo) {
            $totais[$campo] = round(array_sum(array_map(fn ($l) => (float) ($l[$campo] ?? 0), $linhas)), 2);
        }
        return [
            'titulo' => $relatorio['titulo'], 'colunas' => $relatorio['colunas'],
            'linhas' => $linhas, 'totais' => $totais, 'semPeriodo' => $semPeriodo,
        ];
    }
}
