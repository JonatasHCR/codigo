// Pequenos auxiliares de interface usados por várias telas.
// Todo dado vindo do usuário ou do banco entra com textContent (proteção contra XSS — nota 07).

export function mostrarMensagem(elemento, texto, tipo = 'erro') {
  elemento.textContent = texto;
  elemento.className = `mensagem ${tipo}`;
}

export function celula(tr, valor, classe = '') {
  const td = document.createElement('td');
  if (valor instanceof Node) td.append(valor);
  else td.textContent = valor ?? '';
  if (classe) td.className = classe;
  tr.append(td);
  return td;
}

export function botao(texto, aoClicar, classe = 'pequeno secundario') {
  const b = document.createElement('button');
  b.type = 'button';
  b.textContent = texto;
  b.className = classe;
  b.addEventListener('click', aoClicar);
  return b;
}

export function grupo(...elementos) {
  const div = document.createElement('div');
  div.className = 'botoes';
  div.append(...elementos);
  return div;
}

export function etiqueta(texto, tipo = '') {
  const span = document.createElement('span');
  span.className = `etiqueta ${tipo}`;
  span.textContent = texto;
  return span;
}

export function linhaVazia(tbody, colunas, texto = 'Nenhum registro encontrado.') {
  const tr = document.createElement('tr');
  const td = celula(tr, texto, 'vazio');
  td.colSpan = colunas;
  tbody.replaceChildren(tr);
}

export function preencherSelect(select, itens, campoValor, campoTexto, textoVazio = null) {
  const opcoes = itens.map((item) => {
    const opcao = document.createElement('option');
    opcao.value = item[campoValor];
    opcao.textContent = typeof campoTexto === 'function' ? campoTexto(item) : item[campoTexto];
    return opcao;
  });
  if (textoVazio !== null) {
    const vazio = document.createElement('option');
    vazio.value = '';
    vazio.textContent = textoVazio;
    opcoes.unshift(vazio);
  }
  select.replaceChildren(...opcoes);
}

// Preenche um <form> pelos atributos name (iguais às colunas do banco)
export function preencherFormulario(form, dados) {
  for (const campo of form.elements) {
    if (!campo.name || !(campo.name in dados)) continue;
    const valor = dados[campo.name];
    if (campo.type === 'checkbox') campo.checked = Boolean(valor);
    else if (campo.type === 'date' && valor) campo.value = String(valor).slice(0, 10);
    else campo.value = valor ?? '';
  }
}

// Lê o <form> como objeto; checkboxes viram true/false
export function lerFormulario(form) {
  const dados = Object.fromEntries(new FormData(form));
  for (const campo of form.elements) {
    if (campo.type === 'checkbox' && campo.name) dados[campo.name] = campo.checked;
  }
  return dados;
}

let temporizador;
export function debounce(funcao, espera = 300) {
  clearTimeout(temporizador);
  temporizador = setTimeout(funcao, espera);
}
