import { api, enviar } from './api.js';
import { iniciarPagina } from './menu.js';
import { formatarMoeda, formatarData, hojeIso } from './formatar.js';
import { celula, botao, linhaVazia, mostrarMensagem, preencherSelect, debounce } from './ui.js';

const mensagem = document.querySelector('#mensagem');
const formEntrada = document.querySelector('#formEntrada');
const formItem = document.querySelector('#formItem');
const buscaProduto = document.querySelector('#buscaProduto');
const sugestoes = document.querySelector('#sugestoes');
const itensEntrada = document.querySelector('#itensEntrada');

await iniciarPagina();

const itens = new Map();          // idVariacao → { descricao, quantidade, custoUnitario }
let opcoes = new Map();           // texto da sugestão → variação

const descrever = (p, v) => `${p.nomeProduto} — ${v.tamanho}/${v.cor}${v.codigoBarras ? ` (${v.codigoBarras})` : ''}`;

// Sugestões enquanto digita (datalist nativo do HTML)
buscaProduto.addEventListener('input', () => debounce(async () => {
  const termo = buscaProduto.value.trim();
  if (termo.length < 2 || opcoes.has(termo)) return;
  const produtos = await api(`/api/produtos?termo=${encodeURIComponent(termo)}`);
  opcoes = new Map();
  for (const p of produtos) for (const v of p.variacoes) opcoes.set(descrever(p, v), { ...v, precoCusto: null });
  sugestoes.replaceChildren(...[...opcoes.keys()].map((texto) => {
    const opcao = document.createElement('option');
    opcao.value = texto;
    return opcao;
  }));
}));

function desenharItens() {
  let total = 0;
  if (!itens.size) linhaVazia(itensEntrada, 5, 'Nenhum item adicionado.');
  else {
    itensEntrada.replaceChildren(...[...itens].map(([idVariacao, item]) => {
      const subtotal = item.quantidade * item.custoUnitario;
      total += subtotal;
      const tr = document.createElement('tr');
      celula(tr, item.descricao);
      celula(tr, item.quantidade, 'numero');
      celula(tr, formatarMoeda(item.custoUnitario), 'numero');
      celula(tr, formatarMoeda(subtotal), 'numero');
      celula(tr, botao('Remover', () => { itens.delete(idVariacao); desenharItens(); }));
      return tr;
    }));
  }
  document.querySelector('#totalEntrada').textContent = formatarMoeda(total);
}

formItem.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  try {
    let texto = buscaProduto.value.trim();
    let variacao = opcoes.get(texto);
    if (!variacao && /^\d{8,13}$/.test(texto)) {         // leu um código de barras
      const v = await api(`/api/produtos/codigo/${texto}`);
      variacao = v;
      texto = descrever(v, v);
    }
    if (!variacao) return mostrarMensagem(mensagem, 'Escolha um produto da lista de sugestões ou leia o código de barras');

    const quantidade = Number(formItem.quantidade.value);
    const custoUnitario = Number(formItem.custoUnitario.value);
    const atual = itens.get(variacao.idVariacao);
    itens.set(variacao.idVariacao, {
      descricao: texto, quantidade: (atual?.quantidade || 0) + quantidade, custoUnitario,
    });
    desenharItens();
    formItem.reset();
    buscaProduto.focus();
    mostrarMensagem(mensagem, '');
  } catch (erro) {
    mostrarMensagem(mensagem, erro.message);
  }
});

document.querySelector('#registrar').addEventListener('click', async (evento) => {
  if (!formEntrada.reportValidity()) return;
  if (!itens.size) return mostrarMensagem(mensagem, 'Adicione pelo menos um item');
  const botaoRegistrar = evento.currentTarget;
  botaoRegistrar.disabled = true;
  try {
    const dados = Object.fromEntries(new FormData(formEntrada));
    dados.itens = [...itens].map(([idVariacao, i]) => ({ idVariacao, quantidade: i.quantidade, custoUnitario: i.custoUnitario }));
    const resultado = await enviar('/api/estoque/entradas', 'POST', dados);
    mostrarMensagem(mensagem, `Entrada nº ${resultado.idEntrada} registrada: ${formatarMoeda(resultado.valorTotalEntrada)}. Estoque atualizado!`, 'sucesso');
    itens.clear();
    desenharItens();
    await Promise.all([carregarAlertas(), carregarEntradas()]);
  } catch (erro) {
    mostrarMensagem(mensagem, erro.message);
  } finally {
    botaoRegistrar.disabled = false;
  }
});

async function carregarAlertas() {
  const alertas = await api('/api/estoque/alertas');
  const tbody = document.querySelector('#listaAlertas');
  if (!alertas.length) return linhaVazia(tbody, 6, 'Nenhum produto abaixo do mínimo. 👍');
  tbody.replaceChildren(...alertas.map((a) => {
    const tr = document.createElement('tr');
    celula(tr, a.nomeProduto);
    celula(tr, a.tamanho);
    celula(tr, a.cor);
    celula(tr, a.quantidadeEstoque, 'numero');
    celula(tr, a.estoqueMinimo, 'numero');
    celula(tr, botao('Repor', () => {
      const texto = `${a.nomeProduto} — ${a.tamanho}/${a.cor}`;
      opcoes.set(texto, { idVariacao: a.idVariacao });
      buscaProduto.value = texto;
      formItem.quantidade.focus();
    }));
    return tr;
  }));
}

async function carregarEntradas() {
  const entradas = await api('/api/estoque/entradas');
  const tbody = document.querySelector('#listaEntradas');
  if (!entradas.length) return linhaVazia(tbody, 6);
  tbody.replaceChildren(...entradas.map((e) => {
    const tr = document.createElement('tr');
    celula(tr, formatarData(e.dataEntrada));
    celula(tr, e.numeroNotaFiscal);
    celula(tr, e.razaoSocial);
    celula(tr, e.totalItens, 'numero');
    celula(tr, formatarMoeda(e.valorTotalEntrada), 'numero');
    celula(tr, e.nomeUsuario);
    return tr;
  }));
}

const fornecedores = await api('/api/fornecedores');
preencherSelect(document.querySelector('#selFornecedor'), fornecedores, 'idFornecedor', 'razaoSocial', 'Escolha...');
formEntrada.dataEntrada.value = hojeIso();
desenharItens();
await Promise.all([carregarAlertas(), carregarEntradas()]).catch((erro) => mostrarMensagem(mensagem, erro.message));
