import { iniciarPagina } from './menu.js';
import { criarCrud } from './crud.js';
import { cnpjValido } from './cpf.js';
import { formatarCnpj, formatarTelefone } from './formatar.js';

const usuario = await iniciarPagina();

criarCrud({
  endpoint: '/api/fornecedores',
  idCampo: 'idFornecedor',
  nomeItem: 'fornecedor',
  podeEditar: ['ADMIN', 'GERENTE', 'ESTOQUISTA'].includes(usuario.perfil),
  acaoInativar: ['ADMIN', 'GERENTE'].includes(usuario.perfil) ? { rota: 'inativar', texto: 'Inativar' } : null,
  colunas: [
    { campo: 'razaoSocial', titulo: 'Razão social' },
    { campo: 'cnpjFornecedor', titulo: 'CNPJ', formatar: formatarCnpj },
    { campo: 'nomeContato', titulo: 'Contato' },
    { campo: 'telefoneFornecedor', titulo: 'Telefone', formatar: formatarTelefone },
    { campo: 'cidadeFornecedor', titulo: 'Cidade', formatar: (cidade, f) => [cidade, f.ufFornecedor].filter(Boolean).join('/') },
  ],
  antesDeEnviar: (dados) => (cnpjValido(dados.cnpjFornecedor) ? undefined : 'CNPJ inválido'),
});
