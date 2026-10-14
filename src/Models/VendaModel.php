<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Core\ErroApp;
use App\Utils\Dinheiro;
use App\Utils\Loja;

final class VendaModel extends Model
{
    // Junta linhas repetidas do mesmo produto (ex.: leu o código de barras duas vezes)
    private static function agruparItens(array $itens): array
    {
        $porVariacao = [];
        foreach ($itens as $item) {
            $porVariacao[$item['idVariacao']] = ($porVariacao[$item['idVariacao']] ?? 0) + $item['quantidade'];
        }
        return array_map(fn ($id, $qtd) => ['idVariacao' => $id, 'quantidade' => $qtd], array_keys($porVariacao), $porVariacao);
    }

    /**
     * RF09 + RF10 + RF11 + RF18 + RF19 — tudo em UMA transação (RNF09).
     * Ver: Explicação / 08 - Venda e Cancelamento
     */
    public static function registrar(array $dados, int $idVendedor, int $idCaixa): array
    {
        $idCliente = ($dados['idCliente'] ?? null) ?: null;
        $descontoPercentual = (float) ($dados['descontoPercentual'] ?? 0);
        $pontosResgatar = (int) ($dados['pontosResgatar'] ?? 0);
        $pagamentos = $dados['pagamentos'];
        $itens = self::agruparItens($dados['itens']);

        return Database::transacao(function () use ($idCliente, $descontoPercentual, $pontosResgatar, $pagamentos, $itens, $idVendedor, $idCaixa) {
            // 0. Cliente (opcional) e a conta de fidelidade dele
            if ($idCliente) {
                $cliente = self::um('SELECT statusCliente FROM tb_cliente WHERE idCliente = ?', [$idCliente]);
                if (!$cliente || !$cliente['statusCliente']) throw new ErroApp('Cliente não encontrado ou inativo');
                self::executar('INSERT IGNORE INTO tb_fidelidade (idCliente) VALUES (?)', [$idCliente]);
            }

            // 1. Buscar preço, aplicar promoção e TRAVAR o estoque de cada item
            $valorBruto = 0.0;
            $itensCalculados = [];
            foreach ($itens as $item) {
                $variacao = self::um(
                    'SELECT v.quantidadeEstoque, p.idProduto, p.idCategoria, p.precoVenda, p.nomeProduto, v.tamanho, v.cor
                       FROM tb_produto_variacao v
                       JOIN tb_produto p ON p.idProduto = v.idProduto
                      WHERE v.idVariacao = ? AND p.statusProduto = TRUE
                      FOR UPDATE',
                    [$item['idVariacao']]
                );
                if (!$variacao) throw new ErroApp("Produto {$item['idVariacao']} não encontrado");
                if ($variacao['quantidadeEstoque'] < $item['quantidade']) {
                    throw new ErroApp("Estoque insuficiente: {$variacao['nomeProduto']} {$variacao['tamanho']}/{$variacao['cor']} "
                        . "(disponível: {$variacao['quantidadeEstoque']})");
                }
                $precoUnitario = (float) $variacao['precoVenda'];
                $desconto = PromocaoModel::descontoUnitario($variacao)['desconto'];   // RF11 automático
                $descontoItem = Dinheiro::arredondar($desconto * $item['quantidade']);
                $subtotal = Dinheiro::arredondar($precoUnitario * $item['quantidade'] - $descontoItem);
                $valorBruto += $subtotal;
                $itensCalculados[] = [...$item, 'precoUnitario' => $precoUnitario, 'descontoItem' => $descontoItem, 'subtotal' => $subtotal];
            }
            $valorBruto = Dinheiro::arredondar($valorBruto);

            // 2. Descontos: manual (%) + resgate de pontos (RF18)
            $valorDesconto = Dinheiro::arredondar($valorBruto * $descontoPercentual / 100);
            if ($pontosResgatar > 0) {
                if (!$idCliente) throw new ErroApp('Informe o cliente para resgatar pontos');
                if ($pontosResgatar % Loja::PONTOS_POR_RESGATE !== 0) {
                    throw new ErroApp('Os pontos são resgatados de ' . Loja::PONTOS_POR_RESGATE . ' em ' . Loja::PONTOS_POR_RESGATE);
                }
                $conta = self::um('SELECT saldoPontos FROM tb_fidelidade WHERE idCliente = ? FOR UPDATE', [$idCliente]);
                if ($conta['saldoPontos'] < $pontosResgatar) throw new ErroApp('Saldo de pontos insuficiente');
                $valorDesconto += $pontosResgatar / Loja::PONTOS_POR_RESGATE * Loja::VALOR_POR_RESGATE;
            }
            $valorDesconto = Dinheiro::arredondar(min($valorDesconto, $valorBruto));
            $valorTotal = Dinheiro::arredondar($valorBruto - $valorDesconto);

            // 3. Conferir os pagamentos (RF10)
            $totalPago = 0.0;
            $totalDinheiro = 0.0;
            foreach ($pagamentos as &$pagamento) {
                if (($pagamento['numeroParcelas'] ?? 1) > 1 && $pagamento['formaPagamento'] !== 'CREDITO') {
                    throw new ErroApp('Parcelamento só no cartão de crédito');
                }
                if ($pagamento['formaPagamento'] === 'VALE_TROCA') {
                    $vale = self::um(
                        'SELECT idVale, saldoVale, statusVale, dataValidade < CURDATE() AS vencido
                           FROM tb_vale_troca WHERE codigoVale = ? FOR UPDATE',
                        [strtoupper((string) ($pagamento['codigoVale'] ?? ''))]
                    );
                    if (!$vale) throw new ErroApp('Vale-troca não encontrado');
                    if ($vale['statusVale'] !== 'ATIVO' || $vale['vencido']) throw new ErroApp('Vale-troca vencido ou já utilizado');
                    if ((float) $vale['saldoVale'] < $pagamento['valorPago']) throw new ErroApp('Saldo do vale-troca insuficiente');
                    $pagamento['idVale'] = $vale['idVale'];
                }
                $totalPago += $pagamento['valorPago'];
                if ($pagamento['formaPagamento'] === 'DINHEIRO') $totalDinheiro += $pagamento['valorPago'];
            }
            unset($pagamento);
            $totalPago = Dinheiro::arredondar($totalPago);
            if ($totalPago < $valorTotal) throw new ErroApp('Valor pago é menor que o total');
            $troco = Dinheiro::arredondar($totalPago - $valorTotal);
            if ($troco > $totalDinheiro) throw new ErroApp('Troco só é possível em pagamento com dinheiro');

            // 4. Gravar a venda
            $idVenda = self::inserir(
                "INSERT INTO tb_venda (idCliente, idUsuario, idCaixa, valorBruto, valorDesconto, valorTotal, pontosUtilizados, statusVenda)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'FINALIZADA')",
                [$idCliente, $idVendedor, $idCaixa, $valorBruto, $valorDesconto, $valorTotal, $pontosResgatar]
            );

            // 5. Itens + baixa no estoque + alerta de estoque mínimo (RF16)
            foreach ($itensCalculados as $item) {
                self::inserir(
                    'INSERT INTO tb_item_venda (idVenda, idVariacao, quantidade, precoUnitario, descontoItem, subtotal) VALUES (?, ?, ?, ?, ?, ?)',
                    [$idVenda, $item['idVariacao'], $item['quantidade'], $item['precoUnitario'], $item['descontoItem'], $item['subtotal']]
                );
                self::executar(
                    'UPDATE tb_produto_variacao SET quantidadeEstoque = quantidadeEstoque - ? WHERE idVariacao = ?',
                    [$item['quantidade'], $item['idVariacao']]
                );
                EstoqueModel::verificarEstoqueMinimo($item['idVariacao']);
            }

            // 6. Pagamentos (o troco fica registrado no primeiro pagamento em dinheiro)
            $trocoPendente = $troco;
            foreach ($pagamentos as $pagamento) {
                $valorTroco = $pagamento['formaPagamento'] === 'DINHEIRO' ? min($trocoPendente, $pagamento['valorPago']) : 0;
                $trocoPendente = Dinheiro::arredondar($trocoPendente - $valorTroco);
                self::inserir(
                    'INSERT INTO tb_pagamento (idVenda, formaPagamento, valorPago, numeroParcelas, valorTroco, codigoAutorizacao, idVale)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$idVenda, $pagamento['formaPagamento'], $pagamento['valorPago'], $pagamento['numeroParcelas'] ?? 1,
                     $valorTroco, self::ouNulo($pagamento['codigoAutorizacao'] ?? null), $pagamento['idVale'] ?? null]
                );
                if (!empty($pagamento['idVale'])) {
                    self::executar(
                        "UPDATE tb_vale_troca
                            SET saldoVale = saldoVale - ?,
                                statusVale = IF(saldoVale = 0, 'UTILIZADO', 'ATIVO')
                          WHERE idVale = ?",
                        [$pagamento['valorPago'], $pagamento['idVale']]
                    );
                }
            }

            // 7. Fidelidade: gasta os pontos resgatados e soma os novos (RF18)
            $pontosGerados = 0;
            if ($idCliente) {
                $pontosGerados = (int) floor($valorTotal * Loja::PONTOS_POR_REAL);
                self::executar(
                    'UPDATE tb_fidelidade
                        SET pontosAcumulados = pontosAcumulados + ?, pontosUtilizados = pontosUtilizados + ?,
                            saldoPontos = saldoPontos + ? - ?, dataUltimaAtualizacao = NOW()
                      WHERE idCliente = ?',
                    [$pontosGerados, $pontosResgatar, $pontosGerados, $pontosResgatar, $idCliente]
                );
                self::executar('UPDATE tb_venda SET pontosGerados = ? WHERE idVenda = ?', [$pontosGerados, $idVenda]);
            }

            // 8. Comprovante (RF19) — NFC-e simulada
            $numeroComprovante = 'CV' . str_pad((string) $idVenda, 8, '0', STR_PAD_LEFT);
            self::inserir(
                'INSERT INTO tb_comprovante (idVenda, numeroComprovante, chaveAcessoNfce) VALUES (?, ?, ?)',
                [$idVenda, $numeroComprovante, Loja::chaveNfceSimulada($idVenda)]
            );

            return compact('idVenda', 'valorBruto', 'valorDesconto', 'valorTotal', 'troco', 'pontosGerados', 'numeroComprovante');
        });
    }

    /**
     * RF12: cancelar venda finalizada (autorizada pelo gerente).
     * Estoque volta, pontos são estornados e vales usados recebem o saldo de volta.
     */
    public static function cancelar(int $idVenda, string $motivo, int $idSolicitante, int $idAutorizador): void
    {
        Database::transacao(function () use ($idVenda, $motivo, $idSolicitante, $idAutorizador) {
            $venda = self::um('SELECT * FROM tb_venda WHERE idVenda = ? FOR UPDATE', [$idVenda]);
            if (!$venda) throw new ErroApp('Venda não encontrada', 404);
            if ($venda['statusVenda'] !== 'FINALIZADA') throw new ErroApp('Só vendas finalizadas podem ser canceladas');
            if (self::um('SELECT idTroca FROM tb_troca WHERE idVenda = ? LIMIT 1', [$idVenda])) {
                throw new ErroApp('Esta venda já teve troca ou devolução e não pode ser cancelada');
            }

            foreach (self::todos('SELECT idVariacao, quantidade FROM tb_item_venda WHERE idVenda = ?', [$idVenda]) as $item) {
                self::executar(
                    'UPDATE tb_produto_variacao SET quantidadeEstoque = quantidadeEstoque + ? WHERE idVariacao = ?',
                    [$item['quantidade'], $item['idVariacao']]
                );
                EstoqueModel::verificarEstoqueMinimo($item['idVariacao']);
            }

            if ($venda['idCliente']) {
                self::executar(
                    'UPDATE tb_fidelidade
                        SET pontosAcumulados = GREATEST(pontosAcumulados - ?, 0),
                            pontosUtilizados = GREATEST(pontosUtilizados - ?, 0),
                            saldoPontos = GREATEST(saldoPontos - ?, 0) + ?,
                            dataUltimaAtualizacao = NOW()
                      WHERE idCliente = ?',
                    [$venda['pontosGerados'], $venda['pontosUtilizados'], $venda['pontosGerados'], $venda['pontosUtilizados'], $venda['idCliente']]
                );
            }

            self::executar(
                "UPDATE tb_vale_troca vt
                   JOIN tb_pagamento pg ON pg.idVale = vt.idVale
                    SET vt.saldoVale = vt.saldoVale + pg.valorPago, vt.statusVale = 'ATIVO'
                  WHERE pg.idVenda = ?",
                [$idVenda]
            );

            self::inserir(
                'INSERT INTO tb_cancelamento (idVenda, motivoCancelamento, idUsuarioSolicitante, idUsuarioAutorizador) VALUES (?, ?, ?, ?)',
                [$idVenda, $motivo, $idSolicitante, $idAutorizador]
            );
            self::executar("UPDATE tb_venda SET statusVenda = 'CANCELADA' WHERE idVenda = ?", [$idVenda]);
        });
    }

    public static function listar(array $filtros = []): array
    {
        $condicoes = ['1 = 1'];
        $valores = [];
        if (!empty($filtros['idVenda'])) { $condicoes[] = 'v.idVenda = ?'; $valores[] = $filtros['idVenda']; }
        if (!empty($filtros['data'])) {
            $condicoes[] = 'v.dataHoraVenda >= ? AND v.dataHoraVenda < DATE_ADD(?, INTERVAL 1 DAY)';
            array_push($valores, $filtros['data'], $filtros['data']);
        }
        if (!empty($filtros['status'])) { $condicoes[] = 'v.statusVenda = ?'; $valores[] = $filtros['status']; }

        return self::todos(
            'SELECT v.idVenda, v.dataHoraVenda, v.valorTotal, v.statusVenda, c.nomeCliente, u.nomeUsuario AS nomeVendedor
               FROM tb_venda v
               LEFT JOIN tb_cliente c ON c.idCliente = v.idCliente
               JOIN tb_usuario u      ON u.idUsuario = v.idUsuario
              WHERE ' . implode(' AND ', $condicoes) . '
              ORDER BY v.idVenda DESC
              LIMIT 100',
            $valores
        );
    }

    public static function detalhe(int $idVenda): ?array
    {
        $venda = self::um(
            'SELECT v.*, c.nomeCliente, c.cpfCliente, c.telefoneCliente, c.emailCliente,
                    u.nomeUsuario AS nomeVendedor, cp.numeroComprovante, cp.chaveAcessoNfce,
                    cp.dataEmissao, cp.formatoEnvio, ca.motivoCancelamento, ca.dataHoraCancelamento
               FROM tb_venda v
               LEFT JOIN tb_cliente c       ON c.idCliente = v.idCliente
               JOIN tb_usuario u            ON u.idUsuario = v.idUsuario
               LEFT JOIN tb_comprovante cp  ON cp.idVenda  = v.idVenda
               LEFT JOIN tb_cancelamento ca ON ca.idVenda  = v.idVenda
              WHERE v.idVenda = ?',
            [$idVenda]
        );
        if (!$venda) return null;

        $venda['itens'] = self::todos(
            'SELECT i.*, p.nomeProduto, pv.tamanho, pv.cor, pv.codigoBarras,
                    i.quantidade - COALESCE((SELECT SUM(t.quantidade) FROM tb_troca t
                                              WHERE t.idItemVenda = i.idItemVenda), 0) AS quantidadeDisponivelTroca
               FROM tb_item_venda i
               JOIN tb_produto_variacao pv ON pv.idVariacao = i.idVariacao
               JOIN tb_produto p           ON p.idProduto   = pv.idProduto
              WHERE i.idVenda = ?',
            [$idVenda]
        );
        $venda['pagamentos'] = self::todos('SELECT * FROM tb_pagamento WHERE idVenda = ?', [$idVenda]);
        $venda['trocas'] = self::todos(
            'SELECT t.*, vt.codigoVale FROM tb_troca t LEFT JOIN tb_vale_troca vt ON vt.idVale = t.idVale
              WHERE t.idVenda = ? ORDER BY t.idTroca',
            [$idVenda]
        );
        return $venda;
    }

    public static function registrarEnvio(int $idVenda, string $formatoEnvio): bool
    {
        return self::executar('UPDATE tb_comprovante SET formatoEnvio = ? WHERE idVenda = ?', [$formatoEnvio, $idVenda]) > 0;
    }
}
