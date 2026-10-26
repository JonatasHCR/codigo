// Uma única função para todas as telas conversarem com o servidor (ver nota 07)
export async function api(url, opcoes = {}) {
  // Com FormData (upload de arquivo) o próprio navegador monta o Content-Type multipart
  const json = !(opcoes.body instanceof FormData);
  const resposta = await fetch(url, {
    ...opcoes,
    headers: { ...(json && { 'Content-Type': 'application/json' }), ...opcoes.headers },
  });

  if (resposta.status === 401 && !location.pathname.endsWith('login.html')) {
    location.href = 'login.html';
    return new Promise(() => {});   // a página vai mudar; nada mais deve rodar
  }
  if (resposta.status === 204) return null;

  const dados = await resposta.json().catch(() => ({}));
  if (!resposta.ok) {
    throw new Error(dados.erro || 'Erro na comunicação com o servidor');
  }
  return dados;
}

// Atalhos para os métodos mais usados
export const enviar = (url, metodo, corpo) => api(url, { method: metodo, body: JSON.stringify(corpo ?? {}) });

// Envia um arquivo (ex.: foto do produto) no campo indicado
export function enviarArquivo(url, campo, arquivo) {
  const corpo = new FormData();
  corpo.append(campo, arquivo);
  return api(url, { method: 'POST', body: corpo });
}
