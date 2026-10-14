<?php
declare(strict_types=1);

namespace App\Utils;

final class Cpf
{
    public static function cpfValido(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;   // recusa 111.111.111-11

        for ($t = 9; $t < 11; $t++) {               // t=9: 1º dígito; t=10: 2º dígito
            $soma = 0;
            for ($i = 0; $i < $t; $i++) $soma += (int) $cpf[$i] * ($t + 1 - $i);   // pesos 10..2 e 11..2
            $digito = (($soma * 10) % 11) % 10;
            if ($digito !== (int) $cpf[$t]) return false;
        }
        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

        $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($t = 12; $t < 14; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) $soma += (int) $cnpj[$i] * $pesos[$i + 13 - $t];
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ($digito !== (int) $cnpj[$t]) return false;
        }
        return true;
    }

    /** LGPD (RNF06): "52998224725" → "***.982.247-**" */
    public static function mascarar(string $cpf): string
    {
        return '***.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-**';
    }
}
