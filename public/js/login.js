import { api } from './api.js';

const form = document.querySelector('#formLogin');
const mensagem = document.querySelector('#mensagem');

form.addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const botao = form.querySelector('button');
  botao.disabled = true;
  try {
    const dados = Object.fromEntries(new FormData(form));
    const usuario = await api('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify(dados),
    });
    location.href = usuario.perfil === 'CAIXA' ? 'pdv.html' : 'index.html';
  } catch (erro) {
    mensagem.textContent = erro.message;
    mensagem.className = 'mensagem erro';
    form.senha.value = '';
    form.senha.focus();
  } finally {
    botao.disabled = false;
  }
});
