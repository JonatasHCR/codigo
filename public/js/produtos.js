import { api, enviar } from './api.js';
import { iniciarPagina } from './menu.js';
import { formatarMoeda } from './formatar.js';
import {
  celula, botao, grupo, etiqueta, linhaVazia, mostrarMensagem, preencherSelect, preencherFormulario,
  lerFormulario, debounce,
} from './ui.js';

const formFiltros = document.querySelector('#formFiltros');
const lista = document.querySelector('#lista');
const mensagem = document.querySelector('#mensagem');
const formProduto = document.querySelector('#formProduto');
const formVariacao = document.querySelector('#formVariacao');
const listaGrade = document.querySelector('#listaGrade');

const usuario = await iniciarPagina();
const editor = ['ADMIN', 'GERENTE', 'ESTOQUISTA'].includes(usuario.perfil);
let idProdutoEditando = null;
let idVariacaoEditando = null;

const categorias = await api('/api/categorias');
preencherSelect(document.querySelector('#filtroCategoria'), categorias, 'idCategoria', 'nomeCategoria', 'Todas');

// ---------- RF17: consulta com filtros ----------
async function consultar() {
  const filtros = lerFormulario(formFiltros);
  const params = new URLSearchParams(
    Object.entries(filtros).filter(([, valor]) => valor !== '' && valor !== false)
  );
  const produtos = await api(`/api/produtos?${params}`);
  const linhas = [];
  for (const p of produtos) {
    if (!p.variacoes.length) {
      const tr = document.createElement('tr');
      celula(tr, p.nomeProduto);
      celula(tr, p.nomeCategoria);
      const aviso = celula(tr, etiqueta('sem grade cadastrada', 'alerta'));
      aviso.colSpan = 6;
      if (editor) celula(tr, grupo(botao('Editar', () => editarProduto(p.idProduto))));
      linhas.push(tr);
    }
    for (const v of p.variacoes) {
      const tr = document.createElement('tr');
      celula(tr, p.nomeProduto);
      celula(tr, p.nomeCategoria);
      celula(tr, v.tamanho);
      celula(tr, v.cor);
      celula(tr, v.codigoBarras);
      celula(tr, formatarMoeda(p.precoVenda), 'numero');
      celula(tr, etiqueta(String(v.quantidadeEstoque),
        v.quantidadeEstoque === 0 ? 'erro' : v.quantidadeEstoque <= v.estoqueMinimo ? 'alerta' : 'ok'), 'numero');
      celula(tr, v.localizacao);
      if (editor) celula(tr, grupo(botao('Editar', () => editarProduto(p.idProduto))));
      linhas.push(tr);
    }
  }
  if (!linhas.length) return linhaVazia(lista, editor ? 9 : 8, 'Nenhum produto encontrado com esses filtros.');
  lista.replaceChildren(...linhas);
}

formFiltros.addEventListener('input', () => debounce(() => consultar().catch((e) => mostrarMensagem(mensagem, e.message))));
formFiltros.addEventListener('submit', (e) => e.preventDefault());

// ---------- RF04: cadastro de produto ----------
async function editarProduto(id) {
  try {
    const produto = await api(`/api/produtos/${id}`);
    preencherFormulario(formProduto, produto);
    idProdutoEditando = id;
    document.querySelector('#tituloProduto').textContent = `Editando: ${produto.nomeProduto}`;
    document.querySelector('#novoProduto').hidden = false;
    mostrarGrade(produto);
    formProduto.scrollIntoView({ behavior: 'smooth' });
  } catch (erro) {
    mostrarMensagem(mensagem, erro.message);
  }
}

function novoProduto() {
  idProdutoEditando = null;
  formProduto.reset();
  document.querySelector('#tituloProduto').textContent = 'Novo produto';
  document.querySelector('#novoProduto').hidden = true;
  document.querySelector('#secaoGrade').hidden = true;
}

formProduto.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const dados = lerFormulario(formProduto);
  const aviso = document.querySelector('#mensagemProduto');
  if (Number(dados.precoVenda) < Number(dados.precoCusto)) {
    return mostrarMensagem(aviso, 'O preço de venda não pode ser menor que o preço de custo');
  }
  try {
    if (idProdutoEditando) {
      await enviar(`/api/produtos/${idProdutoEditando}`, 'PUT', dados);
      mostrarMensagem(aviso, 'Produto atualizado!', 'sucesso');
    } else {
      const { idProduto } = await enviar('/api/produtos', 'POST', dados);
      mostrarMensagem(aviso, 'Produto cadastrado! Agora cadastre a grade (tamanhos e cores).', 'sucesso');
      await editarProduto(idProduto);
    }
    await consultar();
  } catch (erro) {
    mostrarMensagem(aviso, erro.message);
  }
});

// ---------- RF05: grade de tamanhos e cores ----------
function mostrarGrade(produto) {
  document.querySelector('#secaoGrade').hidden = false;
  document.querySelector('#tituloGrade').textContent = `Grade de ${produto.nomeProduto}`;
  novaVariacao();
  if (!produto.variacoes.length) return linhaVazia(listaGrade, 7, 'Nenhuma variação cadastrada ainda.');
  listaGrade.replaceChildren(...produto.variacoes.map((v) => {
    const tr = document.createElement('tr');
    celula(tr, v.tamanho);
    celula(tr, v.cor);
    celula(tr, v.codigoBarras);
    celula(tr, v.quantidadeEstoque, 'numero');
    celula(tr, v.estoqueMinimo, 'numero');
    celula(tr, v.localizacao);
    celula(tr, botao('Editar', () => {
      preencherFormulario(formVariacao, v);
      idVariacaoEditando = v.idVariacao;
      document.querySelector('#tituloVariacao').textContent = `Editando ${v.tamanho} / ${v.cor}`;
    }));
    return tr;
  }));
}

function novaVariacao() {
  idVariacaoEditando = null;
  formVariacao.reset();
  document.querySelector('#tituloVariacao').textContent = 'Nova variação';
}

formVariacao.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const aviso = document.querySelector('#mensagemGrade');
  try {
    const dados = lerFormulario(formVariacao);
    if (idVariacaoEditando) await enviar(`/api/produtos/variacoes/${idVariacaoEditando}`, 'PUT', dados);
    else await enviar(`/api/produtos/${idProdutoEditando}/variacoes`, 'POST', dados);
    mostrarMensagem(aviso, 'Variação salva!', 'sucesso');
    mostrarGrade(await api(`/api/produtos/${idProdutoEditando}`));
    await consultar();
  } catch (erro) {
    mostrarMensagem(aviso, erro.message);
  }
});

// ---------- Início ----------
if (editor) {
  document.querySelectorAll('.editor').forEach((el) => { el.hidden = false; });
  document.querySelector('#secaoGrade').hidden = true;
  const fornecedores = await api('/api/fornecedores');
  preencherSelect(document.querySelector('#selCategoria'), categorias, 'idCategoria', 'nomeCategoria', 'Escolha...');
  preencherSelect(document.querySelector('#selFornecedor'), fornecedores, 'idFornecedor', 'razaoSocial', 'Escolha...');
  document.querySelector('#novoProduto').addEventListener('click', novoProduto);
} else {
  document.querySelectorAll('th.editor').forEach((th) => th.remove());
}
consultar().catch((erro) => mostrarMensagem(mensagem, erro.message));
