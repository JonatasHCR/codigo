import { api, enviar } from './api.js';
import { cpfValido } from './cpf.js';
import { iniciarPagina } from './menu.js';
import { mascararCpfDigitado, formatarTelefone } from './formatar.js';
import {
  mostrarMensagem as mostrar, celula, botao, grupo, linhaVazia, preencherFormulario, lerFormulario, debounce,
} from './ui.js';

const form = document.querySelector('#formCliente');
const lista = document.querySelector('#listaClientes');
const campoBusca = document.querySelector('#busca');
const mensagem = document.querySelector('#mensagem');
const tituloForm = document.querySelector('#tituloForm');
const cancelarEdicao = document.querySelector('#cancelarEdicao');
let idEditando = null;
let usuario;

const mostrarMensagem = (texto, tipo) => mostrar(mensagem, texto, tipo);

function criarLinha(cliente) {
  const tr = document.createElement('tr');
  for (const valor of [cliente.nomeCliente, cliente.cpfCliente, formatarTelefone(cliente.telefoneCliente)]) {
    const td = document.createElement('td');
    td.textContent = valor;   // textContent: proteção contra XSS (nota 07)
    tr.append(td);
  }
  const tdPontos = celula(tr, '…', 'numero');
  api(`/api/fidelidade/${cliente.idCliente}`)
    .then((conta) => { tdPontos.textContent = conta.saldoPontos; })
    .catch(() => { tdPontos.textContent = '—'; });

  const acoes = [botao('Editar', () => editar(cliente.idCliente))];
  if (['ADMIN', 'GERENTE'].includes(usuario.perfil)) {
    acoes.push(botao('Inativar', () => inativar(cliente), 'pequeno perigo'));
  }
  if (usuario.perfil === 'ADMIN') {
    acoes.push(botao('Anonimizar (LGPD)', () => anonimizar(cliente), 'pequeno secundario'));
  }
  celula(tr, grupo(...acoes));
  return tr;
}

async function carregarClientes(busca = '') {
  const clientes = await api(`/api/clientes?busca=${encodeURIComponent(busca)}`);
  if (!clientes.length) return linhaVazia(lista, 5);
  lista.replaceChildren(...clientes.map(criarLinha));
}

async function editar(id) {
  try {
    const cliente = await api(`/api/clientes/${id}`);
    preencherFormulario(form, cliente);
    form.cpfCliente.value = mascararCpfDigitado(cliente.cpfCliente);
    idEditando = id;
    tituloForm.textContent = `Editando: ${cliente.nomeCliente}`;
    cancelarEdicao.hidden = false;
    form.scrollIntoView({ behavior: 'smooth' });
  } catch (erro) {
    mostrarMensagem(erro.message);
  }
}

function modoNovo() {
  idEditando = null;
  form.reset();
  tituloForm.textContent = 'Novo cliente';
  cancelarEdicao.hidden = true;
}

async function inativar(cliente) {
  if (!confirm(`Inativar ${cliente.nomeCliente}? O histórico de compras é mantido.`)) return;
  try {
    await api(`/api/clientes/${cliente.idCliente}/inativar`, { method: 'PATCH' });
    mostrarMensagem('Cliente inativado.', 'sucesso');
    await carregarClientes(campoBusca.value);
  } catch (erro) {
    mostrarMensagem(erro.message);
  }
}

async function anonimizar(cliente) {
  if (!confirm(`ANONIMIZAR ${cliente.nomeCliente}? Os dados pessoais serão apagados de forma definitiva (pedido do titular, LGPD).`)) return;
  try {
    await api(`/api/clientes/${cliente.idCliente}/anonimizar`, { method: 'PATCH' });
    mostrarMensagem('Dados pessoais apagados. As vendas continuam no histórico.', 'sucesso');
    await carregarClientes(campoBusca.value);
  } catch (erro) {
    mostrarMensagem(erro.message);
  }
}

// Busca enquanto digita, com debounce (RF17 / nota 07)
campoBusca.addEventListener('input', () => {
  debounce(() => carregarClientes(campoBusca.value).catch((erro) => mostrarMensagem(erro.message)));
});

// Enviar o formulário
form.addEventListener('submit', async (evento) => {
  evento.preventDefault();

  const dados = lerFormulario(form);

  if (!cpfValido(dados.cpfCliente)) {
    mostrarMensagem('CPF inválido');
    form.cpfCliente.focus();
    return;
  }

  const botaoSalvar = form.querySelector('button[type="submit"]');
  botaoSalvar.disabled = true;
  try {
    if (idEditando) await enviar(`/api/clientes/${idEditando}`, 'PUT', dados);
    else await enviar('/api/clientes', 'POST', dados);
    mostrarMensagem(idEditando ? 'Cliente atualizado!' : 'Cliente cadastrado com sucesso!', 'sucesso');
    modoNovo();
    await carregarClientes();
  } catch (erro) {
    mostrarMensagem(erro.message);
  } finally {
    botaoSalvar.disabled = false;
  }
});

// Máscara de CPF enquanto digita
form.cpfCliente.addEventListener('input', (evento) => {
  evento.target.value = mascararCpfDigitado(evento.target.value);
});

// Preencher o endereço pelo CEP (RF01) — API pública ViaCEP
form.cepCliente.addEventListener('blur', async () => {
  const cep = form.cepCliente.value.replace(/\D/g, '');
  if (cep.length !== 8) return;

  try {
    const resposta = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    const endereco = await resposta.json();
    if (endereco.erro) return mostrarMensagem('CEP não encontrado');

    form.enderecoCliente.value = endereco.logradouro;
    form.bairroCliente.value = endereco.bairro;
    form.cidadeCliente.value = endereco.localidade;
    form.ufCliente.value = endereco.uf;
    form.numeroEndereco.focus();
  } catch {
    mostrarMensagem('Não foi possível consultar o CEP. Preencha manualmente.');
  }
});

cancelarEdicao.addEventListener('click', modoNovo);

iniciarPagina()
  .then((u) => { usuario = u; return carregarClientes(); })
  .catch((erro) => mostrarMensagem(erro.message));
