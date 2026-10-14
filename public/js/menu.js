import { api } from './api.js';
import { nome } from './formatar.js';

const TODOS = ['ADMIN', 'GERENTE', 'VENDEDOR', 'CAIXA', 'ESTOQUISTA'];
const VENDEM = ['ADMIN', 'GERENTE', 'VENDEDOR', 'CAIXA'];
const ESTOQUE = ['ADMIN', 'GERENTE', 'ESTOQUISTA'];
const GESTAO = ['ADMIN', 'GERENTE'];

export const PAGINAS = [
  { href: 'index.html', titulo: 'Painel', perfis: TODOS, descricao: 'Resumo do dia' },
  { href: 'pdv.html', titulo: 'PDV', perfis: VENDEM, descricao: 'Frente de caixa' },
  { href: 'caixa.html', titulo: 'Caixa', perfis: VENDEM, descricao: 'Abrir, sangria, fechar' },
  { href: 'vendas.html', titulo: 'Vendas', perfis: VENDEM, descricao: 'Histórico e cancelamento' },
  { href: 'trocas.html', titulo: 'Trocas', perfis: VENDEM, descricao: 'Trocas, devoluções e vales' },
  { href: 'clientes.html', titulo: 'Clientes', perfis: VENDEM, descricao: 'Cadastro e fidelidade' },
  { href: 'produtos.html', titulo: 'Produtos', perfis: TODOS, descricao: 'Consulta e grade' },
  { href: 'estoque.html', titulo: 'Estoque', perfis: ESTOQUE, descricao: 'Entradas e alertas' },
  { href: 'fornecedores.html', titulo: 'Fornecedores', perfis: ESTOQUE, descricao: 'Cadastro de fornecedores' },
  { href: 'categorias.html', titulo: 'Categorias', perfis: ESTOQUE, descricao: 'Tipos de produto' },
  { href: 'promocoes.html', titulo: 'Promoções', perfis: GESTAO, descricao: 'Descontos automáticos' },
  { href: 'relatorios.html', titulo: 'Relatórios', perfis: GESTAO, descricao: 'PDF, CSV e tela' },
  { href: 'usuarios.html', titulo: 'Usuários', perfis: ['ADMIN'], descricao: 'Funcionários e acessos' },
];

/**
 * Monta o topo com o menu do perfil (RF07) e devolve o usuário logado.
 * Esconder links é só USABILIDADE: quem protege de verdade é o back-end (exigirPerfil).
 */
export async function iniciarPagina() {
  const usuario = await api('/api/auth/eu');
  const atual = location.pathname.split('/').pop() || 'index.html';
  const pagina = PAGINAS.find((p) => p.href === atual);
  if (pagina && !pagina.perfis.includes(usuario.perfil)) {
    location.href = 'index.html';
    return new Promise(() => {});
  }

  const topo = document.createElement('header');
  topo.className = 'topo';

  const titulo = document.createElement('h1');
  const link = document.createElement('a');
  link.href = 'index.html';
  link.textContent = 'Loja Modelo';
  titulo.append(link);

  const nav = document.createElement('nav');
  nav.setAttribute('aria-label', 'Menu principal');
  for (const p of PAGINAS.filter((p) => p.perfis.includes(usuario.perfil))) {
    const a = document.createElement('a');
    a.href = p.href;
    a.textContent = p.titulo;
    if (p.href === atual) a.setAttribute('aria-current', 'page');
    nav.append(a);
  }

  const quem = document.createElement('div');
  quem.className = 'usuario-logado';
  const texto = document.createElement('span');
  texto.textContent = `${usuario.nome} · ${nome(usuario.perfil)}`;
  const sair = document.createElement('button');
  sair.type = 'button';
  sair.textContent = 'Sair';
  sair.addEventListener('click', async () => {
    await api('/api/auth/logout', { method: 'POST' });
    location.href = 'login.html';
  });
  quem.append(texto, sair);

  topo.append(titulo, nav, quem);
  document.body.prepend(topo);
  return usuario;
}
