<?php
declare(strict_types=1);

namespace App\Utils;

/** Regras e dados da loja num só lugar: mudar uma regra = mudar uma linha */
final class Loja
{
    // Dados impressos no comprovante (RF19)
    public const NOME = 'Loja Modelo — Roupas e Calçados';
    public const CNPJ = '11.222.333/0001-81';
    public const ENDERECO = 'Av. Exemplo, 100 — Centro — Lauro de Freitas/BA';
    public const TELEFONE = '(71) 3333-0000';

    // Programa de fidelidade (RF18)
    public const PONTOS_POR_REAL = 1;        // R$ 1,00 = 1 ponto
    public const PONTOS_POR_RESGATE = 100;   // 100 pontos...
    public const VALOR_POR_RESGATE = 5;      // ...= R$ 5,00 de desconto

    public const PRAZO_TROCA_DIAS = 30;          // RF13
    public const VALIDADE_VALE_DIAS = 90;        // RF14
    public const DESCONTO_MAXIMO_VENDEDOR = 10;  // RF11 (%)

    public static function regrasFidelidade(): array
    {
        return [
            'pontosPorReal' => self::PONTOS_POR_REAL,
            'pontosPorResgate' => self::PONTOS_POR_RESGATE,
            'valorPorResgate' => self::VALOR_POR_RESGATE,
        ];
    }

    /**
     * NFC-e SIMULADA: a chave real (44 dígitos) é gerada pela SEFAZ após a transmissão
     * da nota. Aqui montamos uma chave com o mesmo formato, só para demonstração.
     */
    public static function chaveNfceSimulada(int $idVenda): string
    {
        $aamm = date('ym');
        $base = '29' . $aamm . preg_replace('/\D/', '', self::CNPJ) . '65001' . str_pad((string) $idVenda, 9, '0', STR_PAD_LEFT) . '1';
        $aleatorio = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $semDigito = substr($base . $aleatorio, 0, 43);
        // Dígito verificador módulo 11 (pesos 2 a 9 da direita para a esquerda)
        $soma = 0;
        $peso = 2;
        for ($i = strlen($semDigito) - 1; $i >= 0; $i--) {
            $soma += (int) $semDigito[$i] * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }
        $resto = $soma % 11;
        return $semDigito . ($resto < 2 ? 0 : 11 - $resto);
    }
}
