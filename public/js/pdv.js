import { api, enviar } from './api.js';
import { iniciarPagina } from './menu.js';
import { formatarMoeda, formatarData, nome } from './formatar.js';
import { pedirAutorizacao } from './autorizacao.js';
import { botao, mostrarMensagem as mostrar, preencherSelect, debounce } from './ui.js';

const $ = (seletor) => document.querySelector(seletor);
const mensagem = $('#mensagem');
const mostrarMensagem = (texto, tipo) => mostrar(mensagem, texto, tipo);
const arredondar = (v) => Math.round(v * 100) / 100;

const PONTOS_POR_RESGATE = 100;
const VALOR_POR_RESGATE = 5;

const carrinho = new Map();      // idVariacao → { produto, quantidade }
let pagamentos = [];
let formaAtual = 'DINHEIRO';
let cliente = null;              // { idCliente, nomeCliente, saldoPontos }
let clientesSugeridos = new Map();
let promocoes = [];
let valeConsultado = null;       // { codigo, disponivel, dataValidade } — vale digitado no pagamento

const usuario = await iniciarPagina();

// ---------------------------------------------------------------------------
// Aparência da peça: foto do produto ou um "tecido" com a cor da variação
// ---------------------------------------------------------------------------
const CORES = {
  azul: '#2f5aa8', preto: '#2c2f36', branco: '#cfcbc2', verde: '#3f7a5a', vermelho: '#a33a3a', rosa: '#c97a95',
  nude: '#c99a6e', marrom: '#7a4a2a', bege: '#c2ab88', cinza: '#7b8285', amarelo: '#c9a23a', floral: '#b9577a',
  jeans: '#3d5578', vinho: '#6e2436', laranja: '#c8692e', roxo: '#5b3f8a', caramelo: '#a8692f',
};
function corDaPeca(nomeCor = '') {
  const chave = nomeCor.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().split(' ')[0];
  if (CORES[chave]) return CORES[chave];
  let h = 0;
  for (const c of chave) h = (h * 31 + c.charCodeAt(0)) % 360;
  return `hsl(${h} 32% 42%)`;
}
function pintar(elemento, produto) {
  const cor = corDaPeca(produto.cor);
  if (produto.fotoProduto) {
    elemento.style.backgroundImage = `url("${encodeURI(produto.fotoProduto)}")`;
  } else {
    elemento.style.background = `linear-gradient(160deg, ${cor}, color-mix(in srgb, ${cor} 60%, #000))`;
  }
}

// Mesma regra do servidor (RF11): a maior promoção ativa da loja toda, da categoria ou do produto
function descontoPromocao(produto) {
  const preco = Number(produto.precoVenda);
  let melhor = 0;
  for (const p of promocoes) {
    const vale = p.abrangencia === 'TODOS' || p.idProduto === produto.idProduto || p.idCategoria === produto.idCategoria;
    if (!vale) continue;
    const valor = Number(p.valorDesconto);
    melhor = Math.max(melhor, Math.min(preco, p.tipoDesconto === 'PERCENTUAL' ? arredondar(preco * valor / 100) : valor));
  }
  return melhor;
}

// ---------------------------------------------------------------------------
// Vitrine (RF17): cartões das peças com estoque
// ---------------------------------------------------------------------------
function criarCartao(produto, variacao) {
  const peca = { ...variacao, idProduto: produto.idProduto, idCategoria: produto.idCategoria, precoVenda: produto.precoVenda, fotoProduto: produto.fotoProduto };
  const desconto = descontoPromocao(peca);

  const cartao = document.createElement('button');
  cartao.type = 'button';
  cartao.className = 'cartao-peca';
  cartao.setAttribute('aria-label', `Adicionar ${produto.nomeProduto} ${variacao.tamanho} ${variacao.cor}`);

  const foto = document.createElement('span');
  foto.className = 'foto';
  pintar(foto, peca);
  if (desconto > 0 || variacao.quantidadeEstoque <= variacao.estoqueMinimo) {
    const selo = document.createElement('span');
    selo.className = desconto > 0 ? 'selo' : 'selo baixo';
    selo.textContent = desconto > 0 ? 'Promoção' : `Últimas ${variacao.quantidadeEstoque}`;
    foto.append(selo);
  }
  if (!produto.fotoProduto) {
    const inicial = document.createElement('em');
    inicial.textContent = produto.nomeProduto.charAt(0);
    foto.append(inicial);
  }
  const acao = document.createElement('span');
  acao.className = 'acao-adicionar';
  acao.textContent = 'Adicionar à sacola';
  foto.append(acao);

  const nomePeca = document.createElement('span');
  nomePeca.className = 'nome';
  nomePeca.textContent = produto.nomeProduto;
  const grade = document.createElement('small');
  grade.textContent = `${variacao.tamanho} · ${variacao.cor} · ${variacao.quantidadeEstoque} em estoque`;
  const preco = document.createElement('span');
  preco.className = 'preco';
  if (desconto > 0) {
    const antigo = document.createElement('s');
    antigo.textContent = formatarMoeda(produto.precoVenda);
    preco.append(antigo);
  }
  preco.append(formatarMoeda(Number(produto.precoVenda) - desconto));

  cartao.append(foto, nomePeca, grade, preco);
  cartao.addEventListener('click', () => {
    api(`/api/produtos/variacoes/${variacao.idVariacao}`).then(adicionarAoCarrinho).catch((e) => mostrarMensagem(e.message));
  });
  return cartao;
}

async function carregarVitrine(termo = '') {
  const params = new URLSearchParams({ somenteDisponivel: 'true' });
  if (termo.trim()) params.set('termo', termo.trim());
  const produtos = await api(`/api/produtos?${params}`);
  const cartoes = [];
  for (const p of produtos) for (const v of p.variacoes) cartoes.push(criarCartao(p, v));
  const area = $('#cartoes');
  if (!cartoes.length) {
    const vazio = document.createElement('p');
    vazio.className = 'vazio-sacola';
    vazio.textContent = termo ? 'Nenhuma peça encontrada com esse nome.' : 'Nenhuma peça com estoque.';
    area.replaceChildren(vazio);
  } else {
    area.replaceChildren(...cartoes.slice(0, 48));
  }
}

$('#buscaProduto').addEventListener('input', (e) => {
  const termo = e.currentTarget.value;
  debounce(() => carregarVitrine(termo).catch((erro) => mostrarMensagem(erro.message)));
});

// Leitor de código de barras USB = teclado que digita o código e aperta Enter (nota 09)
$('#codigoBarras').addEventListener('keydown', async (evento) => {
  if (evento.key !== 'Enter') return;
  evento.preventDefault();
  const campo = evento.currentTarget;
  const codigo = campo.value.trim();
  campo.value = '';
  if (!codigo) return;
  try {
    adicionarAoCarrinho(await api(`/api/produtos/codigo/${encodeURIComponent(codigo)}`));
  } catch (erro) {
    mostrarMensagem(erro.message);
  }
  campo.focus();
});

// ---------------------------------------------------------------------------
// Sacola
// ---------------------------------------------------------------------------
function adicionarAoCarrinho(produto) {
  const item = carrinho.get(produto.idVariacao);
  const quantidadeNova = (item?.quantidade || 0) + 1;
  if (quantidadeNova > produto.quantidadeEstoque) {
    mostrarMensagem(`Estoque insuficiente: só há ${produto.quantidadeEstoque} unidade(s) de ${produto.nomeProduto} ${produto.tamanho}/${produto.cor}`);
    return;
  }
  if (item) item.quantidade++;
  else carrinho.set(produto.idVariacao, { produto, quantidade: 1 });
  mostrarMensagem(`${produto.nomeProduto} ${produto.tamanho}/${produto.cor} na sacola`, 'sucesso');
  desenharSacola();
}

function alterarQuantidade(idVariacao, delta) {
  const item = carrinho.get(idVariacao);
  const nova = item.quantidade + delta;
  if (nova <= 0) carrinho.delete(idVariacao);
  else if (nova > item.produto.quantidadeEstoque) return mostrarMensagem('Quantidade maior que o estoque');
  else item.quantidade = nova;
  desenharSacola();
}

function desenharSacola() {
  const lista = $('#itens');
  const pecas = [...carrinho.values()].reduce((s, i) => s + i.quantidade, 0);
  $('#contagem').textContent = `${pecas} ${pecas === 1 ? 'peça' : 'peças'}`;

  if (!carrinho.size) {
    const vazio = document.createElement('p');
    vazio.className = 'vazio-sacola';
    vazio.textContent = 'A sacola está vazia. Leia o código de barras ou escolha uma peça.';
    lista.replaceChildren(vazio);
  } else {
    lista.replaceChildren(...[...carrinho.values()].map(({ produto, quantidade }) => {
      const linha = document.createElement('div');
      linha.className = 'item';

      const mini = document.createElement('span');
      mini.className = 'mini';
      pintar(mini, produto);

      const descricao = document.createElement('span');
      descricao.textContent = produto.nomeProduto;
      const grade = document.createElement('small');
      grade.textContent = `${produto.tamanho} · ${produto.cor}`;
      descricao.append(grade);
      if (produto.descontoPromocao > 0) {
        const promo = document.createElement('small');
        promo.className = 'promocao';
        promo.textContent = `${produto.nomePromocao}: −${formatarMoeda(produto.descontoPromocao)} / un.`;
        descricao.append(promo);
      }

      const valores = document.createElement('span');
      valores.className = 'valores';
      const subtotal = document.createElement('span');
      subtotal.textContent = formatarMoeda((produto.precoVenda - produto.descontoPromocao) * quantidade);
      const qtd = document.createElement('span');
      qtd.className = 'quantidade';
      const numero = document.createElement('span');
      numero.textContent = quantidade;
      qtd.append(
        botao('−', () => alterarQuantidade(produto.idVariacao, -1), 'secundario'),
        numero,
        botao('+', () => alterarQuantidade(produto.idVariacao, +1), 'secundario'),
      );
      valores.append(subtotal, qtd);

      linha.append(mini, descricao, valores);
      return linha;
    }));
  }
  atualizarTotais();
}

// ---------------------------------------------------------------------------
// Cliente e fidelidade (RF18)
// ---------------------------------------------------------------------------
$('#buscaCliente').addEventListener('input', (evento) => {
  const texto = evento.currentTarget.value;
  const escolhido = clientesSugeridos.get(texto);
  if (escolhido) return selecionarCliente(escolhido);
  if (!texto) return selecionarCliente(null);
  debounce(async () => {
    if (texto.trim().length < 2) return;
    const clientes = await api(`/api/clientes?busca=${encodeURIComponent(texto)}`);
    clientesSugeridos = new Map(clientes.map((c) => [`${c.nomeCliente} — ${c.cpfCliente}`, c]));
    $('#sugestoesCliente').replaceChildren(...[...clientesSugeridos.keys()].map((t) => {
      const o = document.createElement('option');
      o.value = t;
      return o;
    }));
  });
});

async function selecionarCliente(c) {
  cliente = null;
  $('#campoPontos').hidden = true;
  $('#infoCliente').textContent = '';
  if (!c) return atualizarTotais();
  try {
    const conta = await api(`/api/fidelidade/${c.idCliente}`);
    cliente = { ...c, saldoPontos: conta.saldoPontos };
    $('#infoCliente').textContent = `${c.nomeCliente} · ${conta.saldoPontos} pontos`;
    const opcoes = [{ valor: 0, texto: 'Não resgatar' }];
    for (let p = PONTOS_POR_RESGATE; p <= conta.saldoPontos; p += PONTOS_POR_RESGATE) {
      opcoes.push({ valor: p, texto: `${p} pontos = ${formatarMoeda(p / PONTOS_POR_RESGATE * VALOR_POR_RESGATE)}` });
    }
    preencherSelect($('#pontosResgatar'), opcoes, 'valor', 'texto');
    $('#campoPontos').hidden = opcoes.length === 1;
  } catch (erro) {
    mostrarMensagem(erro.message);
  }
  atualizarTotais();
}

// ---------------------------------------------------------------------------
// Totais (prévia — o servidor recalcula com os preços do banco)
// ---------------------------------------------------------------------------
function calcularTotais() {
  let bruto = 0;
  for (const { produto, quantidade } of carrinho.values()) {
    bruto += (Number(produto.precoVenda) - produto.descontoPromocao) * quantidade;
  }
  bruto = arredondar(bruto);
  const percentual = Number($('#descontoPercentual').value) || 0;
  const pontos = cliente ? Number($('#pontosResgatar').value) || 0 : 0;
  const desconto = arredondar(Math.min(bruto, bruto * percentual / 100 + pontos / PONTOS_POR_RESGATE * VALOR_POR_RESGATE));
  const total = arredondar(bruto - desconto);
  const pago = arredondar(pagamentos.reduce((s, p) => s + p.valorPago, 0));
  return { bruto, desconto, total, pago, restante: arredondar(total - pago), percentual, pontos };
}

const paraTexto = (v) => v.toFixed(2).replace('.', ',');
// Aceita "150,50", "1.234,50" e "150.50"
const lerValor = (texto) => {
  const limpo = String(texto).replace(/[^\d,.]/g, '');
  return Number(limpo.includes(',') ? limpo.replace(/\./g, '').replace(',', '.') : limpo) || 0;
};

function atualizarTotais() {
  const t = calcularTotais();
  $('#totalBruto').textContent = formatarMoeda(t.bruto);
  $('#totalDesconto').textContent = `− ${formatarMoeda(t.desconto)}`;
  $('#totalVenda').textContent = formatarMoeda(t.total);
  $('#totalPago').textContent = formatarMoeda(t.pago);
  $('#rotuloRestante').textContent = t.restante >= 0 ? 'Falta pagar' : 'Troco';
  $('#restante').textContent = formatarMoeda(Math.abs(t.restante));
  // Já há pagamento lançado e ainda não cobre o total: destaca o que falta
  const pendente = pagamentos.length > 0 && t.restante > 0.001;
  $('#rotuloRestante').classList.toggle('pendente', pendente);
  $('#restante').classList.toggle('pendente', pendente);
  if (!$('#valorPagamento').matches(':focus')) {
    // Com vale-troca, sugere só o que o vale cobre; nas outras formas, o que falta
    const sugestao = formaAtual === 'VALE_TROCA' && valeConsultado
      ? Math.min(valeConsultado.disponivel, t.restante) : t.restante;
    $('#valorPagamento').value = sugestao > 0 ? paraTexto(sugestao) : '';
  }
  if (formaAtual === 'VALE_TROCA' && valeConsultado) explicarVale();
}

$('#descontoPercentual').addEventListener('input', atualizarTotais);
$('#pontosResgatar').addEventListener('change', atualizarTotais);

// ---------------------------------------------------------------------------
// Pagamentos (RF10)
// ---------------------------------------------------------------------------
document.querySelectorAll('[data-forma]').forEach((b) => {
  b.addEventListener('click', () => {
    formaAtual = b.dataset.forma;
    document.querySelectorAll('[data-forma]').forEach((x) => x.setAttribute('aria-pressed', x === b));
    $('#campoParcelas').hidden = formaAtual !== 'CREDITO';
    $('#campoVale').hidden = formaAtual !== 'VALE_TROCA';
    if (formaAtual === 'VALE_TROCA') {
      $('#infoVale').hidden = !valeConsultado;
      $('#codigoVale').focus();
    } else {
      $('#infoVale').hidden = true;
      $('#valorPagamento').focus();
    }
    atualizarTotais();
  });
});

// ---------------------------------------------------------------------------
// Vale-troca (RF14): mostra o saldo ANTES de lançar e quanto ele cobre da venda
// ---------------------------------------------------------------------------
// Quanto deste vale já foi lançado nesta mesma venda
const usadoDoVale = (codigo) =>
  arredondar(pagamentos.filter((p) => p.codigoVale === codigo).reduce((s, p) => s + p.valorPago, 0));

function infoVale(texto, tipo = '') {
  const info = $('#infoVale');
  info.className = `info-vale ${tipo}`;
  info.replaceChildren(...texto);   // lista de textos e <strong>
  info.hidden = false;
}
const forte = (texto) => Object.assign(document.createElement('strong'), { textContent: texto });

function explicarVale() {
  const { disponivel, dataValidade } = valeConsultado;
  const { restante } = calcularTotais();
  const partes = ['Saldo do vale: ', forte(formatarMoeda(disponivel)), ` · válido até ${formatarData(dataValidade)}. `];
  if (disponivel <= 0) {
    return infoVale([...partes, 'Este vale já foi todo usado nesta venda.'], 'erro');
  }
  if (restante <= 0) return infoVale([...partes, 'A venda já está paga.']);
  if (disponivel < restante) {
    return infoVale([...partes, 'Ele cobre só parte da compra: depois ainda ', forte(`faltará ${formatarMoeda(restante - disponivel)}`),
      ', a pagar com outra forma.'], 'aviso');
  }
  const sobra = arredondar(disponivel - restante);
  infoVale([...partes, 'Ele cobre a compra inteira',
    sobra > 0 ? ` e sobrarão ${formatarMoeda(sobra)} no vale para outra compra.` : '.']);
}

let esperaVale;
async function consultarVale() {
  const codigo = $('#codigoVale').value.trim().toUpperCase();
  valeConsultado = null;
  if (!codigo) { $('#infoVale').hidden = true; return null; }
  try {
    const vale = await api(`/api/trocas/vales/${encodeURIComponent(codigo)}`);
    if ($('#codigoVale').value.trim().toUpperCase() !== codigo) return null;   // digitou outro código enquanto buscava
    if (vale.statusVale !== 'ATIVO') {
      infoVale([`Vale ${(nome(vale.statusVale) || vale.statusVale).toLowerCase()}: não pode ser usado.`], 'erro');
      return null;
    }
    valeConsultado = {
      codigo, dataValidade: vale.dataValidade,
      disponivel: arredondar(Number(vale.saldoVale) - usadoDoVale(codigo)),
    };
    $('#valorPagamento').blur();
    atualizarTotais();   // sugere o valor e mostra a explicação
    return valeConsultado;
  } catch (erro) {
    infoVale([erro.message], 'erro');
    return null;
  }
}

$('#codigoVale').addEventListener('input', () => {
  valeConsultado = null;
  $('#infoVale').hidden = true;
  clearTimeout(esperaVale);
  if ($('#codigoVale').value.trim().length >= 6) esperaVale = setTimeout(consultarVale, 400);
});

$('#formPagamento').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const valorPago = lerValor($('#valorPagamento').value);
  if (!(valorPago > 0)) return mostrarMensagem('Informe o valor do pagamento');

  const pagamento = { formaPagamento: formaAtual, valorPago };
  if (formaAtual === 'CREDITO') pagamento.numeroParcelas = Number($('#parcelas').value);
  const { restante } = calcularTotais();
  if (formaAtual === 'VALE_TROCA') {
    const codigo = $('#codigoVale').value.trim().toUpperCase();
    if (!codigo) return mostrarMensagem('Digite o código do vale-troca');
    const vale = valeConsultado?.codigo === codigo ? valeConsultado : await consultarVale();
    if (!vale) return mostrarMensagem('Vale-troca inválido');
    if (valorPago > vale.disponivel + 0.001) {
      return mostrarMensagem(`O vale só tem ${formatarMoeda(vale.disponivel)} de saldo. Lance até esse valor e pague o resto com outra forma.`);
    }
    if (valorPago > restante + 0.001) {
      return mostrarMensagem(`Falta pagar só ${formatarMoeda(restante)}. Use esse valor do vale; o resto do saldo continua no vale.`);
    }
    pagamento.codigoVale = codigo;
    pagamento.saldoDepois = arredondar(vale.disponivel - valorPago);   // só para mostrar na lista
  } else if (formaAtual !== 'DINHEIRO' && valorPago > restante + 0.001) {
    return mostrarMensagem('Só pagamento em dinheiro pode ser maior que o valor que falta (troco)');
  }
  pagamentos.push(pagamento);
  $('#valorPagamento').blur();

  if (formaAtual === 'VALE_TROCA') {
    $('#codigoVale').value = '';
    valeConsultado = null;
    $('#infoVale').hidden = true;
    const falta = arredondar(restante - valorPago);
    if (falta > 0) {
      // O vale não cobriu tudo: avisa e já deixa pronto para receber o resto em dinheiro
      document.querySelector('[data-forma="DINHEIRO"]').click();
      $('#valorPagamento').blur();
      desenharPagamentos();
      return mostrarMensagem(`Vale de ${formatarMoeda(valorPago)} aplicado. Ainda falta pagar ${formatarMoeda(falta)}: escolha outra forma de pagamento.`, 'alerta');
    }
  }
  mostrarMensagem('');
  desenharPagamentos();
});

function desenharPagamentos() {
  $('#pagamentos').replaceChildren(...pagamentos.map((p, i) => {
    const li = document.createElement('li');
    const texto = document.createElement('span');
    const extra = p.numeroParcelas > 1 ? ` em ${p.numeroParcelas}x` : p.codigoVale ? ` (${p.codigoVale})` : '';
    texto.textContent = `${nome(p.formaPagamento)}${extra}: ${formatarMoeda(p.valorPago)}`;
    if (p.codigoVale) {
      const saldo = document.createElement('span');
      saldo.className = 'saldo-vale';
      saldo.textContent = p.saldoDepois > 0 ? `Ficam ${formatarMoeda(p.saldoDepois)} no vale` : 'Vale usado por inteiro';
      texto.append(saldo);
    }
    li.append(texto, botao('Remover', () => { pagamentos.splice(i, 1); desenharPagamentos(); }));
    return li;
  }));
  atualizarTotais();
}

// ---------------------------------------------------------------------------
// Finalizar (RF09) — o servidor faz tudo numa transação
// ---------------------------------------------------------------------------
$('#finalizar').addEventListener('click', async (evento) => {
  const botaoFinalizar = evento.currentTarget;
  const t = calcularTotais();
  if (!carrinho.size) return mostrarMensagem('A sacola está vazia');
  if (t.restante > 0.001) return mostrarMensagem(`Ainda falta pagar ${formatarMoeda(t.restante)}`);

  const corpo = {
    idCliente: cliente?.idCliente || null,
    itens: [...carrinho.values()].map(({ produto, quantidade }) => ({ idVariacao: produto.idVariacao, quantidade })),
    pagamentos: pagamentos.map(({ saldoDepois, ...p }) => p),   // saldoDepois é só da tela
    descontoPercentual: t.percentual,
    pontosResgatar: t.pontos,
  };
  if (!$('#campoVendedor').hidden) corpo.idVendedor = Number($('#vendedor').value);

  // RF11: acima de 10% pede a senha do gerente (o servidor confere com bcrypt)
  if (t.percentual > 10 && !['GERENTE', 'ADMIN'].includes(usuario.perfil)) {
    corpo.autorizacao = await pedirAutorizacao(`Desconto de ${t.percentual}% acima do limite de 10%.`);
    if (!corpo.autorizacao) return;
  }

  botaoFinalizar.disabled = true;
  try {
    const venda = await enviar('/api/vendas', 'POST', corpo);
    vendaConcluida(venda);
  } catch (erro) {
    mostrarMensagem(erro.message);
  } finally {
    botaoFinalizar.disabled = false;
  }
});

function vendaConcluida(venda) {
  const janela = $('#janelaConcluida');
  const partes = [`Venda nº ${venda.idVenda} · Total ${formatarMoeda(venda.valorTotal)}`];
  if (venda.troco > 0) partes.push(`Troco: ${formatarMoeda(venda.troco)}`);
  if (venda.pontosGerados > 0) partes.push(`+${venda.pontosGerados} pontos para o cliente`);
  $('#resumoVenda').textContent = partes.join(' · ');

  $('#imprimir').onclick = () => window.open(`/api/vendas/${venda.idVenda}/comprovante`, '_blank');
  const enviarComprovante = async (formato) => {
    const comprovante = await api(`/api/vendas/${venda.idVenda}/comprovante/texto`);
    const link = formato === 'WHATSAPP' ? comprovante.whatsapp : comprovante.email;
    if (!link) return alert('O cliente não tem e-mail cadastrado.');
    await enviar(`/api/vendas/${venda.idVenda}/comprovante/envio`, 'PATCH', { formatoEnvio: formato });
    window.open(link, '_blank');
  };
  $('#whatsapp').onclick = () => enviarComprovante('WHATSAPP').catch((e) => alert(e.message));
  $('#email').onclick = () => enviarComprovante('EMAIL').catch((e) => alert(e.message));
  $('#novaVenda').onclick = () => { janela.close(); };
  janela.addEventListener('close', limparVenda, { once: true });
  janela.showModal();
}

function limparVenda() {
  carrinho.clear();
  pagamentos = [];
  valeConsultado = null;
  $('#codigoVale').value = '';
  $('#infoVale').hidden = true;
  $('#buscaCliente').value = '';
  $('#descontoPercentual').value = 0;
  selecionarCliente(null);
  desenharPagamentos();
  desenharSacola();
  mostrarMensagem('');
  carregarVitrine($('#buscaProduto').value).catch(() => {});   // estoque mudou
  $('#codigoBarras').focus();
}

$('#limpar').addEventListener('click', () => {
  if (!carrinho.size || confirm('Esvaziar a sacola?')) limparVenda();
});

// ---------------------------------------------------------------------------
// Início
// ---------------------------------------------------------------------------
const [caixa, listaPromocoes] = await Promise.all([api('/api/caixa/atual'), api('/api/promocoes')]);
promocoes = listaPromocoes.filter((p) => p.ativaHoje && p.statusPromocao);
$('#avisoCaixa').hidden = Boolean(caixa);

if (usuario.perfil !== 'VENDEDOR') {
  const vendedores = await api('/api/usuarios/vendedores');
  preencherSelect($('#vendedor'), vendedores, 'idUsuario', 'nomeUsuario');
  $('#vendedor').value = usuario.id;
  $('#campoVendedor').hidden = false;
}
desenharSacola();
await carregarVitrine();
