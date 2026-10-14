import { api } from './api.js';
import { iniciarPagina } from './menu.js';
import { criarCrud } from './crud.js';
import { formatarMoeda, formatarData } from './formatar.js';
import { preencherSelect, etiqueta } from './ui.js';

await iniciarPagina();

const [categorias, produtos] = await Promise.all([api('/api/categorias'), api('/api/produtos')]);
preencherSelect(document.querySelector('#selCategoria'), categorias, 'idCategoria', 'nomeCategoria', '— nenhuma —');
preencherSelect(document.querySelector('#selProduto'), produtos, 'idProduto', 'nomeProduto', '— nenhum —');

criarCrud({
  endpoint: '/api/promocoes',
  idCampo: 'idPromocao',
  nomeItem: 'promoção',
  podeEditar: true,
  acaoInativar: { rota: 'desativar', texto: 'Desativar' },
  colunas: [
    { campo: 'nomePromocao', titulo: 'Nome' },
    { campo: 'valorDesconto', titulo: 'Desconto',
      formatar: (v, p) => (p.tipoDesconto === 'PERCENTUAL' ? `${Number(v)}%` : `${formatarMoeda(v)} / un.`) },
    { campo: 'nomeProduto', titulo: 'Aplica em', formatar: (prod, p) => prod || `Categoria: ${p.nomeCategoria}` },
    { campo: 'dataInicio', titulo: 'Período', formatar: (ini, p) => `${formatarData(ini)} a ${formatarData(p.dataFim)}` },
    { campo: 'ativaHoje', titulo: 'Situação',
      formatar: (ativa, p) => (!p.statusPromocao ? etiqueta('Desativada') : ativa ? etiqueta('Ativa hoje', 'ok') : etiqueta('Fora do período', 'alerta')) },
  ],
});
