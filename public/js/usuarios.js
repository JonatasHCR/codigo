import { api } from './api.js';
import { iniciarPagina } from './menu.js';
import { criarCrud } from './crud.js';
import { cpfValido } from './cpf.js';
import { nome } from './formatar.js';
import { botao, etiqueta, mostrarMensagem } from './ui.js';

await iniciarPagina();

// Ações que só existem para usuários: desbloquear (RF07) e reativar
function acaoPatch(texto, rota, item, recarregar, mensagem) {
  return botao(texto, async () => {
    try {
      await api(`/api/usuarios/${item.idUsuario}/${rota}`, { method: 'PATCH' });
      mostrarMensagem(mensagem, 'Feito!', 'sucesso');
      await recarregar();
    } catch (erro) {
      mostrarMensagem(mensagem, erro.message);
    }
  });
}

criarCrud({
  endpoint: '/api/usuarios',
  idCampo: 'idUsuario',
  nomeItem: 'usuário',
  podeEditar: true,
  colunas: [
    { campo: 'nomeUsuario', titulo: 'Nome' },
    { campo: 'loginUsuario', titulo: 'Login' },
    { campo: 'perfilAcesso', titulo: 'Perfil', formatar: nome },
    { campo: 'percentualComissao', titulo: 'Comissão', classe: 'numero', formatar: (v) => `${Number(v)}%` },
    { campo: 'statusUsuario', titulo: 'Situação',
      formatar: (ativo, u) => (!ativo ? etiqueta('Inativo')
        : u.tentativasFalhas >= 3 ? etiqueta('Bloqueado', 'erro') : etiqueta('Ativo', 'ok')) },
  ],
  acoesExtras: (u, recarregar, mensagem) => {
    const acoes = [];
    if (u.tentativasFalhas >= 3) acoes.push(acaoPatch('Desbloquear', 'desbloquear', u, recarregar, mensagem));
    acoes.push(u.statusUsuario
      ? acaoPatch('Inativar', 'inativar', u, recarregar, mensagem)
      : acaoPatch('Reativar', 'reativar', u, recarregar, mensagem));
    return acoes;
  },
  antesDeEnviar: (dados) => {
    if (dados.cpfUsuario && !cpfValido(dados.cpfUsuario)) return 'CPF inválido';
    return undefined;
  },
});
