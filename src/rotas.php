<?php
/**
 * Todas as rotas da API e quem pode acessar cada uma.
 * Guardas: Auth::login() → 401 se não estiver logado; Auth::perfil(...) → 403 se o perfil não for permitido.
 * Rotas mais específicas vêm ANTES das genéricas (ex.: /codigo/:codigo antes de /:id).
 */
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CaixaController;
use App\Controllers\CategoriaController;
use App\Controllers\ClienteController;
use App\Controllers\EstoqueController;
use App\Controllers\FidelidadeController;
use App\Controllers\FornecedorController;
use App\Controllers\ProdutoController;
use App\Controllers\PromocaoController;
use App\Controllers\RelatorioController;
use App\Controllers\TrocaController;
use App\Controllers\UsuarioController;
use App\Controllers\VendaController;
use App\Core\Router;
use App\Middlewares\Auth;

$r = new Router();
$logado = Auth::login();
$vendem = Auth::perfil('ADMIN', 'GERENTE', 'VENDEDOR', 'CAIXA');
$gestao = Auth::perfil('ADMIN', 'GERENTE');
$estoquistas = Auth::perfil('ADMIN', 'GERENTE', 'ESTOQUISTA');

// RF07 — login aberto; o resto exige sessão
$r->grupo('/api/auth', [], function (Router $r) use ($logado) {
    $r->post('/login', [AuthController::class, 'login']);
    $r->post('/logout', [AuthController::class, 'logout']);
    $r->get('/eu', [AuthController::class, 'eu'], [$logado]);
});

// RF06 — funcionários (só ADMIN, menos a lista de vendedores usada no PDV)
$r->grupo('/api/usuarios', [$logado], function (Router $r) {
    $r->get('/vendedores', [UsuarioController::class, 'vendedores']);
    $r->grupo('', [Auth::perfil('ADMIN')], function (Router $r) {
        $r->get('', [UsuarioController::class, 'listar']);
        $r->get('/:id', [UsuarioController::class, 'buscar']);
        $r->post('', [UsuarioController::class, 'criar']);
        $r->put('/:id', [UsuarioController::class, 'atualizar']);
        $r->patch('/:id/inativar', [UsuarioController::class, 'inativar']);
        $r->patch('/:id/reativar', [UsuarioController::class, 'reativar']);
        $r->patch('/:id/desbloquear', [UsuarioController::class, 'desbloquear']);
    });
});

// RF01 + RNF06
$r->grupo('/api/clientes', [$logado], function (Router $r) use ($gestao) {
    $r->get('', [ClienteController::class, 'listar']);
    $r->get('/:id', [ClienteController::class, 'buscar']);
    $r->post('', [ClienteController::class, 'criar']);
    $r->put('/:id', [ClienteController::class, 'atualizar']);
    $r->patch('/:id/inativar', [ClienteController::class, 'inativar'], [$gestao]);
    $r->patch('/:id/anonimizar', [ClienteController::class, 'anonimizar'], [Auth::perfil('ADMIN')]);
});

// RF02
$r->grupo('/api/fornecedores', [$logado], function (Router $r) use ($estoquistas, $gestao) {
    $r->get('', [FornecedorController::class, 'listar']);
    $r->get('/:id', [FornecedorController::class, 'buscar']);
    $r->post('', [FornecedorController::class, 'criar'], [$estoquistas]);
    $r->put('/:id', [FornecedorController::class, 'atualizar'], [$estoquistas]);
    $r->patch('/:id/inativar', [FornecedorController::class, 'inativar'], [$gestao]);
});

// RF03
$r->grupo('/api/categorias', [$logado], function (Router $r) use ($estoquistas, $gestao) {
    $r->get('', [CategoriaController::class, 'listar']);
    $r->get('/:id', [CategoriaController::class, 'buscar']);
    $r->post('', [CategoriaController::class, 'criar'], [$estoquistas]);
    $r->put('/:id', [CategoriaController::class, 'atualizar'], [$estoquistas]);
    $r->patch('/:id/inativar', [CategoriaController::class, 'inativar'], [$gestao]);
});

// RF04, RF05, RF17
$r->grupo('/api/produtos', [$logado], function (Router $r) use ($estoquistas, $gestao) {
    $r->get('', [ProdutoController::class, 'listar']);
    $r->get('/codigo/:codigo', [ProdutoController::class, 'porCodigoBarras']);
    $r->get('/variacoes/:idVariacao', [ProdutoController::class, 'variacao']);
    $r->put('/variacoes/:idVariacao', [ProdutoController::class, 'atualizarVariacao'], [$estoquistas]);
    $r->get('/:id', [ProdutoController::class, 'buscar']);
    $r->post('', [ProdutoController::class, 'criar'], [$estoquistas]);
    $r->put('/:id', [ProdutoController::class, 'atualizar'], [$estoquistas]);
    $r->patch('/:id/inativar', [ProdutoController::class, 'inativar'], [$gestao]);
    $r->post('/:id/foto', [ProdutoController::class, 'enviarFoto'], [$estoquistas]);
    $r->delete('/:id/foto', [ProdutoController::class, 'removerFoto'], [$estoquistas]);
    $r->post('/:id/variacoes', [ProdutoController::class, 'criarVariacao'], [$estoquistas]);
});

// RF08, RF16
$r->grupo('/api/estoque', [$logado, $estoquistas], function (Router $r) {
    $r->get('/alertas', [EstoqueController::class, 'listarAlertas']);
    $r->get('/entradas', [EstoqueController::class, 'listarEntradas']);
    $r->post('/entradas', [EstoqueController::class, 'registrarEntrada']);
});

// RF15
$r->grupo('/api/caixa', [$logado, $vendem], function (Router $r) use ($gestao) {
    $r->get('/atual', [CaixaController::class, 'atual']);
    $r->post('/abrir', [CaixaController::class, 'abrir']);
    $r->post('/movimentos', [CaixaController::class, 'movimentar']);
    $r->post('/fechar', [CaixaController::class, 'fechar']);
    $r->get('', [CaixaController::class, 'listar'], [$gestao]);
    $r->get('/:id', [CaixaController::class, 'detalhe'], [$gestao]);
});

// RF11
$r->grupo('/api/promocoes', [$logado], function (Router $r) use ($gestao) {
    $r->get('', [PromocaoController::class, 'listar']);
    $r->get('/:id', [PromocaoController::class, 'buscar']);
    $r->post('', [PromocaoController::class, 'criar'], [$gestao]);
    $r->put('/:id', [PromocaoController::class, 'atualizar'], [$gestao]);
    $r->patch('/:id/desativar', [PromocaoController::class, 'desativar'], [$gestao]);
});

// RF09–RF12, RF18, RF19
$r->grupo('/api/vendas', [$logado, $vendem], function (Router $r) {
    $r->get('', [VendaController::class, 'listar']);
    $r->post('', [VendaController::class, 'registrar']);
    $r->get('/:id', [VendaController::class, 'detalhe']);
    $r->post('/:id/cancelar', [VendaController::class, 'cancelar']);
    $r->get('/:id/comprovante', [VendaController::class, 'comprovantePdf']);
    $r->get('/:id/comprovante/texto', [VendaController::class, 'comprovanteTexto']);
    $r->patch('/:id/comprovante/envio', [VendaController::class, 'registrarEnvio']);
});

// RF13, RF14
$r->grupo('/api/trocas', [$logado, $vendem], function (Router $r) {
    $r->get('', [TrocaController::class, 'listar']);
    $r->post('', [TrocaController::class, 'registrar']);
    $r->get('/vales/:codigo', [TrocaController::class, 'vale']);
});

// RF18
$r->grupo('/api/fidelidade', [$logado, $vendem], function (Router $r) {
    $r->get('/:idCliente', [FidelidadeController::class, 'saldo']);
});

// RF20
$r->grupo('/api/relatorios', [$logado, $gestao], function (Router $r) {
    $r->get('', [RelatorioController::class, 'tipos']);
    $r->get('/:tipo', [RelatorioController::class, 'gerar']);
});

return $r;
