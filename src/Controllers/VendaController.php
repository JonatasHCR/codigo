<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\CaixaModel;
use App\Models\VendaModel;
use App\Utils\Autorizacao;
use App\Utils\Dinheiro;
use App\Utils\Loja;
use App\Utils\Pdf;

final class VendaController
{
    public static function registrar(Requisicao $req): void
    {
        $dados = Validacoes::venda($req->corpo);
        $usuario = $req->usuario();

        // RF11: desconto manual acima de 10% só com a senha do gerente
        if (($dados['descontoPercentual'] ?? 0) > Loja::DESCONTO_MAXIMO_VENDEDOR) {
            Autorizacao::autorizarGerente($usuario, $req->corpo['autorizacao'] ?? null);
        }

        // RF15: é preciso ter o caixa aberto para vender
        $idCaixa = CaixaModel::buscarAbertoDoUsuario($usuario['id']);
        if (!$idCaixa) throw new ErroApp('Abra o caixa antes de registrar vendas', 400);

        // RF09: o vendedor logado vende em nome próprio; caixa/gerente informam o vendedor
        $idVendedor = $usuario['perfil'] === 'VENDEDOR' ? $usuario['id'] : (($dados['idVendedor'] ?? null) ?: $usuario['id']);

        Resposta::json(VendaModel::registrar($dados, $idVendedor, $idCaixa), 201);
    }

    public static function listar(Requisicao $req): void
    {
        Resposta::json(VendaModel::listar($req->query));
    }

    public static function detalhe(Requisicao $req): void
    {
        $venda = VendaModel::detalhe((int) $req->param('id'));
        if (!$venda) throw new ErroApp('Venda não encontrada', 404);
        Resposta::json($venda);
    }

    // RF12: cancelar só com autorização do gerente e motivo
    public static function cancelar(Requisicao $req): void
    {
        $dados = Validacoes::cancelamento($req->corpo);
        $usuario = $req->usuario();
        $idAutorizador = Autorizacao::autorizarGerente($usuario, $req->corpo['autorizacao'] ?? null);
        VendaModel::cancelar((int) $req->param('id'), $dados['motivoCancelamento'], $usuario['id'], $idAutorizador);
        Resposta::json(['mensagem' => 'Venda cancelada. Estoque e pontos foram devolvidos.']);
    }

    // RF19: comprovante em PDF (impressora térmica 80 mm)
    public static function comprovantePdf(Requisicao $req): void
    {
        $venda = VendaModel::detalhe((int) $req->param('id'));
        if (!$venda) throw new ErroApp('Venda não encontrada', 404);
        Pdf::cupom($venda);
    }

    // RF19: texto do comprovante + links para WhatsApp / e-mail (enviados pelos apps do usuário)
    public static function comprovanteTexto(Requisicao $req): void
    {
        $venda = VendaModel::detalhe((int) $req->param('id'));
        if (!$venda) throw new ErroApp('Venda não encontrada', 404);

        $linhas = ['*' . Loja::NOME . '*', "Comprovante {$venda['numeroComprovante']} — " . Pdf::dataBr(substr($venda['dataHoraVenda'], 0, 10)), ''];
        foreach ($venda['itens'] as $i) {
            $linhas[] = "{$i['quantidade']}x {$i['nomeProduto']} {$i['tamanho']}/{$i['cor']} — " . Dinheiro::formatar($i['subtotal']);
        }
        $linhas[] = '';
        $linhas[] = 'Total: ' . Dinheiro::formatar($venda['valorTotal']);
        if ($venda['pontosGerados'] > 0) $linhas[] = "Você ganhou {$venda['pontosGerados']} pontos de fidelidade!";
        $linhas[] = 'Obrigado pela preferência!';
        $texto = implode("\n", $linhas);

        $telefone = $venda['telefoneCliente'] ? '55' . $venda['telefoneCliente'] : '';
        Resposta::json([
            'texto' => $texto,
            'whatsapp' => "https://wa.me/$telefone?text=" . rawurlencode($texto),
            'email' => $venda['emailCliente']
                ? "mailto:{$venda['emailCliente']}?subject=" . rawurlencode("Comprovante {$venda['numeroComprovante']}")
                  . '&body=' . rawurlencode(str_replace('*', '', $texto))
                : null,
        ]);
    }

    public static function registrarEnvio(Requisicao $req): void
    {
        $formato = $req->corpo['formatoEnvio'] ?? '';
        if (!in_array($formato, ['IMPRESSO', 'EMAIL', 'WHATSAPP'], true)) throw new ErroApp('Formato inválido');
        if (!VendaModel::registrarEnvio((int) $req->param('id'), $formato)) throw new ErroApp('Venda não encontrada', 404);
        Resposta::vazio();
    }
}
