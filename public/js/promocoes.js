import { api } from './api.js';
import { iniciarPagina } from './menu.js';
import { criarCrud } from './crud.js';
import { formatarMoeda, formatarData } from './formatar.js';
import { preencherSelect, etiqueta } from './ui.js';

await iniciarPagina();

const $ = (seletor) => document.querySelector(seletor);

const [categorias, produtos] = await Promise.all([api('/api/categorias'), api('/api/produtos')]);
preencherSelect($('#selCategoria'), categorias, 'idCategoria', 'nomeCategoria', '— escolha —');
preencherSelect($('#selProduto'), produtos, 'idProduto', 'nomeProduto', '— escolha —');

// Mostra só o campo que combina com "Vale para"
function mostrarCampos() {
  const abrangencia = $('#selAbrangencia').value;
  $('#campoCategoria').hidden = abrangencia !== 'CATEGORIA';
  $('#campoProduto').hidden = abrangencia !== 'PRODUTO';
}
$('#selAbrangencia').addEventListener('change', mostrarCampos);

const ONDE = {
  TODOS: () => 'Todos os produtos',
  CATEGORIA: (p) => `Categoria: ${p.nomeCategoria}`,
  PRODUTO: (p) => `Produto: ${p.nomeProduto}`,
};

criarCrud({
  endpoint: '/api/promocoes',
  idCampo: 'idPromocao',
  nomeItem: 'promoção',
  tituloNovo: 'Nova promoção',
  podeEditar: true,
  acaoInativar: { rota: 'desativar', texto: 'Desativar' },
  aoMudarFormulario: mostrarCampos,
  antesDeEnviar: (dados) => {
    if (dados.abrangencia === 'CATEGORIA' && !dados.idCategoria) return 'Escolha a categoria';
    if (dados.abrangencia === 'PRODUTO' && !dados.idProduto) return 'Escolha o produto';
    return undefined;
  },
  colunas: [
    { campo: 'nomePromocao', titulo: 'Nome' },
    { campo: 'valorDesconto', titulo: 'Desconto',
      formatar: (v, p) => (p.tipoDesconto === 'PERCENTUAL' ? `${Number(v)}%` : `${formatarMoeda(v)} / un.`) },
    { campo: 'abrangencia', titulo: 'Aplica em', formatar: (a, p) => (ONDE[a] || ONDE.PRODUTO)(p) },
    { campo: 'dataInicio', titulo: 'Período', formatar: (ini, p) => `${formatarData(ini)} a ${formatarData(p.dataFim)}` },
    { campo: 'ativaHoje', titulo: 'Situação',
      formatar: (ativa, p) => (!p.statusPromocao ? etiqueta('Desativada') : ativa ? etiqueta('Ativa hoje', 'ok') : etiqueta('Fora do período', 'alerta')) },
  ],
});
