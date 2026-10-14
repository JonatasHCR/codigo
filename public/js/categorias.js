import { iniciarPagina } from './menu.js';
import { criarCrud } from './crud.js';
import { nome } from './formatar.js';

const usuario = await iniciarPagina();

criarCrud({
  endpoint: '/api/categorias',
  idCampo: 'idCategoria',
  nomeItem: 'categoria',
  tituloNovo: 'Nova categoria',
  podeEditar: ['ADMIN', 'GERENTE', 'ESTOQUISTA'].includes(usuario.perfil),
  acaoInativar: ['ADMIN', 'GERENTE'].includes(usuario.perfil) ? { rota: 'inativar', texto: 'Inativar' } : null,
  colunas: [
    { campo: 'nomeCategoria', titulo: 'Nome' },
    { campo: 'tipoCategoria', titulo: 'Tipo', formatar: nome },
    { campo: 'descricaoCategoria', titulo: 'Descrição' },
  ],
});
