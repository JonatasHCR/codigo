<?php
declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use App\Core\ErroApp;
use App\Utils\Dinheiro;

final class CaixaModel extends Model
{
    public static function buscarAbertoDoUsuario(int $idUsuario): ?int
    {
        $caixa = self::um("SELECT idCaixa FROM tb_caixa WHERE idUsuario = ? AND statusCaixa = 'ABERTO'", [$idUsuario]);
        return $caixa ? (int) $caixa['idCaixa'] : null;
    }

    /**
     * Quanto deveria haver de DINHEIRO na gaveta:
     * abertura + dinheiro recebido nas vendas finalizadas − troco + suprimentos − sangrias
     * (vendas canceladas não entram: o dinheiro foi devolvido ao cliente).
     */
    public static function resumo(int $idCaixa): ?array
    {
        $caixa = self::um(
            'SELECT c.*, u.nomeUsuario FROM tb_caixa c JOIN tb_usuario u ON u.idUsuario = c.idUsuario WHERE c.idCaixa = ?',
            [$idCaixa]
        );
        if (!$caixa) return null;

        $vendas = self::um(
            "SELECT COUNT(DISTINCT v.idVenda) AS quantidadeVendas,
                    COALESCE(SUM(CASE WHEN pg.formaPagamento = 'DINHEIRO' THEN pg.valorPago - pg.valorTroco END), 0) AS dinheiro,
                    COALESCE(SUM(CASE WHEN pg.formaPagamento = 'PIX' THEN pg.valorPago END), 0) AS pix,
                    COALESCE(SUM(CASE WHEN pg.formaPagamento = 'DEBITO' THEN pg.valorPago END), 0) AS debito,
                    COALESCE(SUM(CASE WHEN pg.formaPagamento = 'CREDITO' THEN pg.valorPago END), 0) AS credito,
                    COALESCE(SUM(CASE WHEN pg.formaPagamento = 'VALE_TROCA' THEN pg.valorPago END), 0) AS valeTroca
               FROM tb_venda v
               JOIN tb_pagamento pg ON pg.idVenda = v.idVenda
              WHERE v.idCaixa = ? AND v.statusVenda = 'FINALIZADA'",
            [$idCaixa]
        );
        $movimentos = self::um(
            "SELECT COALESCE(SUM(CASE WHEN tipoMovimento = 'SUPRIMENTO' THEN valorMovimento END), 0) AS suprimentos,
                    COALESCE(SUM(CASE WHEN tipoMovimento = 'SANGRIA' THEN valorMovimento END), 0) AS sangrias
               FROM tb_movimento_caixa WHERE idCaixa = ?",
            [$idCaixa]
        );
        $listaMovimentos = self::todos('SELECT * FROM tb_movimento_caixa WHERE idCaixa = ? ORDER BY dataHoraMovimento', [$idCaixa]);

        $dinheiroEsperado = Dinheiro::arredondar(
            (float) $caixa['valorAbertura'] + (float) $vendas['dinheiro']
            + (float) $movimentos['suprimentos'] - (float) $movimentos['sangrias']
        );
        return [...$caixa, 'vendas' => $vendas, 'movimentos' => $movimentos,
                'listaMovimentos' => $listaMovimentos, 'dinheiroEsperado' => $dinheiroEsperado];
    }

    public static function abrir(int $idUsuario, float $valorAbertura): int
    {
        if (self::buscarAbertoDoUsuario($idUsuario)) throw new ErroApp('Você já tem um caixa aberto', 409);
        return self::inserir('INSERT INTO tb_caixa (idUsuario, valorAbertura) VALUES (?, ?)', [$idUsuario, $valorAbertura]);
    }

    public static function movimentar(int $idCaixa, array $m): int
    {
        return Database::transacao(function () use ($idCaixa, $m) {
            self::um('SELECT idCaixa FROM tb_caixa WHERE idCaixa = ? FOR UPDATE', [$idCaixa]);
            if ($m['tipoMovimento'] === 'SANGRIA') {
                $dinheiroEsperado = self::resumo($idCaixa)['dinheiroEsperado'];
                if ($m['valorMovimento'] > $dinheiroEsperado) {
                    throw new ErroApp('Sangria maior que o dinheiro disponível no caixa');
                }
            }
            return self::inserir(
                'INSERT INTO tb_movimento_caixa (idCaixa, tipoMovimento, valorMovimento, descricaoMovimento) VALUES (?, ?, ?, ?)',
                [$idCaixa, $m['tipoMovimento'], $m['valorMovimento'], self::ouNulo($m['descricaoMovimento'] ?? null)]
            );
        });
    }

    // RF15: no fechamento, compara o valor contado pelo operador com o calculado pelo sistema
    public static function fechar(int $idCaixa, float $valorInformado): array
    {
        return Database::transacao(function () use ($idCaixa, $valorInformado) {
            self::um('SELECT idCaixa FROM tb_caixa WHERE idCaixa = ? FOR UPDATE', [$idCaixa]);
            $dinheiroEsperado = self::resumo($idCaixa)['dinheiroEsperado'];
            $diferencaCaixa = Dinheiro::arredondar($valorInformado - $dinheiroEsperado);
            self::executar(
                "UPDATE tb_caixa
                    SET dataHoraFechamento = NOW(), valorInformado = ?, valorSistema = ?,
                        diferencaCaixa = ?, statusCaixa = 'FECHADO'
                  WHERE idCaixa = ?",
                [$valorInformado, $dinheiroEsperado, $diferencaCaixa, $idCaixa]
            );
            return ['valorSistema' => $dinheiroEsperado, 'valorInformado' => $valorInformado, 'diferencaCaixa' => $diferencaCaixa];
        });
    }

    public static function listar(): array
    {
        return self::todos(
            'SELECT c.idCaixa, u.nomeUsuario, c.dataHoraAbertura, c.dataHoraFechamento, c.valorAbertura,
                    c.valorSistema, c.valorInformado, c.diferencaCaixa, c.statusCaixa
               FROM tb_caixa c JOIN tb_usuario u ON u.idUsuario = c.idUsuario
              ORDER BY c.idCaixa DESC LIMIT 50'
        );
    }
}
