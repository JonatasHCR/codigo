import { api, enviar } from './api.js';
import { iniciarPagina } from './menu.js';
import { formatarMoeda, formatarData, nome } from './formatar.js';
import { celula, etiqueta, linhaVazia, mostrarMensagem, lerFormulario } from './ui.js';

const mensagem = document.querySelector('#mensagem');
const usuario = await iniciarPagina();

function indicador(rotulo, valor, destaque = false) {
  const div = document.createElement('div');
  div.className = destaque ? 'indicador destaque' : 'indicador';
  const span = document.createElement('span');
  span.textContent = rotulo;
  const strong = document.createElement('strong');
  strong.textContent = valor;
  div.append(span, strong);
  return div;
}

async function carregar() {
  const caixa = await api('/api/caixa/atual');
  document.querySelector('#secaoAbrir').hidden = Boolean(caixa);
  document.querySelector('#secaoAberto').hidden = !caixa;
  if (!caixa) return;

  document.querySelector('#tituloCaixa').textContent =
    `Caixa nº ${caixa.idCaixa} · aberto em ${formatarData(caixa.dataHoraAbertura)}`;
  const { vendas, movimentos } = caixa;
  document.querySelector('#resumo').replaceChildren(
    indicador('Valor inicial', formatarMoeda(caixa.valorAbertura)),
    indicador('Vendas', String(vendas.quantidadeVendas)),
    indicador('Dinheiro (já sem troco)', formatarMoeda(vendas.dinheiro)),
    indicador('PIX', formatarMoeda(vendas.pix)),
    indicador('Débito', formatarMoeda(vendas.debito)),
    indicador('Crédito', formatarMoeda(vendas.credito)),
    indicador('Vale-troca', formatarMoeda(vendas.valeTroca)),
    indicador('Suprimentos − sangrias', formatarMoeda(movimentos.suprimentos - movimentos.sangrias)),
    indicador('Dinheiro esperado na gaveta', formatarMoeda(caixa.dinheiroEsperado), true),
  );

  const tbody = document.querySelector('#listaMovimentos');
  if (!caixa.listaMovimentos.length) linhaVazia(tbody, 4, 'Nenhuma sangria ou suprimento.');
  else {
    tbody.replaceChildren(...caixa.listaMovimentos.map((m) => {
      const tr = document.createElement('tr');
      celula(tr, formatarData(m.dataHoraMovimento).slice(-5));
      celula(tr, nome(m.tipoMovimento));
      celula(tr, m.descricaoMovimento);
      celula(tr, `${m.tipoMovimento === 'SANGRIA' ? '−' : '+'} ${formatarMoeda(m.valorMovimento)}`, 'numero');
      return tr;
    }));
  }
}

async function carregarHistorico() {
  const caixas = await api('/api/caixa');
  const tbody = document.querySelector('#listaCaixas');
  if (!caixas.length) return linhaVazia(tbody, 8);
  tbody.replaceChildren(...caixas.map((c) => {
    const tr = document.createElement('tr');
    celula(tr, c.idCaixa);
    celula(tr, c.nomeUsuario);
    celula(tr, formatarData(c.dataHoraAbertura));
    celula(tr, formatarData(c.dataHoraFechamento));
    celula(tr, c.valorSistema === null ? '' : formatarMoeda(c.valorSistema), 'numero');
    celula(tr, c.valorInformado === null ? '' : formatarMoeda(c.valorInformado), 'numero');
    const diferenca = Number(c.diferencaCaixa);
    celula(tr, c.diferencaCaixa === null ? '' : etiqueta(formatarMoeda(diferenca),
      diferenca === 0 ? 'ok' : diferenca < 0 ? 'erro' : 'alerta'), 'numero');
    celula(tr, etiqueta(nome(c.statusCaixa), c.statusCaixa === 'ABERTO' ? 'ok' : ''));
    return tr;
  }));
}

function aoEnviar(form, acao) {
  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const botao = form.querySelector('button[type="submit"]');
    botao.disabled = true;
    try {
      await acao(lerFormulario(form));
      form.reset();
      await carregar();
      if (!document.querySelector('#secaoHistorico').hidden) await carregarHistorico();
    } catch (erro) {
      mostrarMensagem(mensagem, erro.message);
    } finally {
      botao.disabled = false;
    }
  });
}

aoEnviar(document.querySelector('#formAbrir'), async (dados) => {
  await enviar('/api/caixa/abrir', 'POST', dados);
  mostrarMensagem(mensagem, 'Caixa aberto. Boas vendas!', 'sucesso');
});

aoEnviar(document.querySelector('#formMovimento'), async (dados) => {
  await enviar('/api/caixa/movimentos', 'POST', dados);
  mostrarMensagem(mensagem, `${nome(dados.tipoMovimento)} de ${formatarMoeda(dados.valorMovimento)} registrado(a).`, 'sucesso');
});

aoEnviar(document.querySelector('#formFechar'), async (dados) => {
  if (!confirm('Fechar o caixa? Depois disso não é possível registrar vendas nele.')) return;
  const r = await enviar('/api/caixa/fechar', 'POST', dados);
  const situacao = r.diferencaCaixa === 0 ? 'Caixa conferido, sem diferença.'
    : r.diferencaCaixa < 0 ? `FALTA de ${formatarMoeda(-r.diferencaCaixa)}.` : `SOBRA de ${formatarMoeda(r.diferencaCaixa)}.`;
  mostrarMensagem(mensagem, `Caixa fechado. Sistema: ${formatarMoeda(r.valorSistema)} · Contado: ${formatarMoeda(r.valorInformado)}. ${situacao}`,
    r.diferencaCaixa === 0 ? 'sucesso' : 'erro');
});

if (['ADMIN', 'GERENTE'].includes(usuario.perfil)) {
  document.querySelector('#secaoHistorico').hidden = false;
  carregarHistorico().catch((erro) => mostrarMensagem(mensagem, erro.message));
}
carregar().catch((erro) => mostrarMensagem(mensagem, erro.message));
