<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Core\ErroApp;
use App\Utils\Dinheiro;

final class EstoqueModel extends Model
{
    /**
     * RF16: depois de qualquer mudança no estoque, confere a variação:
     *  - ficou igual ou abaixo do mínimo e não há alerta pendente → cria o alerta
     *  - voltou a ficar acima do mínimo → resolve os alertas pendentes
     * Chamada dentro da transação de quem alterou o estoque.
     */
    public static function verificarEstoqueMinimo(int $idVariacao): void
    {
        $v = self::um('SELECT quantidadeEstoque, estoqueMinimo FROM tb_produto_variacao WHERE idVariacao = ?', [$idVariacao]);
        if ($v['quantidadeEstoque'] <= $v['estoqueMinimo']) {
            $pendente = self::um("SELECT idAlerta FROM tb_alerta_estoque WHERE idVariacao = ? AND statusAlerta = 'PENDENTE'", [$idVariacao]);
            if ($pendente) {
                self::executar('UPDATE tb_alerta_estoque SET quantidadeAtual = ? WHERE idAlerta = ?', [$v['quantidadeEstoque'], $pendente['idAlerta']]);
            } else {
                self::inserir('INSERT INTO tb_alerta_estoque (idVariacao, quantidadeAtual) VALUES (?, ?)', [$idVariacao, $v['quantidadeEstoque']]);
            }
        } else {
            self::executar(
                "UPDATE tb_alerta_estoque SET statusAlerta = 'RESOLVIDO', quantidadeAtual = ?
                  WHERE idVariacao = ? AND statusAlerta = 'PENDENTE'",
                [$v['quantidadeEstoque'], $idVariacao]
            );
        }
    }

    // RF08: entrada de mercadorias (transação: tudo ou nada)
    public static function registrarEntrada(array $dados, int $idUsuario): array
    {
        return Database::transacao(function () use ($dados, $idUsuario) {
            $valorTotalEntrada = Dinheiro::arredondar(array_sum(array_map(
                fn ($i) => $i['quantidade'] * $i['custoUnitario'], $dados['itens']
            )));
            $idEntrada = self::inserir(
                'INSERT INTO tb_entrada_estoque (idFornecedor, numeroNotaFiscal, dataEntrada, valorTotalEntrada, idUsuario)
                 VALUES (?, ?, ?, ?, ?)',
                [$dados['idFornecedor'], self::ouNulo($dados['numeroNotaFiscal'] ?? null), $dados['dataEntrada'], $valorTotalEntrada, $idUsuario]
            );

            foreach ($dados['itens'] as $item) {
                $variacao = self::um('SELECT idVariacao FROM tb_produto_variacao WHERE idVariacao = ? FOR UPDATE', [$item['idVariacao']]);
                if (!$variacao) throw new ErroApp("Variação {$item['idVariacao']} não encontrada");

                self::inserir(
                    'INSERT INTO tb_item_entrada (idEntrada, idVariacao, quantidade, custoUnitario) VALUES (?, ?, ?, ?)',
                    [$idEntrada, $item['idVariacao'], $item['quantidade'], $item['custoUnitario']]
                );
                self::executar(
                    'UPDATE tb_produto_variacao SET quantidadeEstoque = quantidadeEstoque + ? WHERE idVariacao = ?',
                    [$item['quantidade'], $item['idVariacao']]
                );
                self::verificarEstoqueMinimo($item['idVariacao']);
            }
            return ['idEntrada' => $idEntrada, 'valorTotalEntrada' => $valorTotalEntrada];
        });
    }

    public static function listarEntradas(): array
    {
        return self::todos(
            'SELECT e.idEntrada, e.numeroNotaFiscal, e.dataEntrada, e.valorTotalEntrada,
                    f.razaoSocial, u.nomeUsuario,
                    (SELECT SUM(quantidade) FROM tb_item_entrada WHERE idEntrada = e.idEntrada) AS totalItens
               FROM tb_entrada_estoque e
               JOIN tb_fornecedor f ON f.idFornecedor = e.idFornecedor
               JOIN tb_usuario u    ON u.idUsuario    = e.idUsuario
              ORDER BY e.idEntrada DESC
              LIMIT 100'
        );
    }

    public static function listarAlertas(): array
    {
        return self::todos(
            "SELECT a.idAlerta, a.dataAlerta, a.quantidadeAtual, v.idVariacao, v.tamanho, v.cor,
                    v.estoqueMinimo, v.quantidadeEstoque, p.nomeProduto, p.codigoProduto
               FROM tb_alerta_estoque a
               JOIN tb_produto_variacao v ON v.idVariacao = a.idVariacao
               JOIN tb_produto p          ON p.idProduto  = v.idProduto
              WHERE a.statusAlerta = 'PENDENTE'
              ORDER BY v.quantidadeEstoque, a.dataAlerta"
        );
    }
}
