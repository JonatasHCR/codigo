-- =====================================================================
--  Loja de roupas e calçados — criação do banco de dados
--  Ver: Construção do Sistema / 02 - Modelagem do Banco
-- =====================================================================

-- Garante que os acentos sejam lidos como UTF-8 (senão "Básica" vira "BÃ¡sica")
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS loja_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE loja_db;

-- Usuário só para o sistema, com permissões limitadas (instalação sem Docker).
-- No Docker, o usuário é criado pelas variáveis MYSQL_USER / MYSQL_PASSWORD.
CREATE USER IF NOT EXISTS 'loja_app'@'localhost' IDENTIFIED BY 'troque_esta_senha';
GRANT SELECT, INSERT, UPDATE, DELETE ON loja_db.* TO 'loja_app'@'localhost';

-- ---------------------------------------------------------------------
-- 1. Tabelas sem dependências
-- ---------------------------------------------------------------------

-- RF03
CREATE TABLE tb_categoria (
  idCategoria        INT AUTO_INCREMENT PRIMARY KEY,
  nomeCategoria      VARCHAR(50)  NOT NULL UNIQUE,
  tipoCategoria      ENUM('ROUPA', 'CALCADO', 'ACESSORIO') NOT NULL,
  descricaoCategoria VARCHAR(200),
  statusCategoria    BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

-- RF02
CREATE TABLE tb_fornecedor (
  idFornecedor       INT AUTO_INCREMENT PRIMARY KEY,
  razaoSocial        VARCHAR(120) NOT NULL,
  nomeFantasia       VARCHAR(100),
  cnpjFornecedor     CHAR(14)     NOT NULL UNIQUE,
  inscricaoEstadual  VARCHAR(20),
  telefoneFornecedor VARCHAR(15),
  emailFornecedor    VARCHAR(100),
  nomeContato        VARCHAR(80),
  enderecoFornecedor VARCHAR(150),
  cidadeFornecedor   VARCHAR(60),
  ufFornecedor       CHAR(2),
  statusFornecedor   BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

-- RF01 (+ consentimento da LGPD, RNF06)
CREATE TABLE tb_cliente (
  idCliente         INT AUTO_INCREMENT PRIMARY KEY,
  nomeCliente       VARCHAR(100) NOT NULL,
  cpfCliente        CHAR(11)     NOT NULL UNIQUE,
  dataNascimento    DATE,
  telefoneCliente   VARCHAR(15)  NOT NULL,
  emailCliente      VARCHAR(100),
  cepCliente        CHAR(8),
  enderecoCliente   VARCHAR(150),
  numeroEndereco    VARCHAR(10),
  bairroCliente     VARCHAR(60),
  cidadeCliente     VARCHAR(60),
  ufCliente         CHAR(2),
  consentimentoLgpd BOOLEAN NOT NULL DEFAULT FALSE,
  dataConsentimento DATETIME,
  dataCadastro      DATETIME DEFAULT CURRENT_TIMESTAMP,
  statusCliente     BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

-- RF06
CREATE TABLE tb_usuario (
  idUsuario          INT AUTO_INCREMENT PRIMARY KEY,
  nomeUsuario        VARCHAR(100) NOT NULL,
  cpfUsuario         CHAR(11) UNIQUE,
  loginUsuario       VARCHAR(30)  NOT NULL UNIQUE,
  senhaHash          VARCHAR(255) NOT NULL,
  perfilAcesso       ENUM('ADMIN', 'GERENTE', 'VENDEDOR', 'CAIXA', 'ESTOQUISTA') NOT NULL,
  percentualComissao DECIMAL(5,2) NOT NULL DEFAULT 0,
  dataAdmissao       DATE,
  tentativasFalhas   INT NOT NULL DEFAULT 0,
  statusUsuario      BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. Dependem das tabelas acima
-- ---------------------------------------------------------------------

-- RF04
CREATE TABLE tb_produto (
  idProduto        INT AUTO_INCREMENT PRIMARY KEY,
  codigoProduto    VARCHAR(20) UNIQUE,
  nomeProduto      VARCHAR(100) NOT NULL,
  descricaoProduto TEXT,
  idCategoria      INT NOT NULL,
  idFornecedor     INT NOT NULL,
  marcaProduto     VARCHAR(50),
  generoProduto    ENUM('MASCULINO', 'FEMININO', 'UNISSEX', 'INFANTIL'),
  materialProduto  VARCHAR(50),
  precoCusto       DECIMAL(10,2) NOT NULL,
  precoVenda       DECIMAL(10,2) NOT NULL,
  fotoProduto      VARCHAR(255),
  statusProduto    BOOLEAN NOT NULL DEFAULT TRUE,
  CONSTRAINT fk_produto_categoria  FOREIGN KEY (idCategoria)  REFERENCES tb_categoria(idCategoria),
  CONSTRAINT fk_produto_fornecedor FOREIGN KEY (idFornecedor) REFERENCES tb_fornecedor(idFornecedor),
  CONSTRAINT ck_preco CHECK (precoVenda >= precoCusto)
) ENGINE=InnoDB;

-- RF07
CREATE TABLE tb_log_acesso (
  idLog          INT AUTO_INCREMENT PRIMARY KEY,
  idUsuario      INT NOT NULL,
  dataHoraLogin  DATETIME NOT NULL,
  dataHoraLogout DATETIME,
  enderecoIp     VARCHAR(45),
  sucessoLogin   BOOLEAN NOT NULL,
  FOREIGN KEY (idUsuario) REFERENCES tb_usuario(idUsuario)
) ENGINE=InnoDB;

-- RF15
CREATE TABLE tb_caixa (
  idCaixa            INT AUTO_INCREMENT PRIMARY KEY,
  idUsuario          INT NOT NULL,
  dataHoraAbertura   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  valorAbertura      DECIMAL(10,2) NOT NULL,
  dataHoraFechamento DATETIME,
  valorInformado     DECIMAL(10,2),
  valorSistema       DECIMAL(10,2),
  diferencaCaixa     DECIMAL(10,2),
  statusCaixa        ENUM('ABERTO', 'FECHADO') NOT NULL DEFAULT 'ABERTO',
  FOREIGN KEY (idUsuario) REFERENCES tb_usuario(idUsuario)
) ENGINE=InnoDB;

-- RF18
CREATE TABLE tb_fidelidade (
  idFidelidade          INT AUTO_INCREMENT PRIMARY KEY,
  idCliente             INT NOT NULL UNIQUE,
  pontosAcumulados      INT NOT NULL DEFAULT 0,
  pontosUtilizados      INT NOT NULL DEFAULT 0,
  saldoPontos           INT NOT NULL DEFAULT 0,
  dataUltimaAtualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idCliente) REFERENCES tb_cliente(idCliente),
  CONSTRAINT ck_saldo_pontos CHECK (saldoPontos >= 0)
) ENGINE=InnoDB;

-- RF14
CREATE TABLE tb_vale_troca (
  idVale       INT AUTO_INCREMENT PRIMARY KEY,
  idCliente    INT NULL,
  codigoVale   VARCHAR(12) NOT NULL UNIQUE,
  valorVale    DECIMAL(10,2) NOT NULL,
  saldoVale    DECIMAL(10,2) NOT NULL,
  dataEmissao  DATE NOT NULL,
  dataValidade DATE NOT NULL,
  statusVale   ENUM('ATIVO', 'UTILIZADO', 'VENCIDO') NOT NULL DEFAULT 'ATIVO',
  FOREIGN KEY (idCliente) REFERENCES tb_cliente(idCliente),
  CONSTRAINT ck_saldo_vale CHECK (saldoVale >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. Grade, promoções, caixa e estoque
-- ---------------------------------------------------------------------

-- RF05
CREATE TABLE tb_produto_variacao (
  idVariacao        INT AUTO_INCREMENT PRIMARY KEY,
  idProduto         INT NOT NULL,
  tamanho           VARCHAR(5)  NOT NULL,
  cor               VARCHAR(30) NOT NULL,
  codigoBarras      VARCHAR(13) UNIQUE,
  quantidadeEstoque INT NOT NULL DEFAULT 0,
  estoqueMinimo     INT NOT NULL DEFAULT 2,
  localizacao       VARCHAR(30),
  CONSTRAINT fk_variacao_produto FOREIGN KEY (idProduto) REFERENCES tb_produto(idProduto),
  CONSTRAINT ck_estoque CHECK (quantidadeEstoque >= 0),
  CONSTRAINT uq_grade UNIQUE (idProduto, tamanho, cor)
) ENGINE=InnoDB;

-- RF11
CREATE TABLE tb_promocao (
  idPromocao     INT AUTO_INCREMENT PRIMARY KEY,
  nomePromocao   VARCHAR(80) NOT NULL,
  tipoDesconto   ENUM('PERCENTUAL', 'VALOR_FIXO') NOT NULL,
  valorDesconto  DECIMAL(10,2) NOT NULL,
  dataInicio     DATE NOT NULL,
  dataFim        DATE NOT NULL,
  -- Onde vale: TODOS os produtos (loja toda), uma CATEGORIA ou um PRODUTO
  abrangencia    ENUM('TODOS', 'CATEGORIA', 'PRODUTO') NOT NULL DEFAULT 'PRODUTO',
  idCategoria    INT NULL,
  idProduto      INT NULL,
  statusPromocao BOOLEAN NOT NULL DEFAULT TRUE,
  FOREIGN KEY (idCategoria) REFERENCES tb_categoria(idCategoria),
  FOREIGN KEY (idProduto)   REFERENCES tb_produto(idProduto),
  CONSTRAINT ck_promocao_datas CHECK (dataFim >= dataInicio),
  CONSTRAINT ck_promocao_valor CHECK (valorDesconto > 0),
  -- A abrangência e os campos preenchidos precisam combinar
  CONSTRAINT ck_promocao_alvo CHECK (
       (abrangencia = 'TODOS'     AND idCategoria IS NULL     AND idProduto IS NULL)
    OR (abrangencia = 'CATEGORIA' AND idCategoria IS NOT NULL AND idProduto IS NULL)
    OR (abrangencia = 'PRODUTO'   AND idProduto IS NOT NULL   AND idCategoria IS NULL))
) ENGINE=InnoDB;

-- RF15
CREATE TABLE tb_movimento_caixa (
  idMovimento        INT AUTO_INCREMENT PRIMARY KEY,
  idCaixa            INT NOT NULL,
  tipoMovimento      ENUM('SANGRIA', 'SUPRIMENTO') NOT NULL,
  valorMovimento     DECIMAL(10,2) NOT NULL,
  descricaoMovimento VARCHAR(150),
  dataHoraMovimento  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idCaixa) REFERENCES tb_caixa(idCaixa),
  CONSTRAINT ck_movimento_valor CHECK (valorMovimento > 0)
) ENGINE=InnoDB;

-- RF08
CREATE TABLE tb_entrada_estoque (
  idEntrada         INT AUTO_INCREMENT PRIMARY KEY,
  idFornecedor      INT NOT NULL,
  numeroNotaFiscal  VARCHAR(20),
  dataEntrada       DATE NOT NULL,
  valorTotalEntrada DECIMAL(10,2) NOT NULL,
  idUsuario         INT NOT NULL,
  FOREIGN KEY (idFornecedor) REFERENCES tb_fornecedor(idFornecedor),
  FOREIGN KEY (idUsuario)    REFERENCES tb_usuario(idUsuario)
) ENGINE=InnoDB;

CREATE TABLE tb_item_entrada (
  idItemEntrada INT AUTO_INCREMENT PRIMARY KEY,
  idEntrada     INT NOT NULL,
  idVariacao    INT NOT NULL,
  quantidade    INT NOT NULL CHECK (quantidade > 0),
  custoUnitario DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (idEntrada)  REFERENCES tb_entrada_estoque(idEntrada),
  FOREIGN KEY (idVariacao) REFERENCES tb_produto_variacao(idVariacao)
) ENGINE=InnoDB;

-- RF16
CREATE TABLE tb_alerta_estoque (
  idAlerta        INT AUTO_INCREMENT PRIMARY KEY,
  idVariacao      INT NOT NULL,
  quantidadeAtual INT NOT NULL,
  dataAlerta      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  statusAlerta    ENUM('PENDENTE', 'RESOLVIDO') NOT NULL DEFAULT 'PENDENTE',
  FOREIGN KEY (idVariacao) REFERENCES tb_produto_variacao(idVariacao)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. Vendas
-- ---------------------------------------------------------------------

-- RF09 (+ pontos de fidelidade da venda, para estornar no cancelamento)
CREATE TABLE tb_venda (
  idVenda          INT AUTO_INCREMENT PRIMARY KEY,
  dataHoraVenda    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  idCliente        INT NULL,
  idUsuario        INT NOT NULL,
  idCaixa          INT NOT NULL,
  valorBruto       DECIMAL(10,2) NOT NULL,
  valorDesconto    DECIMAL(10,2) NOT NULL DEFAULT 0,
  valorTotal       DECIMAL(10,2) NOT NULL,
  pontosUtilizados INT NOT NULL DEFAULT 0,
  pontosGerados    INT NOT NULL DEFAULT 0,
  statusVenda      ENUM('ABERTA', 'FINALIZADA', 'CANCELADA') NOT NULL DEFAULT 'ABERTA',
  FOREIGN KEY (idCliente) REFERENCES tb_cliente(idCliente),
  FOREIGN KEY (idUsuario) REFERENCES tb_usuario(idUsuario),
  FOREIGN KEY (idCaixa)   REFERENCES tb_caixa(idCaixa),
  INDEX idx_venda_data (dataHoraVenda)
) ENGINE=InnoDB;

CREATE TABLE tb_item_venda (
  idItemVenda   INT AUTO_INCREMENT PRIMARY KEY,
  idVenda       INT NOT NULL,
  idVariacao    INT NOT NULL,
  quantidade    INT NOT NULL CHECK (quantidade > 0),
  precoUnitario DECIMAL(10,2) NOT NULL,
  descontoItem  DECIMAL(10,2) NOT NULL DEFAULT 0,
  subtotal      DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (idVenda)    REFERENCES tb_venda(idVenda),
  FOREIGN KEY (idVariacao) REFERENCES tb_produto_variacao(idVariacao)
) ENGINE=InnoDB;

-- RF10 (+ idVale quando a forma é VALE_TROCA)
CREATE TABLE tb_pagamento (
  idPagamento       INT AUTO_INCREMENT PRIMARY KEY,
  idVenda           INT NOT NULL,
  formaPagamento    ENUM('DINHEIRO', 'PIX', 'DEBITO', 'CREDITO', 'VALE_TROCA') NOT NULL,
  valorPago         DECIMAL(10,2) NOT NULL,
  numeroParcelas    INT NOT NULL DEFAULT 1,
  valorTroco        DECIMAL(10,2) NOT NULL DEFAULT 0,
  codigoAutorizacao VARCHAR(30),
  idVale            INT NULL,
  dataHoraPagamento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (idVenda) REFERENCES tb_venda(idVenda),
  FOREIGN KEY (idVale)  REFERENCES tb_vale_troca(idVale),
  CONSTRAINT ck_parcelas CHECK (numeroParcelas BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- RF12
CREATE TABLE tb_cancelamento (
  idCancelamento       INT AUTO_INCREMENT PRIMARY KEY,
  idVenda              INT NOT NULL UNIQUE,
  motivoCancelamento   VARCHAR(200) NOT NULL,
  dataHoraCancelamento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  idUsuarioSolicitante INT NOT NULL,
  idUsuarioAutorizador INT NOT NULL,
  FOREIGN KEY (idVenda)              REFERENCES tb_venda(idVenda),
  FOREIGN KEY (idUsuarioSolicitante) REFERENCES tb_usuario(idUsuario),
  FOREIGN KEY (idUsuarioAutorizador) REFERENCES tb_usuario(idUsuario)
) ENGINE=InnoDB;

-- RF13 (+ idVale gerado na devolução)
CREATE TABLE tb_troca (
  idTroca        INT AUTO_INCREMENT PRIMARY KEY,
  idVenda        INT NOT NULL,
  idItemVenda    INT NOT NULL,
  idVariacaoNova INT NULL,
  tipoOperacao   ENUM('TROCA', 'DEVOLUCAO') NOT NULL,
  motivoTroca    ENUM('TAMANHO', 'COR', 'DEFEITO', 'ARREPENDIMENTO', 'OUTRO') NOT NULL,
  quantidade     INT NOT NULL CHECK (quantidade > 0),
  valorDiferenca DECIMAL(10,2) NOT NULL DEFAULT 0,
  idVale         INT NULL,
  dataTroca      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  idUsuario      INT NOT NULL,
  FOREIGN KEY (idVenda)        REFERENCES tb_venda(idVenda),
  FOREIGN KEY (idItemVenda)    REFERENCES tb_item_venda(idItemVenda),
  FOREIGN KEY (idVariacaoNova) REFERENCES tb_produto_variacao(idVariacao),
  FOREIGN KEY (idVale)         REFERENCES tb_vale_troca(idVale),
  FOREIGN KEY (idUsuario)      REFERENCES tb_usuario(idUsuario),
  INDEX idx_troca_data (dataTroca)
) ENGINE=InnoDB;

-- RF19
CREATE TABLE tb_comprovante (
  idComprovante      INT AUTO_INCREMENT PRIMARY KEY,
  idVenda            INT NOT NULL UNIQUE,
  numeroComprovante  VARCHAR(20) NOT NULL UNIQUE,
  chaveAcessoNfce    CHAR(44),
  dataEmissao        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  formatoEnvio       ENUM('IMPRESSO', 'EMAIL', 'WHATSAPP') NOT NULL DEFAULT 'IMPRESSO',
  FOREIGN KEY (idVenda) REFERENCES tb_venda(idVenda)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. Sessões de login (guardadas pelo PHP — src/Config/Sessao.php)
-- ---------------------------------------------------------------------
CREATE TABLE sessions (
  session_id VARCHAR(128) COLLATE utf8mb4_bin NOT NULL PRIMARY KEY,
  expires    INT UNSIGNED NOT NULL,
  data       MEDIUMTEXT COLLATE utf8mb4_bin
) ENGINE=InnoDB;
