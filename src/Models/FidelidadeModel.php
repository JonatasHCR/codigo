<?php
declare(strict_types=1);

namespace App\Models;

final class FidelidadeModel extends Model
{
    public static function buscarPorCliente(int $idCliente): ?array
    {
        return self::um(
            'SELECT f.*, c.nomeCliente FROM tb_fidelidade f JOIN tb_cliente c ON c.idCliente = f.idCliente
              WHERE f.idCliente = ?',
            [$idCliente]
        );
    }
}
