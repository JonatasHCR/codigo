<?php
declare(strict_types=1);

namespace App\Utils;

final class Dinheiro
{
    /** Contas com float geram dízimas (0.1 + 0.2); arredondamos sempre para centavos */
    public static function arredondar(float $valor): float
    {
        return round($valor, 2);
    }

    /** 1234.5 → "R$ 1.234,50" */
    public static function formatar(float|string|null $valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    /** 1234.5 → "1234,50" (para CSV e cupom) */
    public static function numero(float|string|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '');
    }
}
