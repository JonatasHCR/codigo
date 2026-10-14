<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Core\ErroApp;
use App\Utils\Dinheiro;
use App\Utils\Loja;

final class TrocaModel extends Model
{
    // Código do vale: 10 caracteres sem letras/números que se confundem (0/O, 1/I)
    private static function gerarCodigoVale(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';
        for ($i = 0; $i < 10; $i++) $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];   // gerador seguro
        return $codigo;
    }

    private static function criarVale(?int $idCliente, float $valor): array
    {
        $idVale = self::inserir(
            'INSERT INTO tb_vale_troca (idCliente, codigoVale, valorVale, saldoVale, dataEmissao, dataValidade)
             VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY))',
            [$idCliente, self::gerarCodigoVale(), $valor, $valor, Loja::VALIDADE_VALE_DIAS]
        );
        return self::um('SELECT * FROM tb_vale_troca WHERE idVale = ?', [$idVale]);
    }

    /**
     * RF13: troca (outro tamanho/cor) ou devolução em até 30 dias.
     *  - o item devolvido volta ao estoque
     *  - TROCA: baixa o produto novo; diferença a menor vira vale, a maior é recebida no caixa
     *  - DEVOLUÇÃO: gera um vale-troca com o valor pago pelo item (RF14)
     */
    public static function registrar(array $dados, int $idUsuario): array
    {
        return Database::transacao(function () use ($dados, $idUsuario) {
            ['idItemVenda' => $idItemVenda, 'tipoOperacao' => $tipoOperacao, 'motivoTroca' => $motivoTroca, 'quantidade' => $quantidade] = $dados;
            $idVariacaoNova = $dados['idVariacaoNova'] ?? null;

            $item = self::um(
                'SELECT i.*, v.statusVenda, v.idCliente, v.valorBruto, v.valorTotal,
                        DATEDIFF(CURDATE(), DATE(v.dataHoraVenda)) AS diasDesdeVenda
                   FROM tb_item_venda i
                   JOIN tb_venda v ON v.idVenda = i.idVenda
                  WHERE i.idItemVenda = ?
                  FOR UPDATE',
                [$idItemVenda]
            );
            if (!$item) throw new ErroApp('Item da venda não encontrado', 404);
            if ($item['statusVenda'] !== 'FINALIZADA') throw new ErroApp('Só itens de vendas finalizadas podem ser trocados');
            if ($item['diasDesdeVenda'] > Loja::PRAZO_TROCA_DIAS) {
                throw new ErroApp('Prazo de troca expirado (' . Loja::PRAZO_TROCA_DIAS . ' dias após a compra)');
            }
            $jaTrocado = (int) self::um('SELECT COALESCE(SUM(quantidade), 0) AS jaTrocado FROM tb_troca WHERE idItemVenda = ?', [$idItemVenda])['jaTrocado'];
            if ($quantidade > $item['quantidade'] - $jaTrocado) {
                throw new ErroApp('Quantidade maior que a disponível para troca (' . ($item['quantidade'] - $jaTrocado) . ')');
            }

            // Valor efetivamente pago por unidade: subtotal do item com o desconto da venda rateado
            $proporcaoPaga = (float) $item['valorBruto'] > 0 ? (float) $item['valorTotal'] / (float) $item['valorBruto'] : 0;
            $valorDevolvido = Dinheiro::arredondar((float) $item['subtotal'] / $item['quantidade'] * $quantidade * $proporcaoPaga);

            // O item devolvido volta ao estoque
            self::executar('UPDATE tb_produto_variacao SET quantidadeEstoque = quantidadeEstoque + ? WHERE idVariacao = ?',
                [$quantidade, $item['idVariacao']]);
            EstoqueModel::verificarEstoqueMinimo($item['idVariacao']);

            $vale = null;
            if ($tipoOperacao === 'TROCA') {
                $nova = self::um(
                    'SELECT v.quantidadeEstoque, p.idProduto, p.idCategoria, p.precoVenda, p.nomeProduto
                       FROM tb_produto_variacao v JOIN tb_produto p ON p.idProduto = v.idProduto
                      WHERE v.idVariacao = ? AND p.statusProduto = TRUE
                      FOR UPDATE',
                    [$idVariacaoNova]
                );
                if (!$nova) throw new ErroApp('Produto novo não encontrado');
                if ($nova['quantidadeEstoque'] < $quantidade) throw new ErroApp("Estoque insuficiente: {$nova['nomeProduto']}");

                $desconto = PromocaoModel::descontoUnitario($nova)['desconto'];
                $valorNovo = Dinheiro::arredondar(((float) $nova['precoVenda'] - $desconto) * $quantidade);
                $valorDiferenca = Dinheiro::arredondar($valorNovo - $valorDevolvido);

                self::executar('UPDATE tb_produto_variacao SET quantidadeEstoque = quantidadeEstoque - ? WHERE idVariacao = ?',
                    [$quantidade, $idVariacaoNova]);
                EstoqueModel::verificarEstoqueMinimo($idVariacaoNova);

                if ($valorDiferenca > 0) {
                    // Cliente paga a diferença: entra como suprimento no caixa de quem fez a troca
                    $idCaixa = CaixaModel::buscarAbertoDoUsuario($idUsuario);
                    if (!$idCaixa) throw new ErroApp('Abra o caixa para receber a diferença da troca');
                    self::inserir(
                        "INSERT INTO tb_movimento_caixa (idCaixa, tipoMovimento, valorMovimento, descricaoMovimento)
                         VALUES (?, 'SUPRIMENTO', ?, ?)",
                        [$idCaixa, $valorDiferenca, "Diferença de troca — venda #{$item['idVenda']}"]
                    );
                } elseif ($valorDiferenca < 0) {
                    $vale = self::criarVale($item['idCliente'], -$valorDiferenca);
                }
            } else {
                $valorDiferenca = -$valorDevolvido;
                $vale = self::criarVale($item['idCliente'], $valorDevolvido);
            }

            $idTroca = self::inserir(
                'INSERT INTO tb_troca
                   (idVenda, idItemVenda, idVariacaoNova, tipoOperacao, motivoTroca, quantidade, valorDiferenca, idVale, idUsuario)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$item['idVenda'], $idItemVenda, $tipoOperacao === 'TROCA' ? $idVariacaoNova : null, $tipoOperacao,
                 $motivoTroca, $quantidade, $valorDiferenca, $vale['idVale'] ?? null, $idUsuario]
            );

            return compact('idTroca', 'valorDevolvido', 'valorDiferenca', 'vale');
        });
    }

    // RF14: consulta do vale (marca como VENCIDO se passou da validade)
    public static function buscarVale(string $codigo): ?array
    {
        self::executar(
            "UPDATE tb_vale_troca SET statusVale = 'VENCIDO'
              WHERE codigoVale = ? AND statusVale = 'ATIVO' AND dataValidade < CURDATE()",
            [$codigo]
        );
        return self::um(
            'SELECT vt.*, c.nomeCliente FROM tb_vale_troca vt LEFT JOIN tb_cliente c ON c.idCliente = vt.idCliente
              WHERE vt.codigoVale = ?',
            [$codigo]
        );
    }

    public static function listar(): array
    {
        return self::todos(
            'SELECT t.idTroca, t.dataTroca, t.idVenda, t.tipoOperacao, t.motivoTroca, t.quantidade,
                    t.valorDiferenca, vt.codigoVale, p.nomeProduto, pv.tamanho, pv.cor, u.nomeUsuario
               FROM tb_troca t
               JOIN tb_item_venda i        ON i.idItemVenda = t.idItemVenda
               JOIN tb_produto_variacao pv ON pv.idVariacao = i.idVariacao
               JOIN tb_produto p           ON p.idProduto   = pv.idProduto
               JOIN tb_usuario u           ON u.idUsuario   = t.idUsuario
               LEFT JOIN tb_vale_troca vt  ON vt.idVale     = t.idVale
              ORDER BY t.idTroca DESC LIMIT 100'
        );
    }
}
