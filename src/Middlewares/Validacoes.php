<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Validador;

/**
 * Regras de validação de cada formulário (RNF09: validação também no back-end).
 * Cada função recebe o corpo da requisição e devolve os dados LIMPOS,
 * ou lança ErroApp 400 com a primeira mensagem de erro.
 */
final class Validacoes
{
    // RF01
    public static function cliente(array $dados): array
    {
        return (new Validador($dados))
            ->texto('nomeCliente', 'Informe o nome', 100)
            ->cpf('cpfCliente')
            ->digitos('telefoneCliente', 10, 11, 'Telefone inválido')
            ->email('emailCliente')
            ->digitos('cepCliente', 8, 8, 'CEP inválido', obrigatorio: false)
            ->data('dataNascimento', 'Data de nascimento inválida', obrigatorio: false)
            ->uf('ufCliente')
            ->booleano('consentimentoLgpd')
            ->dados();
    }

    // RF02
    public static function fornecedor(array $dados): array
    {
        return (new Validador($dados))
            ->texto('razaoSocial', 'Informe a razão social', 120)
            ->cnpj('cnpjFornecedor')
            ->digitos('telefoneFornecedor', 10, 11, 'Telefone inválido', obrigatorio: false)
            ->email('emailFornecedor')
            ->uf('ufFornecedor')
            ->dados();
    }

    // RF03
    public static function categoria(array $dados): array
    {
        return (new Validador($dados))
            ->texto('nomeCategoria', 'Informe o nome da categoria', 50)
            ->opcao('tipoCategoria', ['ROUPA', 'CALCADO', 'ACESSORIO'], 'Tipo inválido')
            ->dados();
    }

    // RF04
    public static function produto(array $dados): array
    {
        $v = (new Validador($dados))
            ->texto('nomeProduto', 'Informe o nome do produto', 100)
            ->inteiro('idCategoria', 'Escolha a categoria', minimo: 1)
            ->inteiro('idFornecedor', 'Escolha o fornecedor', minimo: 1)
            ->opcao('generoProduto', ['MASCULINO', 'FEMININO', 'UNISSEX', 'INFANTIL'], 'Gênero inválido', obrigatorio: false)
            ->decimal('precoCusto', 'Preço de custo inválido', minimo: 0)
            ->decimal('precoVenda', 'Preço de venda inválido', maiorQue: 0);
        $v->regra($v->valor('precoVenda') >= $v->valor('precoCusto'), 'O preço de venda não pode ser menor que o preço de custo');
        return $v->dados();
    }

    // RF05
    public static function variacao(array $dados): array
    {
        $v = (new Validador($dados))
            ->texto('tamanho', 'Informe o tamanho', 5)
            ->texto('cor', 'Informe a cor', 30)
            ->digitos('codigoBarras', 8, 13, 'Código de barras inválido', obrigatorio: false)
            ->inteiro('estoqueMinimo', 'Estoque mínimo inválido', minimo: 0, obrigatorio: false);
        $v->definir('tamanho', mb_strtoupper($v->valor('tamanho')));
        return $v->dados();
    }

    // RF06
    public static function usuario(array $dados, bool $senhaObrigatoria): array
    {
        $v = (new Validador($dados))
            ->texto('nomeUsuario', 'Informe o nome')
            ->formato('loginUsuario', '/^[a-z0-9._]{3,30}$/i', 'Login: 3 a 30 letras, números, ponto ou _')
            ->opcao('perfilAcesso', ['ADMIN', 'GERENTE', 'VENDEDOR', 'CAIXA', 'ESTOQUISTA'], 'Perfil inválido')
            ->cpf('cpfUsuario', obrigatorio: false)
            ->decimal('percentualComissao', 'Comissão inválida', minimo: 0, maximo: 100, obrigatorio: false)
            ->data('dataAdmissao', 'Data de admissão inválida', obrigatorio: false);
        $senha = (string) ($dados['senha'] ?? '');
        if ($senhaObrigatoria || $senha !== '') {
            $v->regra(mb_strlen($senha) >= 6, 'A senha precisa de pelo menos 6 caracteres');
        }
        return $v->dados();
    }

    // RF08
    public static function entrada(array $dados): array
    {
        $v = (new Validador($dados))
            ->inteiro('idFornecedor', 'Escolha o fornecedor', minimo: 1)
            ->data('dataEntrada', 'Data inválida')
            ->lista('itens', 'Adicione pelo menos um item');
        $itens = array_map(fn ($item) => (new Validador((array) $item))
            ->inteiro('idVariacao', 'Item inválido', minimo: 1)
            ->inteiro('quantidade', 'Quantidade inválida', minimo: 1)
            ->decimal('custoUnitario', 'Custo inválido', minimo: 0)
            ->dados(), $v->valor('itens'));
        return $v->definir('itens', $itens)->dados();
    }

    // RF09, RF10, RF11, RF18
    public static function venda(array $dados): array
    {
        $v = (new Validador($dados))
            ->inteiro('idCliente', 'Cliente inválido', minimo: 1, obrigatorio: false)
            ->inteiro('idVendedor', 'Vendedor inválido', minimo: 1, obrigatorio: false)
            ->lista('itens', 'Adicione pelo menos um item')
            ->lista('pagamentos', 'Informe o pagamento')
            ->decimal('descontoPercentual', 'Desconto inválido', minimo: 0, maximo: 100, obrigatorio: false)
            ->inteiro('pontosResgatar', 'Pontos inválidos', minimo: 0, obrigatorio: false);

        $itens = array_map(fn ($item) => (new Validador((array) $item))
            ->inteiro('idVariacao', 'Item inválido', minimo: 1)
            ->inteiro('quantidade', 'Quantidade inválida', minimo: 1)
            ->dados(), $v->valor('itens'));

        $pagamentos = array_map(fn ($pagamento) => (new Validador((array) $pagamento))
            ->opcao('formaPagamento', ['DINHEIRO', 'PIX', 'DEBITO', 'CREDITO', 'VALE_TROCA'], 'Forma de pagamento inválida')
            ->decimal('valorPago', 'Valor pago inválido', maiorQue: 0)
            ->inteiro('numeroParcelas', 'Crédito em até 6x', minimo: 1, maximo: 6, obrigatorio: false)
            ->dados(), $v->valor('pagamentos'));

        return $v->definir('itens', $itens)->definir('pagamentos', $pagamentos)->dados();
    }

    // RF12
    public static function cancelamento(array $dados): array
    {
        $v = (new Validador($dados))->texto('motivoCancelamento', 'Descreva o motivo do cancelamento (5 a 200 caracteres)');
        $tamanho = mb_strlen($v->valor('motivoCancelamento'));
        return $v->regra($tamanho >= 5 && $tamanho <= 200, 'Descreva o motivo do cancelamento (5 a 200 caracteres)')->dados();
    }

    // RF13
    public static function troca(array $dados): array
    {
        $v = (new Validador($dados))
            ->inteiro('idItemVenda', 'Item inválido', minimo: 1)
            ->opcao('tipoOperacao', ['TROCA', 'DEVOLUCAO'], 'Operação inválida')
            ->opcao('motivoTroca', ['TAMANHO', 'COR', 'DEFEITO', 'ARREPENDIMENTO', 'OUTRO'], 'Motivo inválido')
            ->inteiro('quantidade', 'Quantidade inválida', minimo: 1);
        if ($v->valor('tipoOperacao') === 'TROCA') {
            $v->inteiro('idVariacaoNova', 'Escolha o produto novo da troca', minimo: 1);
        }
        return $v->dados();
    }

    // RF11
    public static function promocao(array $dados): array
    {
        // Sem abrangência informada, deduz pelo campo preenchido (produto ou categoria)
        $dados['abrangencia'] ??= !empty($dados['idProduto']) ? 'PRODUTO' : (!empty($dados['idCategoria']) ? 'CATEGORIA' : null);
        $v = (new Validador($dados))
            ->texto('nomePromocao', 'Informe o nome da promoção', 80)
            ->opcao('tipoDesconto', ['PERCENTUAL', 'VALOR_FIXO'], 'Tipo de desconto inválido')
            ->decimal('valorDesconto', 'Desconto inválido', maiorQue: 0)
            ->data('dataInicio', 'Data inicial inválida')
            ->data('dataFim', 'Data final inválida')
            ->opcao('abrangencia', ['TODOS', 'CATEGORIA', 'PRODUTO'], 'Escolha onde a promoção vale')
            ->inteiro('idCategoria', 'Categoria inválida', minimo: 1, obrigatorio: false)
            ->inteiro('idProduto', 'Produto inválido', minimo: 1, obrigatorio: false);
        $abrangencia = $v->valor('abrangencia');
        $v->regra($v->valor('tipoDesconto') !== 'PERCENTUAL' || $v->valor('valorDesconto') <= 100, 'Percentual máximo é 100%')
          ->regra($v->valor('dataFim') >= $v->valor('dataInicio'), 'A data final deve ser depois da inicial')
          ->regra($abrangencia !== 'CATEGORIA' || $v->valor('idCategoria'), 'Escolha a categoria da promoção')
          ->regra($abrangencia !== 'PRODUTO' || $v->valor('idProduto'), 'Escolha o produto da promoção');
        // Guarda só o campo que combina com a abrangência (o outro fica vazio)
        if ($abrangencia !== 'CATEGORIA') $v->definir('idCategoria', null);
        if ($abrangencia !== 'PRODUTO') $v->definir('idProduto', null);
        return $v->dados();
    }

    // RF15
    public static function valor(array $dados, string $campo): array
    {
        return (new Validador($dados))->decimal($campo, 'Valor inválido', minimo: 0)->dados();
    }

    public static function movimento(array $dados): array
    {
        return (new Validador($dados))
            ->opcao('tipoMovimento', ['SANGRIA', 'SUPRIMENTO'], 'Tipo inválido')
            ->decimal('valorMovimento', 'Valor inválido', maiorQue: 0)
            ->texto('descricaoMovimento', '', 150, obrigatorio: false)
            ->dados();
    }

    // RF20 — o relatório de estoque é a "foto" de agora: não precisa de período
    public static function periodo(array $query, bool $precisaPeriodo): array
    {
        $v = (new Validador($query))->opcao('formato', ['json', 'pdf', 'csv'], 'Formato inválido', obrigatorio: false);
        if ($precisaPeriodo) {
            $v->data('inicio', 'Data inicial inválida')
              ->data('fim', 'Data final inválida')
              ->regra($v->valor('fim') >= $v->valor('inicio'), 'A data final deve ser depois da inicial');
        }
        return $v->dados();
    }
}
