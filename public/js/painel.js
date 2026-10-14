import { api } from './api.js';
import { iniciarPagina, PAGINAS } from './menu.js';
import { formatarMoeda, formatarData, hojeIso } from './formatar.js';
import { celula, linhaVazia, mostrarMensagem } from './ui.js';

const mensagem = document.querySelector('#mensagem');

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

async function iniciar() {
  const usuario = await iniciarPagina();
  document.querySelector('#boasVindas').textContent = `Olá, ${usuario.nome.split(' ')[0]}!`;

  // Atalhos: só as telas que o perfil pode usar
  const atalhos = PAGINAS.filter((p) => p.href !== 'index.html' && p.perfis.includes(usuario.perfil))
    .map((p) => {
      const a = document.createElement('a');
      a.href = p.href;
      a.textContent = p.titulo;
      const small = document.createElement('small');
      small.textContent = p.descricao;
      a.append(small);
      return a;
    });
  document.querySelector('#atalhos').replaceChildren(...atalhos);

  const indicadores = [];

  // Caixa do usuário (quem vende)
  if (['ADMIN', 'GERENTE', 'VENDEDOR', 'CAIXA'].includes(usuario.perfil)) {
    const caixa = await api('/api/caixa/atual');
    indicadores.push(indicador('Meu caixa', caixa ? `Aberto · ${caixa.vendas.quantidadeVendas} vendas` : 'Fechado'));
  }

  // Vendas de hoje (gerência)
  if (['ADMIN', 'GERENTE'].includes(usuario.perfil)) {
    const hoje = hojeIso();
    const relatorio = await api(`/api/relatorios/vendas-periodo?inicio=${hoje}&fim=${hoje}`);
    indicadores.push(indicador('Vendas hoje', String(relatorio.totais.quantidadeVendas || 0)));
    indicadores.push(indicador('Faturamento hoje', formatarMoeda(relatorio.totais.valorTotal), true));
  }

  // RF16: alerta de estoque mínimo na tela inicial do gerente e do estoquista
  if (['ADMIN', 'GERENTE', 'ESTOQUISTA'].includes(usuario.perfil)) {
    const alertas = await api('/api/estoque/alertas');
    indicadores.push(indicador('Itens para repor', String(alertas.length)));
    const secao = document.querySelector('#secaoAlertas');
    const tbody = document.querySelector('#listaAlertas');
    secao.hidden = false;
    if (!alertas.length) {
      linhaVazia(tbody, 6, 'Nenhum produto abaixo do estoque mínimo. 👍');
    } else {
      tbody.replaceChildren(...alertas.map((a) => {
        const tr = document.createElement('tr');
        celula(tr, a.nomeProduto);
        celula(tr, a.tamanho);
        celula(tr, a.cor);
        celula(tr, a.quantidadeEstoque, 'numero');
        celula(tr, a.estoqueMinimo, 'numero');
        celula(tr, formatarData(a.dataAlerta));
        return tr;
      }));
    }
  }

  document.querySelector('#indicadores').replaceChildren(...indicadores);
}

iniciar().catch((erro) => mostrarMensagem(mensagem, erro.message));
