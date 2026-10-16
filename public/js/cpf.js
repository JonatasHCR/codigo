// Mesma função do back-end (src/Utils/Cpf.php). A duplicação é proposital:
// o front valida para a COMODIDADE do usuário; o back valida para a SEGURANÇA (RNF09).
export function cpfValido(cpf) {
  cpf = String(cpf).replace(/\D/g, '');
  if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;

  for (let t = 9; t < 11; t++) {
    let soma = 0;
    for (let i = 0; i < t; i++) soma += cpf[i] * (t + 1 - i);
    const digito = ((soma * 10) % 11) % 10;
    if (digito !== Number(cpf[t])) return false;
  }
  return true;
}

export function cnpjValido(cnpj) {
  cnpj = String(cnpj).replace(/\D/g, '');
  if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;

  const pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  for (let t = 12; t < 14; t++) {
    let soma = 0;
    for (let i = 0; i < t; i++) soma += cnpj[i] * pesos[i + 13 - t];
    const resto = soma % 11;
    const digito = resto < 2 ? 0 : 11 - resto;
    if (digito !== Number(cnpj[t])) return false;
  }
  return true;
}
