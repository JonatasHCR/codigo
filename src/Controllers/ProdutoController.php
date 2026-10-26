<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\ProdutoModel;
use App\Utils\Imagem;
use PDOException;

final class ProdutoController
{
    // RF17: consulta com filtros na query string
    public static function listar(Requisicao $req): void
    {
        Resposta::json(ProdutoModel::buscar($req->query));
    }

    public static function buscar(Requisicao $req): void
    {
        $produto = ProdutoModel::buscarPorId((int) $req->param('id'));
        if (!$produto) throw new ErroApp('Produto não encontrado', 404);
        Resposta::json($produto);
    }

    // PDV: leitor de código de barras
    public static function porCodigoBarras(Requisicao $req): void
    {
        $variacao = ProdutoModel::buscarPorCodigoBarras($req->param('codigo'));
        if (!$variacao) throw new ErroApp('Produto não encontrado para este código', 404);
        Resposta::json($variacao);
    }

    public static function variacao(Requisicao $req): void
    {
        $variacao = ProdutoModel::buscarVariacao((int) $req->param('idVariacao'));
        if (!$variacao) throw new ErroApp('Variação não encontrada', 404);
        Resposta::json($variacao);
    }

    public static function criar(Requisicao $req): void
    {
        $dados = Validacoes::produto($req->corpo);
        try {
            Resposta::json(['idProduto' => ProdutoModel::criar($dados)], 201);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('Código do produto já existe', 409);
            if (($erro->errorInfo[1] ?? null) === 1452) throw new ErroApp('Categoria ou fornecedor inexistente');
            throw $erro;
        }
    }

    public static function atualizar(Requisicao $req): void
    {
        $dados = Validacoes::produto($req->corpo);
        try {
            if (!ProdutoModel::atualizar((int) $req->param('id'), $dados)) throw new ErroApp('Produto não encontrado', 404);
            Resposta::json(['mensagem' => 'Produto atualizado']);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('Código do produto já existe', 409);
            throw $erro;
        }
    }

    public static function inativar(Requisicao $req): void
    {
        if (!ProdutoModel::inativar((int) $req->param('id'))) throw new ErroApp('Produto não encontrado', 404);
        Resposta::vazio();
    }

    // RF04: foto do produto, enviada como formulário multipart (campo "foto")
    public static function enviarFoto(Requisicao $req): void
    {
        $id = (int) $req->param('id');
        $produto = ProdutoModel::buscarPorId($id) ?? throw new ErroApp('Produto não encontrado', 404);
        $caminho = Imagem::salvar($req->arquivo('foto'), "produto-$id");
        ProdutoModel::atualizarFoto($id, $caminho);
        Imagem::apagar($produto['fotoProduto']);   // a foto antiga não fica ocupando espaço
        Resposta::json(['fotoProduto' => $caminho]);
    }

    public static function removerFoto(Requisicao $req): void
    {
        $id = (int) $req->param('id');
        $produto = ProdutoModel::buscarPorId($id) ?? throw new ErroApp('Produto não encontrado', 404);
        ProdutoModel::atualizarFoto($id, null);
        Imagem::apagar($produto['fotoProduto']);
        Resposta::vazio();
    }

    // RF05: grade (tamanho + cor)
    public static function criarVariacao(Requisicao $req): void
    {
        $dados = Validacoes::variacao($req->corpo);
        try {
            Resposta::json(['idVariacao' => ProdutoModel::criarVariacao((int) $req->param('id'), $dados)], 201);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('Esta grade (tamanho + cor) ou código de barras já existe', 409);
            if (($erro->errorInfo[1] ?? null) === 1452) throw new ErroApp('Produto não encontrado', 404);
            throw $erro;
        }
    }

    public static function atualizarVariacao(Requisicao $req): void
    {
        $dados = Validacoes::variacao($req->corpo);
        try {
            if (!ProdutoModel::atualizarVariacao((int) $req->param('idVariacao'), $dados)) throw new ErroApp('Variação não encontrada', 404);
            Resposta::json(['mensagem' => 'Variação atualizada']);
        } catch (PDOException $erro) {
            if (($erro->errorInfo[1] ?? null) === 1062) throw new ErroApp('Esta grade (tamanho + cor) ou código de barras já existe', 409);
            throw $erro;
        }
    }
}
