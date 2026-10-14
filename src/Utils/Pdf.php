<?php
declare(strict_types=1);

namespace App\Utils;

use FPDF;

/**
 * Geração de PDF com a biblioteca FPDF.
 * O FPDF usa a codificação Windows-1252: por isso todo texto passa por t() antes de ir para o PDF.
 */
final class Pdf
{
    private const FORMAS = [
        'DINHEIRO' => 'Dinheiro', 'PIX' => 'PIX', 'DEBITO' => 'Cartão de débito',
        'CREDITO' => 'Cartão de crédito', 'VALE_TROCA' => 'Vale-troca',
    ];

    /** UTF-8 → Windows-1252 (acentos certos no FPDF) */
    private static function t(?string $texto): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT', (string) $texto);
    }

    public static function dataBr(?string $texto): string
    {
        if (!$texto) return '';
        [$data, $hora] = array_pad(explode(' ', $texto), 2, null);
        $dataBr = implode('/', array_reverse(explode('-', $data)));
        return $hora ? $dataBr . ' ' . substr($hora, 0, 5) : $dataBr;
    }

    /**
     * RF19: cupom no tamanho da impressora térmica de 80 mm.
     * A altura acompanha a quantidade de itens.
     */
    public static function cupom(array $venda): void
    {
        $altura = 125 + count($venda['itens']) * 11 + count($venda['pagamentos']) * 5;
        $pdf = new FPDF('P', 'mm', [80, $altura]);
        $pdf->SetMargins(5, 5, 5);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $largura = 70;

        $linha = function () use ($pdf, $largura) {
            $pdf->SetFont('Courier', '', 8);
            $pdf->Cell($largura, 4, str_repeat('-', 40), 0, 1, 'C');
        };
        $par = function (string $esquerda, string $direita, bool $negrito = false) use ($pdf, $largura) {
            $pdf->SetFont('Helvetica', $negrito ? 'B' : '', 8);
            $pdf->Cell($largura * 0.62, 4.5, self::t($esquerda));
            $pdf->Cell($largura * 0.38, 4.5, self::t($direita), 0, 1, 'R');
        };

        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->MultiCell($largura, 4.5, self::t(Loja::NOME), 0, 'C');
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->MultiCell($largura, 3.5, self::t('CNPJ ' . Loja::CNPJ . "\n" . Loja::ENDERECO . "\nTel. " . Loja::TELEFONE), 0, 'C');
        $linha();
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell($largura, 4, self::t("Comprovante: {$venda['numeroComprovante']}"), 0, 1);
        $pdf->Cell($largura, 4, self::t('Data: ' . self::dataBr($venda['dataHoraVenda'])), 0, 1);
        $pdf->Cell($largura, 4, self::t("Vendedor: {$venda['nomeVendedor']}"), 0, 1);
        if ($venda['nomeCliente']) $pdf->Cell($largura, 4, self::t("Cliente: {$venda['nomeCliente']}"), 0, 1);
        if ($venda['statusVenda'] === 'CANCELADA') {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell($largura, 5, '*** VENDA CANCELADA ***', 0, 1, 'C');
        }
        $linha();

        foreach ($venda['itens'] as $item) {
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->Cell($largura, 4, self::t("{$item['nomeProduto']} {$item['tamanho']}/{$item['cor']}"), 0, 1);
            $par("  {$item['quantidade']} x " . Dinheiro::formatar($item['precoUnitario']), Dinheiro::formatar($item['subtotal']));
            if ((float) $item['descontoItem'] > 0) $par('  promoção', '-' . Dinheiro::formatar($item['descontoItem']));
        }
        $linha();
        $par('Subtotal', Dinheiro::formatar($venda['valorBruto']));
        if ((float) $venda['valorDesconto'] > 0) $par('Desconto', '-' . Dinheiro::formatar($venda['valorDesconto']));
        $par('TOTAL', Dinheiro::formatar($venda['valorTotal']), true);
        $pdf->Ln(1);
        foreach ($venda['pagamentos'] as $p) {
            $parcelas = $p['numeroParcelas'] > 1 ? " ({$p['numeroParcelas']}x)" : '';
            $par(self::FORMAS[$p['formaPagamento']] . $parcelas, Dinheiro::formatar($p['valorPago']));
            if ((float) $p['valorTroco'] > 0) $par('Troco', Dinheiro::formatar($p['valorTroco']));
        }
        if ($venda['pontosGerados'] > 0) {
            $pdf->Ln(1);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->Cell($largura, 4, self::t("Você ganhou {$venda['pontosGerados']} pontos de fidelidade!"), 0, 1, 'C');
        }
        $linha();
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->MultiCell($largura, 3, self::t("NFC-e SIMULADA - documento sem valor fiscal\nChave: "
            . trim(chunk_split((string) $venda['chaveAcessoNfce'], 4, ' ')) . "\nTrocas em até 30 dias com este comprovante."), 0, 'C');

        $pdf->Output('I', "cupom-{$venda['idVenda']}.pdf");   // I = abre no navegador (inline)
    }

    /**
     * RF20: relatório em tabela, genérico para todos os tipos.
     * colunas = [['campo', 'titulo', 'tipo' => texto|numero|moeda|data, 'peso']]
     */
    public static function relatorio(array $dados, string $periodo): void
    {
        $pdf = new FPDF('L', 'mm', 'A4');   // paisagem
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(false);
        $larguraUtil = 297 - 24;
        $colunas = $dados['colunas'];
        $somaPesos = array_sum(array_map(fn ($c) => $c['peso'] ?? 1, $colunas));
        $larguras = array_map(fn ($c) => $larguraUtil * ($c['peso'] ?? 1) / $somaPesos, $colunas);

        $formatar = function ($valor, ?string $tipo): string {
            if ($valor === null) return '';
            return match ($tipo) {
                'moeda' => Dinheiro::formatar($valor),
                'data' => self::dataBr((string) $valor),
                default => (string) $valor,
            };
        };
        $cortar = function (string $texto, float $largura) use ($pdf): string {
            while ($texto !== '' && $pdf->GetStringWidth($texto) > $largura - 2) $texto = substr($texto, 0, -1);
            return $texto;
        };
        $desenharLinha = function (array $textos, bool $negrito = false, ?array $fundo = null) use ($pdf, $colunas, $larguras, $cortar) {
            $pdf->SetFont('Helvetica', $negrito ? 'B' : '', 8.5);
            if ($fundo) $pdf->SetFillColor(...$fundo);
            foreach ($textos as $i => $texto) {
                $alinhamento = in_array($colunas[$i]['tipo'] ?? null, ['moeda', 'numero'], true) ? 'R' : 'L';
                $pdf->Cell($larguras[$i], 6, $cortar(self::t($texto), $larguras[$i]), 'B', 0, $alinhamento, (bool) $fundo);
            }
            $pdf->Ln();
        };
        $cabecalho = function () use ($pdf, $dados, $periodo, $larguraUtil, $colunas, $desenharLinha) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 15);
            $pdf->Cell($larguraUtil, 8, self::t($dados['titulo']), 0, 1, 'C');
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->Cell($larguraUtil, 5, self::t(Loja::NOME . " · $periodo · emitido em " . date('d/m/Y H:i')), 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(3);
            $pdf->SetDrawColor(210, 210, 210);
            $desenharLinha(array_map(fn ($c) => $c['titulo'], $colunas), true);
        };

        $cabecalho();
        if (!$dados['linhas']) {
            $pdf->SetFont('Helvetica', 'I', 9);
            $pdf->Cell($larguraUtil, 8, self::t('Nenhum registro no período.'), 0, 1);
        }
        foreach ($dados['linhas'] as $i => $l) {
            if ($pdf->GetY() > 190) $cabecalho();   // nova página com cabeçalho
            $desenharLinha(array_map(fn ($c) => $formatar($l[$c['campo']] ?? null, $c['tipo'] ?? null), $colunas),
                false, $i % 2 ? [245, 245, 245] : null);
        }
        if ($dados['totais']) {
            $desenharLinha(array_map(fn ($c) => array_key_exists($c['campo'], $dados['totais'])
                ? $formatar($dados['totais'][$c['campo']], $c['tipo'] ?? null) : '', $colunas), true, [232, 238, 252]);
        }

        $nome = Csv::nomeArquivo($dados['titulo']);
        $pdf->Output('D', "$nome.pdf");   // D = download
    }
}
