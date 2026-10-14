import { api } from './api.js';
import { iniciarPagina } from './menu.js';
import { formatarMoeda, formatarData, hojeIso, nome } from './formatar.js';
import { celula, linhaVazia, mostrarMensagem, preencherSelect } from './ui.js';

const $ = (s) => document.querySelector(s);
const form = $('#formRelatorio');
const mensagem = $('#mensagem');

await iniciarPagina();

const tipos = await api('/api/relatorios');
preencherSelect($('#tipo'), tipos, 'tipo', 'titulo');

// Período padrão: do dia 1º do mês até hoje
const hoje = hojeIso();
form.inicio.value = `${hoje.slice(0, 8)}01`;
form.fim.value = hoje;

function semPeriodo() {
  return tipos.find((t) => t.tipo === form.tipo.value)?.semPeriodo;
}
function ajustarPeriodo() {
  document.querySelectorAll('.periodo').forEach((el) => { el.hidden = semPeriodo(); });
  form.inicio.required = !semPeriodo();
  form.fim.required = !semPeriodo();
}
form.tipo.addEventListener('change', ajustarPeriodo);
ajustarPeriodo();

function url(formato) {
  const params = new URLSearchParams({ formato });
  if (!semPeriodo()) { params.set('inicio', form.inicio.value); params.set('fim', form.fim.value); }
  return `/api/relatorios/${form.tipo.value}?${params}`;
}

const formatar = (valor, tipo) => {
  if (valor === null || valor === undefined) return '';
  if (tipo === 'moeda') return formatarMoeda(valor);
  if (tipo === 'data') return formatarData(valor);
  if (tipo === 'numero') return Number(valor).toLocaleString('pt-BR');
  return nome(String(valor));
};

form.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  try {
    const r = await api(url('json'));
    $('#secaoResultado').hidden = false;
    $('#tituloResultado').textContent = r.titulo;
    $('#periodoResultado').textContent = `Período: ${r.periodo}`;

    const trCabecalho = document.createElement('tr');
    for (const c of r.colunas) {
      const th = document.createElement('th');
      th.textContent = c.titulo;
      if (['moeda', 'numero'].includes(c.tipo)) th.className = 'numero';
      trCabecalho.append(th);
    }
    $('#cabecalho').replaceChildren(trCabecalho);

    if (!r.linhas.length) linhaVazia($('#linhas'), r.colunas.length, 'Nenhum registro no período.');
    else {
      $('#linhas').replaceChildren(...r.linhas.map((linha) => {
        const tr = document.createElement('tr');
        for (const c of r.colunas) {
          celula(tr, formatar(linha[c.campo], c.tipo), ['moeda', 'numero'].includes(c.tipo) ? 'numero' : '');
        }
        return tr;
      }));
    }

    const trTotal = document.createElement('tr');
    r.colunas.forEach((c, i) => {
      const valor = c.campo in r.totais ? formatar(r.totais[c.campo], c.tipo) : i === 0 ? 'Total' : '';
      const th = document.createElement('th');
      th.textContent = valor;
      if (['moeda', 'numero'].includes(c.tipo)) th.className = 'numero';
      trTotal.append(th);
    });
    $('#totais').replaceChildren(trTotal);
    mostrarMensagem(mensagem, '');
  } catch (erro) {
    mostrarMensagem(mensagem, erro.message);
  }
});

// PDF e CSV: navegar para a URL faz o navegador baixar (Content-Disposition) — nota 10
document.querySelectorAll('[data-formato]').forEach((botao) => {
  botao.addEventListener('click', () => {
    if (!form.reportValidity()) return;
    location.href = url(botao.dataset.formato);
  });
});
