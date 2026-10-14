<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\CaixaModel;

final class CaixaController
{
    private static function caixaAberto(Requisicao $req): int
    {
        $idCaixa = CaixaModel::buscarAbertoDoUsuario($req->usuario()['id']);
        if (!$idCaixa) throw new ErroApp('Você não tem caixa aberto', 400);
        return $idCaixa;
    }

    // Caixa atual do usuário logado (ou null, para a tela mostrar "Abrir caixa")
    public static function atual(Requisicao $req): void
    {
        $idCaixa = CaixaModel::buscarAbertoDoUsuario($req->usuario()['id']);
        Resposta::json($idCaixa ? CaixaModel::resumo($idCaixa) : null);
    }

    public static function abrir(Requisicao $req): void
    {
        $dados = Validacoes::valor($req->corpo, 'valorAbertura');
        Resposta::json(['idCaixa' => CaixaModel::abrir($req->usuario()['id'], $dados['valorAbertura'])], 201);
    }

    // Sangria (retirar dinheiro) ou suprimento (colocar dinheiro)
    public static function movimentar(Requisicao $req): void
    {
        $dados = Validacoes::movimento($req->corpo);
        Resposta::json(['idMovimento' => CaixaModel::movimentar(self::caixaAberto($req), $dados)], 201);
    }

    public static function fechar(Requisicao $req): void
    {
        $dados = Validacoes::valor($req->corpo, 'valorInformado');
        Resposta::json(CaixaModel::fechar(self::caixaAberto($req), $dados['valorInformado']));
    }

    public static function listar(Requisicao $req): void
    {
        Resposta::json(CaixaModel::listar());
    }

    public static function detalhe(Requisicao $req): void
    {
        $caixa = CaixaModel::resumo((int) $req->param('id'));
        if (!$caixa) throw new ErroApp('Caixa não encontrado', 404);
        Resposta::json($caixa);
    }
}
