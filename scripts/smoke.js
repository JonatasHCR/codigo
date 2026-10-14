/**
 * Teste de ponta a ponta da API (todos os RFs principais).
 * Uso:  npm run smoke            (servidor rodando em http://localhost:3000)
 *       BASE=http://outro:3000 npm run smoke
 * Pode ser executado várias vezes: os cadastros usam códigos aleatórios.
 */
const BASE = process.env.BASE || 'http://localhost:3000';
let falhas = 0;
let total = 0;

// ---------- Pequeno "navegador": guarda o cookie de sessão ----------
class Sessao {
  constructor(nome) { this.nome = nome; this.cookie = ''; }

  async req(metodo, caminho, corpo) {
    const resposta = await fetch(BASE + caminho, {
      method: metodo,
      headers: { 'Content-Type': 'application/json', Cookie: this.cookie },
      body: corpo === undefined ? undefined : JSON.stringify(corpo),
    });
    // Como o navegador: guarda o ÚLTIMO cookie loja_sid recebido
    const cookies = resposta.headers.getSetCookie?.() ?? [resposta.headers.get('set-cookie')].filter(Boolean);
    for (const c of cookies) if (c.startsWith('loja_sid=')) this.cookie = c.split(';')[0];
    const tipo = resposta.headers.get('content-type') || '';
    let dados;
    if (tipo.includes('application/json')) dados = await resposta.json();
    else dados = Buffer.from(await resposta.arrayBuffer());
    return { status: resposta.status, dados, tipo };
  }

  get(c) { return this.req('GET', c); }
  post(c, b) { return this.req('POST', c, b); }
  put(c, b) { return this.req('PUT', c, b); }
  patch(c, b) { return this.req('PATCH', c, b); }

  async login(login, senha) {
    const r = await this.post('/api/auth/login', { login, senha });
    if (r.status !== 200) throw new Error(`Login de ${login} falhou: ${JSON.stringify(r.dados)}`);
    return r;
  }
}

function verificar(descricao, condicao, detalhe = '') {
  total++;
  if (condicao) console.log(`  ✅ ${descricao}`);
  else { falhas++; console.log(`  ❌ ${descricao} ${detalhe}`); }
}
const titulo = (t) => console.log(`\n▶ ${t}`);
const igual = (a, b) => Math.abs(Number(a) - Number(b)) < 0.005;
const aleatorio = (n) => Array.from({ length: n }, () => Math.floor(Math.random() * 10)).join('');

function gerarCpf() {
  const d = Array.from({ length: 9 }, () => Math.floor(Math.random() * 10));
  for (let t = 9; t < 11; t++) {
    let soma = 0;
    for (let i = 0; i < t; i++) soma += d[i] * (t + 1 - i);
    d.push(((soma * 10) % 11) % 10);
  }
  return d.join('');
}
function gerarCnpj() {
  const d = [...Array.from({ length: 8 }, () => Math.floor(Math.random() * 10)), 0, 0, 0, 1];
  const pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  for (let t = 12; t < 14; t++) {
    let soma = 0;
    for (let i = 0; i < t; i++) soma += d[i] * pesos[i + 13 - t];
    const resto = soma % 11;
    d.push(resto < 2 ? 0 : 11 - resto);
  }
  return d.join('');
}
const hoje = () => {
  const a = new Date();
  return new Date(a.getTime() - a.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
};

async function estoqueDe(sessao, idProduto, idVariacao) {
  const r = await sessao.get(`/api/produtos/${idProduto}`);
  return r.dados.variacoes.find((v) => v.idVariacao === idVariacao).quantidadeEstoque;
}

async function main() {
  const admin = new Sessao('admin');
  const caixa = new Sessao('caixa');
  const vendedor = new Sessao('vendedor');
  const anonimo = new Sessao('anonimo');

  // ------------------------------------------------------------------
  titulo('RF07 / RNF05 — Login, sessão e perfis');
  verificar('API sem login responde 401', (await anonimo.get('/api/clientes')).status === 401);
  await admin.login('admin', 'admin123');
  verificar('Login do admin', (await admin.get('/api/auth/eu')).dados.perfil === 'ADMIN');
  await vendedor.login('vendedor', 'vendedor123');
  verificar('Vendedor não acessa relatórios (403)', (await vendedor.get('/api/relatorios')).status === 403);
  verificar('Vendedor não acessa usuários (403)', (await vendedor.get('/api/usuarios')).status === 403);

  const loginTeste = `teste${aleatorio(5)}`;
  const novoUsuario = await admin.post('/api/usuarios', {
    nomeUsuario: 'Usuário de Teste', loginUsuario: loginTeste, perfilAcesso: 'VENDEDOR', senha: 'senha123', percentualComissao: 2,
  });
  verificar('RF06 cadastra funcionário', novoUsuario.status === 201, JSON.stringify(novoUsuario.dados));
  const tentativa = new Sessao('tentativa');
  const respostas = [];
  for (let i = 0; i < 4; i++) respostas.push((await tentativa.post('/api/auth/login', { login: loginTeste, senha: 'errada' })).status);
  verificar('3 senhas erradas → bloqueio (401,401,401,423)', respostas.join() === '401,401,401,423', respostas.join());
  verificar('Bloqueado não entra nem com a senha certa',
    (await tentativa.post('/api/auth/login', { login: loginTeste, senha: 'senha123' })).status === 423);
  await admin.patch(`/api/usuarios/${novoUsuario.dados.idUsuario}/desbloquear`);
  verificar('Admin desbloqueia e o login volta a funcionar',
    (await tentativa.post('/api/auth/login', { login: loginTeste, senha: 'senha123' })).status === 200);

  // ------------------------------------------------------------------
  titulo('RF02 / RF03 / RF04 / RF05 — Cadastros e grade');
  const cat = await admin.post('/api/categorias', { nomeCategoria: `Teste ${aleatorio(6)}`, tipoCategoria: 'ROUPA' });
  verificar('Cadastra categoria', cat.status === 201);
  verificar('CNPJ inválido é recusado (400)',
    (await admin.post('/api/fornecedores', { razaoSocial: 'X', cnpjFornecedor: '11111111111111' })).status === 400);
  const forn = await admin.post('/api/fornecedores', { razaoSocial: 'Fornecedor Teste', cnpjFornecedor: gerarCnpj() });
  verificar('Cadastra fornecedor com CNPJ válido', forn.status === 201, JSON.stringify(forn.dados));

  const produtoBase = {
    nomeProduto: 'Camiseta Teste', idCategoria: cat.dados.idCategoria, idFornecedor: forn.dados.idFornecedor,
    codigoProduto: `T-${aleatorio(8)}`, generoProduto: 'UNISSEX', precoCusto: 50, precoVenda: 100,
  };
  verificar('Preço de venda menor que o custo é recusado (400)',
    (await admin.post('/api/produtos', { ...produtoBase, precoVenda: 40 })).status === 400);
  const prod = await admin.post('/api/produtos', produtoBase);
  verificar('Cadastra produto', prod.status === 201, JSON.stringify(prod.dados));
  const idProduto = prod.dados.idProduto;
  const codigoM = `789${aleatorio(10)}`;
  const varM = await admin.post(`/api/produtos/${idProduto}/variacoes`, { tamanho: 'M', cor: 'Verde', codigoBarras: codigoM, estoqueMinimo: 2 });
  const varG = await admin.post(`/api/produtos/${idProduto}/variacoes`, { tamanho: 'G', cor: 'Verde', codigoBarras: `789${aleatorio(10)}`, estoqueMinimo: 2 });
  verificar('Cadastra duas variações (grade)', varM.status === 201 && varG.status === 201);
  verificar('Grade repetida é recusada (409)',
    (await admin.post(`/api/produtos/${idProduto}/variacoes`, { tamanho: 'M', cor: 'Verde' })).status === 409);
  const idM = varM.dados.idVariacao;
  const idG = varG.dados.idVariacao;

  // ------------------------------------------------------------------
  titulo('RF08 / RF16 — Entrada de estoque e alertas');
  const entrada = await admin.post('/api/estoque/entradas', {
    idFornecedor: forn.dados.idFornecedor, numeroNotaFiscal: aleatorio(6), dataEntrada: hoje(),
    itens: [{ idVariacao: idM, quantidade: 10, custoUnitario: 50 }, { idVariacao: idG, quantidade: 3, custoUnitario: 50 }],
  });
  verificar('Registra entrada (valor total = 650)', entrada.status === 201 && igual(entrada.dados.valorTotalEntrada, 650), JSON.stringify(entrada.dados));
  verificar('Estoque somado (M = 10, G = 3)',
    (await estoqueDe(admin, idProduto, idM)) === 10 && (await estoqueDe(admin, idProduto, idG)) === 3);

  // ------------------------------------------------------------------
  titulo('RF17 — Consulta com filtros');
  const consulta = await admin.get(`/api/produtos?termo=Camiseta%20Teste&tamanho=G&somenteDisponivel=true`);
  verificar('Filtra por nome + tamanho', consulta.dados.some((p) => p.idProduto === idProduto && p.variacoes.every((v) => v.tamanho === 'G')));
  const porCodigo = await admin.get(`/api/produtos/codigo/${codigoM}`);
  verificar('Busca pelo código de barras (PDV)', porCodigo.status === 200 && porCodigo.dados.idVariacao === idM);

  // ------------------------------------------------------------------
  titulo('RF01 / RNF06 — Clientes e LGPD');
  verificar('CPF inválido é recusado (400)',
    (await caixa.login('caixa', 'caixa123'), await caixa.post('/api/clientes', { nomeCliente: 'X', cpfCliente: '111.111.111-11', telefoneCliente: '71999990000' })).status === 400);
  const cpf = gerarCpf();
  const cli = await caixa.post('/api/clientes', {
    nomeCliente: 'Cliente Teste', cpfCliente: cpf, telefoneCliente: '(71) 98888-7777', consentimentoLgpd: true,
  });
  verificar('Cadastra cliente', cli.status === 201, JSON.stringify(cli.dados));
  verificar('CPF repetido é recusado (409)',
    (await caixa.post('/api/clientes', { nomeCliente: 'Outro', cpfCliente: cpf, telefoneCliente: '71999990000' })).status === 409);
  const listaCli = await caixa.get(`/api/clientes?busca=${cpf.slice(0, 6)}`);
  verificar('Listagem mostra o CPF mascarado', listaCli.dados[0]?.cpfCliente === `***.${cpf.slice(3, 6)}.${cpf.slice(6, 9)}-**`);
  const idCliente = cli.dados.idCliente;

  // ------------------------------------------------------------------
  titulo('RF11 — Promoção automática');
  const promo = await admin.post('/api/promocoes', {
    nomePromocao: 'Teste 10%', tipoDesconto: 'PERCENTUAL', valorDesconto: 10, dataInicio: hoje(), dataFim: hoje(), idProduto,
  });
  verificar('Cadastra promoção', promo.status === 201, JSON.stringify(promo.dados));
  const comPromo = await caixa.get(`/api/produtos/codigo/${codigoM}`);
  verificar('Prévia do PDV já traz o desconto (R$ 10,00)', igual(comPromo.dados.descontoPromocao, 10));

  // Promoção na loja toda: vale para todos os produtos; entre duas, fica a MAIOR (não se somam)
  verificar('Promoção por categoria sem escolher a categoria é recusada (400)', (await admin.post('/api/promocoes', {
    nomePromocao: 'Sem categoria', tipoDesconto: 'PERCENTUAL', valorDesconto: 5, dataInicio: hoje(), dataFim: hoje(), abrangencia: 'CATEGORIA',
  })).status === 400);
  const promoLoja = await admin.post('/api/promocoes', {
    nomePromocao: 'Loja toda 5%', tipoDesconto: 'PERCENTUAL', valorDesconto: 5, dataInicio: hoje(), dataFim: hoje(), abrangencia: 'TODOS',
  });
  verificar('Cadastra promoção para todos os produtos', promoLoja.status === 201, JSON.stringify(promoLoja.dados));
  verificar('Com 10% no produto e 5% na loja, vale a maior (R$ 10,00)',
    igual((await caixa.get(`/api/produtos/codigo/${codigoM}`)).dados.descontoPromocao, 10));
  const promoLoja50 = await admin.post('/api/promocoes', {
    nomePromocao: 'Loja toda 50%', tipoDesconto: 'PERCENTUAL', valorDesconto: 50, dataInicio: hoje(), dataFim: hoje(), abrangencia: 'TODOS',
  });
  verificar('Promoção da loja toda maior que a do produto passa a valer (R$ 50,00)',
    igual((await caixa.get(`/api/produtos/codigo/${codigoM}`)).dados.descontoPromocao, 50));
  await admin.patch(`/api/promocoes/${promoLoja.dados.idPromocao}/desativar`);
  await admin.patch(`/api/promocoes/${promoLoja50.dados.idPromocao}/desativar`);
  verificar('Desativadas, volta a valer só a do produto (R$ 10,00)',
    igual((await caixa.get(`/api/produtos/codigo/${codigoM}`)).dados.descontoPromocao, 10));

  // ------------------------------------------------------------------
  titulo('RF15 — Caixa');
  let caixaAtual = (await caixa.get('/api/caixa/atual')).dados;
  if (caixaAtual) await caixa.post('/api/caixa/fechar', { valorInformado: caixaAtual.dinheiroEsperado });
  verificar('Venda sem caixa aberto é recusada (400)',
    (await caixa.post('/api/vendas', { itens: [{ idVariacao: idM, quantidade: 1 }], pagamentos: [{ formaPagamento: 'PIX', valorPago: 90 }] })).status === 400);
  verificar('Abre o caixa com R$ 100,00', (await caixa.post('/api/caixa/abrir', { valorAbertura: 100 })).status === 201);
  verificar('Não abre dois caixas (409)', (await caixa.post('/api/caixa/abrir', { valorAbertura: 50 })).status === 409);

  // ------------------------------------------------------------------
  titulo('RF09 / RF10 / RF18 / RF19 — Venda');
  const venda1 = await caixa.post('/api/vendas', {
    idCliente, itens: [{ idVariacao: idM, quantidade: 1 }, { idVariacao: idM, quantidade: 1 }],
    pagamentos: [{ formaPagamento: 'DINHEIRO', valorPago: 200 }],
  });
  verificar('Registra venda (2 × R$ 90 = R$ 180, troco R$ 20)',
    venda1.status === 201 && igual(venda1.dados.valorTotal, 180) && igual(venda1.dados.troco, 20), JSON.stringify(venda1.dados));
  verificar('Linhas repetidas do mesmo produto são somadas (estoque M = 8)', (await estoqueDe(admin, idProduto, idM)) === 8);
  verificar('Gera 180 pontos de fidelidade', venda1.dados.pontosGerados === 180);
  verificar('Comprovante numerado', /^CV\d{8}$/.test(venda1.dados.numeroComprovante || ''));
  const idVenda1 = venda1.dados.idVenda;

  const pdf = await caixa.get(`/api/vendas/${idVenda1}/comprovante`);
  verificar('Comprovante em PDF', pdf.tipo.includes('pdf') && pdf.dados.subarray(0, 4).toString() === '%PDF');
  const texto = await caixa.get(`/api/vendas/${idVenda1}/comprovante/texto`);
  verificar('Texto do comprovante com link do WhatsApp', texto.dados.whatsapp?.startsWith('https://wa.me/5571988887777'));

  verificar('Troco em cartão é recusado (400)',
    (await caixa.post('/api/vendas', { itens: [{ idVariacao: idM, quantidade: 1 }], pagamentos: [{ formaPagamento: 'DEBITO', valorPago: 100 }] })).status === 400);
  verificar('Parcelamento acima de 6x é recusado (400)',
    (await caixa.post('/api/vendas', { itens: [{ idVariacao: idM, quantidade: 1 }], pagamentos: [{ formaPagamento: 'CREDITO', valorPago: 90, numeroParcelas: 7 }] })).status === 400);

  // RF11: desconto manual
  verificar('Desconto de 15% sem gerente é recusado (403)',
    (await caixa.post('/api/vendas', { itens: [{ idVariacao: idM, quantidade: 1 }], descontoPercentual: 15, pagamentos: [{ formaPagamento: 'PIX', valorPago: 76.5 }] })).status === 403);
  verificar('Senha errada do gerente é recusada (403)',
    (await caixa.post('/api/vendas', { itens: [{ idVariacao: idM, quantidade: 1 }], descontoPercentual: 15, autorizacao: { login: 'gerente', senha: 'x' }, pagamentos: [{ formaPagamento: 'PIX', valorPago: 76.5 }] })).status === 403);
  const venda2 = await caixa.post('/api/vendas', {
    itens: [{ idVariacao: idM, quantidade: 1 }], descontoPercentual: 15, autorizacao: { login: 'gerente', senha: 'gerente123' },
    pagamentos: [{ formaPagamento: 'CREDITO', valorPago: 76.5, numeroParcelas: 3 }],
  });
  verificar('Desconto de 15% com o gerente: R$ 90 − 15% = R$ 76,50', venda2.status === 201 && igual(venda2.dados.valorTotal, 76.5), JSON.stringify(venda2.dados));

  // RNF09: transação
  const estoqueG = await estoqueDe(admin, idProduto, idG);
  const vendasAntes = (await admin.get('/api/vendas')).dados.length;
  const semEstoque = await caixa.post('/api/vendas', {
    itens: [{ idVariacao: idM, quantidade: 1 }, { idVariacao: idG, quantidade: 99 }], pagamentos: [{ formaPagamento: 'PIX', valorPago: 9999 }],
  });
  verificar('Estoque insuficiente → 400', semEstoque.status === 400, JSON.stringify(semEstoque.dados));
  verificar('…e NADA foi gravado (rollback): estoques e vendas iguais',
    (await estoqueDe(admin, idProduto, idG)) === estoqueG && (await estoqueDe(admin, idProduto, idM)) === 7
      && (await admin.get('/api/vendas')).dados.length === vendasAntes);

  // RF16: vender G até chegar no mínimo gera alerta
  await caixa.post('/api/vendas', { itens: [{ idVariacao: idG, quantidade: 1 }], pagamentos: [{ formaPagamento: 'PIX', valorPago: 90 }] });
  const alertas = (await admin.get('/api/estoque/alertas')).dados;
  verificar('RF16 alerta criado ao chegar no estoque mínimo', alertas.some((a) => a.idVariacao === idG));
  await admin.post('/api/estoque/entradas', { idFornecedor: forn.dados.idFornecedor, dataEntrada: hoje(), itens: [{ idVariacao: idG, quantidade: 5, custoUnitario: 50 }] });
  verificar('…e resolvido quando o estoque é reposto', !(await admin.get('/api/estoque/alertas')).dados.some((a) => a.idVariacao === idG));

  // ------------------------------------------------------------------
  titulo('RF13 / RF14 — Troca, devolução e vale-troca');
  const detalhe1 = (await caixa.get(`/api/vendas/${idVenda1}`)).dados;
  const idItem = detalhe1.itens[0].idItemVenda;
  const devolucao = await caixa.post('/api/trocas', { idItemVenda: idItem, tipoOperacao: 'DEVOLUCAO', motivoTroca: 'ARREPENDIMENTO', quantidade: 1 });
  verificar('Devolução gera vale-troca de R$ 90,00', devolucao.status === 201 && igual(devolucao.dados.vale?.valorVale, 90), JSON.stringify(devolucao.dados));
  verificar('Item devolvido volta ao estoque (M = 8)', (await estoqueDe(admin, idProduto, idM)) === 8);
  const codigoVale = devolucao.dados.vale.codigoVale;
  const vale = await caixa.get(`/api/trocas/vales/${codigoVale}`);
  verificar('Consulta o vale (90 dias de validade)', vale.dados.statusVale === 'ATIVO' && igual(vale.dados.saldoVale, 90));

  const troca = await caixa.post('/api/trocas', { idItemVenda: idItem, tipoOperacao: 'TROCA', motivoTroca: 'TAMANHO', quantidade: 1, idVariacaoNova: idG });
  verificar('Troca de M por G (mesmo preço, diferença R$ 0)', troca.status === 201 && igual(troca.dados.valorDiferenca, 0), JSON.stringify(troca.dados));
  verificar('Não troca mais do que foi comprado (400)',
    (await caixa.post('/api/trocas', { idItemVenda: idItem, tipoOperacao: 'DEVOLUCAO', motivoTroca: 'OUTRO', quantidade: 1 })).status === 400);

  const venda3 = await caixa.post('/api/vendas', {
    idCliente, itens: [{ idVariacao: idM, quantidade: 2 }],
    pagamentos: [{ formaPagamento: 'VALE_TROCA', valorPago: 90, codigoVale }, { formaPagamento: 'PIX', valorPago: 90 }],
  });
  verificar('Paga com vale-troca + PIX', venda3.status === 201, JSON.stringify(venda3.dados));
  verificar('Vale fica UTILIZADO com saldo zero', (await caixa.get(`/api/trocas/vales/${codigoVale}`)).dados.statusVale === 'UTILIZADO');
  verificar('Vale usado não paga outra venda (400)',
    (await caixa.post('/api/vendas', { itens: [{ idVariacao: idM, quantidade: 1 }], pagamentos: [{ formaPagamento: 'VALE_TROCA', valorPago: 10, codigoVale }, { formaPagamento: 'PIX', valorPago: 80 }] })).status === 400);

  // ------------------------------------------------------------------
  titulo('RF18 — Fidelidade');
  const pontos = (await caixa.get(`/api/fidelidade/${idCliente}`)).dados;
  verificar('Saldo = 180 + 180 = 360 pontos', pontos.saldoPontos === 360, JSON.stringify(pontos));
  const venda4 = await caixa.post('/api/vendas', {
    idCliente, pontosResgatar: 200, itens: [{ idVariacao: idM, quantidade: 1 }], pagamentos: [{ formaPagamento: 'PIX', valorPago: 80 }],
  });
  verificar('Resgata 200 pontos = R$ 10,00 de desconto (R$ 80)', venda4.status === 201 && igual(venda4.dados.valorTotal, 80), JSON.stringify(venda4.dados));
  verificar('Resgate fora do múltiplo de 100 é recusado (400)',
    (await caixa.post('/api/vendas', { idCliente, pontosResgatar: 150, itens: [{ idVariacao: idM, quantidade: 1 }], pagamentos: [{ formaPagamento: 'PIX', valorPago: 90 }] })).status === 400);

  // ------------------------------------------------------------------
  titulo('RF12 — Cancelamento');
  const estoqueAntesCancelar = await estoqueDe(admin, idProduto, idM);
  const saldoAntes = (await caixa.get(`/api/fidelidade/${idCliente}`)).dados.saldoPontos;
  verificar('Cancelar sem gerente é recusado (403)',
    (await caixa.post(`/api/vendas/${venda4.dados.idVenda}/cancelar`, { motivoCancelamento: 'Cliente desistiu' })).status === 403);
  const cancelamento = await caixa.post(`/api/vendas/${venda4.dados.idVenda}/cancelar`, {
    motivoCancelamento: 'Cliente desistiu da compra', autorizacao: { login: 'gerente', senha: 'gerente123' },
  });
  verificar('Cancela com autorização do gerente', cancelamento.status === 200, JSON.stringify(cancelamento.dados));
  verificar('Estoque volta (+1)', (await estoqueDe(admin, idProduto, idM)) === estoqueAntesCancelar + 1);
  verificar('Pontos estornados (−80 gerados, +200 resgatados)',
    (await caixa.get(`/api/fidelidade/${idCliente}`)).dados.saldoPontos === saldoAntes - 80 + 200);
  verificar('Não cancela duas vezes (400)',
    (await caixa.post(`/api/vendas/${venda4.dados.idVenda}/cancelar`, { motivoCancelamento: 'De novo', autorizacao: { login: 'gerente', senha: 'gerente123' } })).status === 400);
  verificar('Venda com troca não pode ser cancelada (400)',
    (await caixa.post(`/api/vendas/${idVenda1}/cancelar`, { motivoCancelamento: 'Teste', autorizacao: { login: 'gerente', senha: 'gerente123' } })).status === 400);

  // ------------------------------------------------------------------
  titulo('RF15 — Sangria, suprimento e fechamento');
  caixaAtual = (await caixa.get('/api/caixa/atual')).dados;
  // Dinheiro: 100 (abertura) + 200 − 20 de troco (venda 1) = 280
  verificar('Dinheiro esperado na gaveta = R$ 280,00', igual(caixaAtual.dinheiroEsperado, 280), JSON.stringify(caixaAtual.vendas));
  verificar('Sangria maior que o disponível é recusada (400)',
    (await caixa.post('/api/caixa/movimentos', { tipoMovimento: 'SANGRIA', valorMovimento: 1000 })).status === 400);
  await caixa.post('/api/caixa/movimentos', { tipoMovimento: 'SANGRIA', valorMovimento: 150, descricaoMovimento: 'Depósito' });
  await caixa.post('/api/caixa/movimentos', { tipoMovimento: 'SUPRIMENTO', valorMovimento: 20, descricaoMovimento: 'Troco' });
  const fechamento = await caixa.post('/api/caixa/fechar', { valorInformado: 145 });
  verificar('Fecha o caixa: sistema R$ 150, informado R$ 145, diferença −R$ 5',
    igual(fechamento.dados.valorSistema, 150) && igual(fechamento.dados.diferencaCaixa, -5), JSON.stringify(fechamento.dados));

  // ------------------------------------------------------------------
  titulo('RF20 — Relatórios');
  const tipos = (await admin.get('/api/relatorios')).dados.map((t) => t.tipo);
  verificar('7 relatórios disponíveis', tipos.length === 7, tipos.join());
  for (const tipo of tipos) {
    const periodo = `inicio=${hoje()}&fim=${hoje()}`;
    const json = await admin.get(`/api/relatorios/${tipo}?${periodo}`);
    const relPdf = await admin.get(`/api/relatorios/${tipo}?${periodo}&formato=pdf`);
    const csv = await admin.get(`/api/relatorios/${tipo}?${periodo}&formato=csv`);
    const csvTexto = csv.dados.toString('utf8');
    verificar(`${tipo}: JSON (${json.dados.linhas?.length} linhas), PDF e CSV`,
      json.status === 200 && relPdf.dados.subarray(0, 4).toString() === '%PDF'
        && csvTexto.charCodeAt(0) === 0xfeff && csvTexto.includes(';'),
      `${json.status} ${relPdf.status} ${csv.status}`);
  }
  verificar('Data final antes da inicial é recusada (400)',
    (await admin.get('/api/relatorios/mais-vendidos?inicio=2026-09-30&fim=2026-09-01')).status === 400);

  // ------------------------------------------------------------------
  titulo('RNF06 — Anonimização (LGPD) e logout');
  verificar('Caixa não pode anonimizar (403)', (await caixa.patch(`/api/clientes/${idCliente}/anonimizar`)).status === 403);
  await admin.patch(`/api/clientes/${idCliente}/anonimizar`);
  const anonimizado = (await admin.get(`/api/clientes/${idCliente}`)).dados;
  verificar('Dados pessoais apagados, histórico mantido', anonimizado.nomeCliente.startsWith('Cliente anonimizado') && !anonimizado.emailCliente);
  await caixa.post('/api/auth/logout');
  verificar('Depois do logout, a sessão acaba (401)', (await caixa.get('/api/auth/eu')).status === 401);

  console.log(`\n${falhas === 0 ? '✅' : '❌'} ${total - falhas}/${total} verificações passaram.`);
  process.exit(falhas === 0 ? 0 : 1);
}

main().catch((erro) => {
  console.error('\n💥 Erro inesperado:', erro);
  process.exit(1);
});
