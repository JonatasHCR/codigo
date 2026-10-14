<?php
/**
 * Ponto de entrada da API ("front controller").
 * O Apache manda para cá toda requisição que começa com /api (ver public/.htaccess).
 * Aqui ficam: inicialização, rotas e o TRATAMENTO CENTRAL DE ERROS.
 */
declare(strict_types=1);

use App\Config\Sessao;
use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;

require __DIR__ . '/../src/bootstrap.php';

try {
    Sessao::iniciar();
    $router = require __DIR__ . '/../src/rotas.php';
    $router->despachar(Requisicao::atual());
} catch (ErroApp $erro) {
    // Regra de negócio violada: status e mensagem definidos por quem lançou o erro
    Resposta::json(['erro' => $erro->getMessage()], $erro->status);
} catch (PDOException $erro) {
    // Erros conhecidos do MySQL viram mensagens claras
    $codigo = $erro->errorInfo[1] ?? null;
    match ($codigo) {
        1062 => Resposta::json(['erro' => 'Já existe um registro com esses dados (campo único repetido)'], 409),
        3819 => Resposta::json(['erro' => 'Operação recusada por uma regra do banco de dados'], 400),
        1451 => Resposta::json(['erro' => 'Registro em uso; inative em vez de excluir'], 409),
        1452 => Resposta::json(['erro' => 'Registro relacionado não encontrado'], 400),
        default => erroInterno($erro),
    };
} catch (Throwable $erro) {
    erroInterno($erro);
}

// Erro inesperado: o usuário recebe uma mensagem genérica; o detalhe vai só para o log
function erroInterno(Throwable $erro): void
{
    error_log((string) $erro);
    Resposta::json(['erro' => 'Erro interno do servidor'], 500);
}
