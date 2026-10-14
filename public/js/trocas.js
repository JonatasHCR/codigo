import { api, enviar } from './api.js';
import { iniciarPagina } from './menu.js';
import { formatarMoeda, formatarData, nome } from './formatar.js';
import { celula, linhaVazia, mostrarMensagem, debounce } from './ui.js';

const $ = (s) => document.querySelector(s);
const mensagem = $('#mensagem');
let venda = null;
let sugestoes = new Map();

await iniciarPagina();

async function buscarVenda(idVenda) {
  venda = await api(`/api/vendas/${idVenda}`);
  const proporcao = Number(venda.valorBruto) ? Number(venda.valorTotal) / Number(venda.valorBruto) : 0;
  $('#infoVenda').textContent = `Venda nº ${venda.idVenda} · ${formatarData(venda.dataHoraVenda)} · ` +
    `${venda.nomeCliente || 'sem cliente'} · ${nome(venda.statusVenda)}`;
  $('#secaoItens').hidden = venda.statusVenda !== 'FINALIZADA';
  if (venda.statusVenda !== 'FINALIZADA') return mostrarMensagem(mensagem, 'Venda cancelada não pode ter troca.');

  $('#itensVenda').replaceChildren(...venda.itens.map((i, indice) => {
    const tr = document.createElement('tr');
    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = 'idItemVenda';
    radio.value = i.idItemVenda;
    radio.disabled = i.quantidadeDisponivelTroca <= 0;
    radio.checked = indice === 0 && !radio.disabled;
    radio.setAttribute('aria-label', i.nomeProduto);
    celula(tr, radio);
    celula(tr, `${i.nomeProduto} ${i.tamanho}/${i.cor}`);
    celula(tr, i.quantidade, 'numero');
    celula(tr, i.quantidadeDisponivelTroca, 'numero');
    celula(tr, formatarMoeda(Number(i.subtotal) / i.quantidade * proporcao), 'numero');
    return tr;
  }));
}

$('#formBuscaVenda').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  mostrarMensagem(mensagem, '');
  try {
    await buscarVenda(evento.currentTarget.idVenda.value);
  } catch (erro) {
    $('#secaoItens').hidden = true;
    mostrarMensagem(mensagem, erro.message);
  }
});

$('#tipoOperacao').addEventListener('change', (e) => {
  $('#campoNovo').hidden = e.currentTarget.value !== 'TROCA';
});

$('#buscaNovo').addEventListener('input', (evento) => {
  const texto = evento.currentTarget.value.trim();
  if (sugestoes.has(texto) || texto.length < 2) return;
  debounce(async () => {
    const produtos = await api(`/api/produtos?somenteDisponivel=true&termo=${encodeURIComponent(texto)}`);
    sugestoes = new Map();
    for (const p of produtos) for (const v of p.variacoes) {
      sugestoes.set(`${p.nomeProduto} — ${v.tamanho}/${v.cor} — ${formatarMoeda(p.precoVenda)}`, v.idVariacao);
    }
    $('#sugestoesNovo').replaceChildren(...[...sugestoes.keys()].map((t) => {
      const o = document.createElement('option');
      o.value = t;
      return o;
    }));
  });
});

async function idVariacaoNova() {
  const texto = $('#buscaNovo').value.trim();
  if (sugestoes.has(texto)) return sugestoes.get(texto);
  if (/^\d{8,13}$/.test(texto)) return (await api(`/api/produtos/codigo/${texto}`)).idVariacao;
  throw new Error('Escolha o produto novo na lista ou leia o código de barras');
}

$('#formTroca').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const form = evento.currentTarget;
  const selecionado = document.querySelector('input[name="idItemVenda"]:checked');
  if (!selecionado) return mostrarMensagem(mensagem, 'Escolha o item que está voltando');
  try {
    const dados = {
      idItemVenda: Number(selecionado.value),
      tipoOperacao: form.tipoOperacao.value,
      motivoTroca: form.motivoTroca.value,
      quantidade: Number(form.quantidade.value),
    };
    if (dados.tipoOperacao === 'TROCA') dados.idVariacaoNova = await idVariacaoNova();

    const r = await enviar('/api/trocas', 'POST', dados);
    let texto = `${nome(dados.tipoOperacao)} registrada.`;
    if (r.valorDiferenca > 0) texto += ` Cobrar a diferença de ${formatarMoeda(r.valorDiferenca)} (lançada no seu caixa).`;
    if (r.vale) texto += ` VALE-TROCA ${r.vale.codigoVale} de ${formatarMoeda(r.vale.valorVale)}, válido até ${formatarData(r.vale.dataValidade)}.`;
    mostrarMensagem(mensagem, texto, 'sucesso');
    form.reset();
    $('#campoNovo').hidden = false;
    await Promise.all([buscarVenda(venda.idVenda), carregarTrocas()]);
  } catch (erro) {
    mostrarMensagem(mensagem, erro.message);
  }
});

// RF14: consulta de vale
$('#formVale').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const saida = $('#resultadoVale');
  try {
    const vale = await api(`/api/trocas/vales/${encodeURIComponent(evento.currentTarget.codigo.value.trim())}`);
    mostrarMensagem(saida,
      `${vale.codigoVale}: ${nome(vale.statusVale) || vale.statusVale} · saldo ${formatarMoeda(vale.saldoVale)} de ` +
      `${formatarMoeda(vale.valorVale)} · válido até ${formatarData(vale.dataValidade)}` +
      `${vale.nomeCliente ? ` · ${vale.nomeCliente}` : ''}`,
      vale.statusVale === 'ATIVO' ? 'sucesso' : 'erro');
  } catch (erro) {
    mostrarMensagem(saida, erro.message);
  }
});

async function carregarTrocas() {
  const trocas = await api('/api/trocas');
  const tbody = $('#listaTrocas');
  if (!trocas.length) return linhaVazia(tbody, 8);
  tbody.replaceChildren(...trocas.map((t) => {
    const tr = document.createElement('tr');
    celula(tr, formatarData(t.dataTroca));
    celula(tr, t.idVenda);
    celula(tr, nome(t.tipoOperacao));
    celula(tr, nome(t.motivoTroca));
    celula(tr, `${t.nomeProduto} ${t.tamanho}/${t.cor}`);
    celula(tr, t.quantidade, 'numero');
    celula(tr, formatarMoeda(t.valorDiferenca), 'numero');
    celula(tr, t.codigoVale || '');
    return tr;
  }));
}

const idDaUrl = new URLSearchParams(location.search).get('venda');
if (idDaUrl) {
  $('#formBuscaVenda').idVenda.value = idDaUrl;
  buscarVenda(idDaUrl).catch((erro) => mostrarMensagem(mensagem, erro.message));
}
carregarTrocas().catch((erro) => mostrarMensagem(mensagem, erro.message));
