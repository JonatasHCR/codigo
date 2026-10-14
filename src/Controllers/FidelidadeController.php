<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Models\FidelidadeModel;
use App\Utils\Loja;

final class FidelidadeController
{
    // RF18: saldo de pontos do cliente (usado no PDV)
    public static function saldo(Requisicao $req): void
    {
        $conta = FidelidadeModel::buscarPorCliente((int) $req->param('idCliente'));
        if (!$conta) throw new ErroApp('Cliente sem conta de fidelidade', 404);
        $resgatesPossiveis = intdiv((int) $conta['saldoPontos'], Loja::PONTOS_POR_RESGATE);
        Resposta::json([...$conta, 'regras' => Loja::regrasFidelidade(), 'resgatesPossiveis' => $resgatesPossiveis]);
    }
}
