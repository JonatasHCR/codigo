<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Erro de regra de negócio, com o status HTTP que deve ser devolvido.
 * Ex.: throw new ErroApp('Abra o caixa antes de registrar vendas', 400);
 */
final class ErroApp extends \RuntimeException
{
    public function __construct(string $mensagem, public readonly int $status = 400)
    {
        parent::__construct($mensagem);
    }
}
