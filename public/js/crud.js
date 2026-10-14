import { api, enviar } from './api.js';
import {
  mostrarMensagem, celula, botao, grupo, linhaVazia, preencherFormulario, lerFormulario, debounce,
} from './ui.js';

/**
 * Tela de cadastro genérica (listar + cadastrar + editar + inativar), no mesmo padrão
 * do CRUD de Clientes (notas 05 e 07). Usada por fornecedores, categorias, promoções e usuários.
 *
 * config = {
 *   endpoint: '/api/fornecedores', idCampo: 'idFornecedor', nomeItem: 'fornecedor', tituloNovo?: 'Nova promoção',
 *   colunas: [{ campo, titulo, formatar?, classe? }],
 *   podeEditar: boolean, acaoInativar?: { rota, texto }, acoesExtras?: (item, recarregar) => [botões],
 *   antesDeEnviar?: (dados) => mensagemDeErro | undefined,
 *   aoMudarFormulario?: () => void   // depois de limpar ou preencher o form (ex.: mostrar/esconder campos)
 * }
 */
export function criarCrud(config) {
  const form = document.querySelector('#formCadastro');
  const lista = document.querySelector('#lista');
  const mensagem = document.querySelector('#mensagem');
  const campoBusca = document.querySelector('#busca');
  const tituloForm = document.querySelector('#tituloForm');
  const botaoCancelar = document.querySelector('#cancelarEdicao');
  let idEditando = null;

  function modoNovo() {
    idEditando = null;
    form.reset();
    tituloForm.textContent = config.tituloNovo || `Novo ${config.nomeItem}`;
    botaoCancelar.hidden = true;
    config.aoMudarFormulario?.();
  }

  async function editar(id) {
    const item = await api(`${config.endpoint}/${id}`);
    preencherFormulario(form, item);
    config.aoMudarFormulario?.();
    idEditando = id;
    tituloForm.textContent = `Editando ${config.nomeItem} #${id}`;
    botaoCancelar.hidden = false;
    form.scrollIntoView({ behavior: 'smooth' });
  }

  function criarLinha(item) {
    const tr = document.createElement('tr');
    for (const c of config.colunas) {
      const valor = c.formatar ? c.formatar(item[c.campo], item) : item[c.campo];
      celula(tr, valor, c.classe);
    }
    const acoes = [];
    if (config.podeEditar) {
      acoes.push(botao('Editar', () => editar(item[config.idCampo]).catch((e) => mostrarMensagem(mensagem, e.message))));
    }
    if (config.acaoInativar) {
      acoes.push(botao(config.acaoInativar.texto || 'Inativar', async () => {
        if (!confirm(`Confirma ${(config.acaoInativar.texto || 'inativar').toLowerCase()} este ${config.nomeItem}?`)) return;
        try {
          await api(`${config.endpoint}/${item[config.idCampo]}/${config.acaoInativar.rota}`, { method: 'PATCH' });
          mostrarMensagem(mensagem, 'Feito!', 'sucesso');
          await carregar();
        } catch (erro) {
          mostrarMensagem(mensagem, erro.message);
        }
      }, 'pequeno perigo'));
    }
    if (config.acoesExtras) acoes.push(...config.acoesExtras(item, carregar, mensagem));
    celula(tr, grupo(...acoes));
    return tr;
  }

  async function carregar() {
    const busca = campoBusca?.value || '';
    const itens = await api(`${config.endpoint}?busca=${encodeURIComponent(busca)}`);
    if (!itens.length) return linhaVazia(lista, config.colunas.length + 1);
    lista.replaceChildren(...itens.map(criarLinha));
  }

  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    const dados = lerFormulario(form);
    const erroLocal = config.antesDeEnviar?.(dados);
    if (erroLocal) return mostrarMensagem(mensagem, erroLocal);

    const botaoSalvar = form.querySelector('button[type="submit"]');
    botaoSalvar.disabled = true;
    try {
      if (idEditando) await enviar(`${config.endpoint}/${idEditando}`, 'PUT', dados);
      else await enviar(config.endpoint, 'POST', dados);
      mostrarMensagem(mensagem, idEditando ? 'Alterações salvas!' : 'Cadastrado com sucesso!', 'sucesso');
      modoNovo();
      await carregar();
    } catch (erro) {
      mostrarMensagem(mensagem, erro.message);
    } finally {
      botaoSalvar.disabled = false;
    }
  });

  botaoCancelar.addEventListener('click', modoNovo);
  campoBusca?.addEventListener('input', () => debounce(() => carregar().catch((e) => mostrarMensagem(mensagem, e.message))));

  if (!config.podeEditar) form.closest('section').hidden = true;
  modoNovo();
  carregar().catch((erro) => mostrarMensagem(mensagem, erro.message));
  return { carregar };
}
