import { api, enviar } from './api.js';
import { iniciarPagina } from './menu.js';
import { formatarMoeda, formatarData, nome } from './formatar.js';
import { pedirAutorizacao } from './autorizacao.js';
import { celula, botao, etiqueta, linhaVazia, mostrarMensagem, lerFormulario, debounce } from './ui.js';

const $ = (s) => document.querySelector(s);
const mensagem = $('#mensagem');
const usuario = await iniciarPagina();
const gerente = ['GERENTE', 'ADMIN'].includes(usuario.perfil);

async function carregar() {
  const filtros = lerFormulario($('#formFiltros'));
  const params = new URLSearchParams(Object.entries(filtros).filter(([, v]) => v));
  const vendas = await api(`/api/vendas?${params}`);
  if (!vendas.length) return linhaVazia($('#lista'), 7, 'Nenhuma venda encontrada.');
  $('#lista').replaceChildren(...vendas.map((v) => {
    const tr = document.createElement('tr');
    celula(tr, v.idVenda);
    celula(tr, formatarData(v.dataHoraVenda));
    celula(tr, v.nomeCliente || '—');
    celula(tr, v.nomeVendedor);
    celula(tr, formatarMoeda(v.valorTotal), 'numero');
    celula(tr, etiqueta(nome(v.statusVenda), v.statusVenda === 'FINALIZADA' ? 'ok' : 'erro'));
    celula(tr, botao('Detalhes', () => detalhar(v.idVenda)));
    return tr;
  }));
}

async function detalhar(idVenda) {
  try {
    const v = await api(`/api/vendas/${idVenda}`);
    $('#secaoDetalhe').hidden = false;
    $('#tituloDetalhe').textContent = `Venda nº ${v.idVenda} · ${v.numeroComprovante}`;
    const info = [
      formatarData(v.dataHoraVenda), `Vendedor: ${v.nomeVendedor}`, v.nomeCliente ? `Cliente: ${v.nomeCliente}` : 'Sem cliente',
      `Subtotal ${formatarMoeda(v.valorBruto)}`, `Desconto ${formatarMoeda(v.valorDesconto)}`, `Total ${formatarMoeda(v.valorTotal)}`,
    ];
    if (v.pontosGerados) info.push(`${v.pontosGerados} pontos gerados`);
    if (v.statusVenda === 'CANCELADA') info.push(`CANCELADA em ${formatarData(v.dataHoraCancelamento)}: ${v.motivoCancelamento}`);
    $('#infoDetalhe').textContent = info.join(' · ');

    $('#itensDetalhe').replaceChildren(...v.itens.map((i) => {
      const tr = document.createElement('tr');
      celula(tr, `${i.nomeProduto} ${i.tamanho}/${i.cor}`);
      celula(tr, i.quantidade, 'numero');
      celula(tr, formatarMoeda(i.precoUnitario), 'numero');
      celula(tr, Number(i.descontoItem) ? `− ${formatarMoeda(i.descontoItem)}` : '', 'numero');
      celula(tr, formatarMoeda(i.subtotal), 'numero');
      return tr;
    }));
    $('#pagamentosDetalhe').replaceChildren(...v.pagamentos.map((p) => {
      const li = document.createElement('li');
      const parcelas = p.numeroParcelas > 1 ? ` em ${p.numeroParcelas}x` : '';
      const troco = Number(p.valorTroco) ? ` (troco ${formatarMoeda(p.valorTroco)})` : '';
      li.textContent = `${nome(p.formaPagamento)}${parcelas}: ${formatarMoeda(p.valorPago)}${troco}`;
      return li;
    }));

    const trocas = $('#trocasDetalhe');
    trocas.replaceChildren();
    if (v.trocas.length) {
      const h3 = document.createElement('h3');
      h3.textContent = 'Trocas e devoluções';
      const ul = document.createElement('ul');
      ul.append(...v.trocas.map((t) => {
        const li = document.createElement('li');
        li.textContent = `${formatarData(t.dataTroca)} · ${nome(t.tipoOperacao)} (${nome(t.motivoTroca)}) · ` +
          `${t.quantidade} un. · diferença ${formatarMoeda(t.valorDiferenca)}${t.codigoVale ? ` · vale ${t.codigoVale}` : ''}`;
        return li;
      }));
      trocas.append(h3, ul);
    }

    const acoes = [botao('Imprimir cupom', () => window.open(`/api/vendas/${v.idVenda}/comprovante`, '_blank'))];
    if (v.statusVenda === 'FINALIZADA') {
      acoes.push(botao('Trocar / devolver', () => { location.href = `trocas.html?venda=${v.idVenda}`; }));
      acoes.push(botao('Cancelar venda', () => cancelar(v), 'pequeno perigo'));
    }
    $('#acoesDetalhe').replaceChildren(...acoes);
    $('#secaoDetalhe').scrollIntoView({ behavior: 'smooth' });
  } catch (erro) {
    mostrarMensagem(mensagem, erro.message);
  }
}

// RF12: cancelar com motivo e autorização do gerente
async function cancelar(venda) {
  const motivoCancelamento = prompt(`Motivo do cancelamento da venda nº ${venda.idVenda}:`);
  if (!motivoCancelamento) return;
  const corpo = { motivoCancelamento };
  if (!gerente) {
    corpo.autorizacao = await pedirAutorizacao('Cancelar uma venda exige a autorização do gerente.');
    if (!corpo.autorizacao) return;
  }
  try {
    const r = await enviar(`/api/vendas/${venda.idVenda}/cancelar`, 'POST', corpo);
    mostrarMensagem(mensagem, r.mensagem, 'sucesso');
    await carregar();
    await detalhar(venda.idVenda);
  } catch (erro) {
    mostrarMensagem(mensagem, erro.message);
  }
}

$('#formFiltros').addEventListener('input', () => debounce(() => carregar().catch((e) => mostrarMensagem(mensagem, e.message))));
$('#formFiltros').addEventListener('submit', (e) => e.preventDefault());
carregar().catch((erro) => mostrarMensagem(mensagem, erro.message));
