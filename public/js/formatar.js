// Formatação no padrão brasileiro (RNF03) — ver nota 07
const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export function formatarMoeda(valor) {
  return moeda.format(Number(valor || 0));          // '89.9' → 'R$ 89,90'
}

export function formatarData(texto) {               // '2026-09-30 14:05:00'
  if (!texto) return '';
  const [data, hora] = texto.split(' ');
  const [ano, mes, dia] = data.split('-');
  const dataBr = `${dia}/${mes}/${ano}`;
  return hora ? `${dataBr} ${hora.slice(0, 5)}` : dataBr;   // '30/09/2026 14:05'
}

// Data de hoje no formato do <input type="date"> (AAAA-MM-DD), no fuso do navegador
export function hojeIso() {
  const agora = new Date();
  const ajustada = new Date(agora.getTime() - agora.getTimezoneOffset() * 60000);
  return ajustada.toISOString().slice(0, 10);
}

export function mascararCpfDigitado(valor) {
  const numeros = valor.replace(/\D/g, '').slice(0, 11);
  return numeros
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

export function formatarCnpj(cnpj) {
  return String(cnpj || '').replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

export function formatarTelefone(tel) {
  const n = String(tel || '').replace(/\D/g, '');
  if (n.length === 11) return n.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
  if (n.length === 10) return n.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
  return tel || '';
}

export const NOMES = {
  DINHEIRO: 'Dinheiro', PIX: 'PIX', DEBITO: 'Débito', CREDITO: 'Crédito', VALE_TROCA: 'Vale-troca',
  ROUPA: 'Roupa', CALCADO: 'Calçado', ACESSORIO: 'Acessório',
  ADMIN: 'Administrador', GERENTE: 'Gerente', VENDEDOR: 'Vendedor', CAIXA: 'Caixa', ESTOQUISTA: 'Estoquista',
  FINALIZADA: 'Finalizada', CANCELADA: 'Cancelada', ABERTA: 'Aberta',
  TROCA: 'Troca', DEVOLUCAO: 'Devolução', TAMANHO: 'Tamanho', COR: 'Cor', DEFEITO: 'Defeito',
  ARREPENDIMENTO: 'Arrependimento', OUTRO: 'Outro', PERCENTUAL: 'Percentual', VALOR_FIXO: 'Valor fixo',
  SANGRIA: 'Sangria', SUPRIMENTO: 'Suprimento', ABERTO: 'Aberto', FECHADO: 'Fechado',
};
export const nome = (codigo) => NOMES[codigo] || codigo || '';
