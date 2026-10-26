-- =====================================================================
--  Dados de teste (executado depois do schema.sql)
--  Usuários de teste (login / senha):
--    admin / admin123        gerente / gerente123      vendedor / vendedor123
--    caixa / caixa123        estoque / estoque123
--  TROQUE estas senhas se o sistema for usado de verdade.
-- =====================================================================

-- Garante que os acentos sejam lidos como UTF-8 (senão "Básica" vira "BÃ¡sica")
SET NAMES utf8mb4;

USE loja_db;

INSERT INTO tb_usuario (nomeUsuario, loginUsuario, senhaHash, perfilAcesso, percentualComissao, dataAdmissao) VALUES
  ('Administrador',  'admin',    '$2b$10$sLMHWR/RKJ7uWv1/A51.9e/pGOHYKxabPOtOoOixy4PyJZ2ANFmUq', 'ADMIN',      0.00, '2026-01-05'),
  ('Gabriela Gerente','gerente', '$2b$10$PnfV.hPzqPcGTzPAQuurH.OE9eOAAqR0yzZjpgtByF8hcHjOD8uES', 'GERENTE',    1.00, '2026-01-05'),
  ('Vitor Vendedor', 'vendedor', '$2b$10$8A/OfDbylpu7vpwtvnMame/1G9m/TDR6.EdJEHYxMs6PaQUkK1YN.', 'VENDEDOR',   3.00, '2026-02-10'),
  ('Carla Caixa',    'caixa',    '$2b$10$IxMgBHjo8QBulo40TRZWKOwG.tNREsv6Ny.QGSh3jWMOcCypjwAqa', 'CAIXA',      0.00, '2026-03-01'),
  ('Eduardo Estoque','estoque',  '$2b$10$mKRoOazjQZbWgXoSVCWYL.VKvWFT/rEDmRAUbr1zWM8zAuMsdOGG6', 'ESTOQUISTA', 0.00, '2026-03-01');

INSERT INTO tb_categoria (nomeCategoria, tipoCategoria, descricaoCategoria) VALUES
  ('Camisas',    'ROUPA',     'Camisas, camisetas e polos'),
  ('Calças',     'ROUPA',     'Calças jeans, sociais e moletom'),
  ('Vestidos',   'ROUPA',     'Vestidos casuais e de festa'),
  ('Tênis',      'CALCADO',   'Tênis esportivos e casuais'),
  ('Sandálias',  'CALCADO',   'Sandálias e rasteirinhas'),
  ('Acessórios', 'ACESSORIO', 'Cintos, bonés e bolsas');

INSERT INTO tb_fornecedor (razaoSocial, nomeFantasia, cnpjFornecedor, telefoneFornecedor, emailFornecedor, nomeContato, cidadeFornecedor, ufFornecedor) VALUES
  ('Confecções Bahia Ltda',       'Bahia Moda',   '11222333000181', '7133334444', 'vendas@bahiamoda.com.br', 'Rita',  'Salvador',  'BA'),
  ('Calçados Nordeste Indústria', 'Passo Firme',  '11444777000161', '7532215566', 'contato@passofirme.com.br', 'Paulo', 'Feira de Santana', 'BA');

-- fotoProduto: imagens de exemplo que vêm com o sistema (public/img/produtos)
INSERT INTO tb_produto (codigoProduto, nomeProduto, descricaoProduto, idCategoria, idFornecedor, marcaProduto, generoProduto, materialProduto, precoCusto, precoVenda, fotoProduto) VALUES
  ('CAM-001', 'Camisa Polo Básica',   'Camisa polo de algodão piquet', 1, 1, 'Bahia Moda',  'MASCULINO', 'algodão', 35.00,  89.90, 'img/produtos/camisa-polo.png'),
  ('CAL-001', 'Calça Jeans Slim',     'Calça jeans com elastano',      2, 1, 'Bahia Moda',  'FEMININO',  'jeans',   60.00, 149.90, 'img/produtos/calca-jeans.png'),
  ('VES-001', 'Vestido Midi Floral',  'Vestido midi estampado',        3, 1, 'Bahia Moda',  'FEMININO',  'viscose', 55.00, 129.90, 'img/produtos/vestido-midi.png'),
  ('TEN-001', 'Tênis Casual Couro',   'Tênis casual de couro legítimo',4, 2, 'Passo Firme', 'UNISSEX',   'couro',   90.00, 229.90, 'img/produtos/tenis-couro.png'),
  ('SAN-001', 'Sandália Rasteira',    'Rasteirinha com tiras',         5, 2, 'Passo Firme', 'FEMININO',  'sintético',25.00, 59.90, 'img/produtos/sandalia-rasteira.png');

INSERT INTO tb_produto_variacao (idProduto, tamanho, cor, codigoBarras, quantidadeEstoque, estoqueMinimo, localizacao) VALUES
  (1, 'P',  'Azul',   '7890000000011', 10, 2, 'A1'),
  (1, 'M',  'Azul',   '7890000000028', 12, 2, 'A1'),
  (1, 'G',  'Azul',   '7890000000035',  8, 2, 'A1'),
  (1, 'M',  'Branco', '7890000000042',  6, 2, 'A2'),
  (2, '38', 'Azul',   '7890000000059',  7, 2, 'B1'),
  (2, '40', 'Azul',   '7890000000066',  5, 2, 'B1'),
  (2, '42', 'Preto',  '7890000000073',  3, 2, 'B2'),
  (3, 'P',  'Floral', '7890000000080',  4, 2, 'C1'),
  (3, 'M',  'Floral', '7890000000097',  4, 2, 'C1'),
  (4, '39', 'Preto',  '7890000000103',  6, 2, 'D1'),
  (4, '40', 'Preto',  '7890000000110',  6, 2, 'D1'),
  (4, '41', 'Marrom', '7890000000127',  2, 2, 'D2'),
  (5, '35', 'Nude',   '7890000000134',  9, 3, 'E1'),
  (5, '36', 'Nude',   '7890000000141',  9, 3, 'E1'),
  (5, '37', 'Preto',  '7890000000158',  5, 3, 'E2');

INSERT INTO tb_cliente (nomeCliente, cpfCliente, dataNascimento, telefoneCliente, emailCliente, cepCliente, enderecoCliente, numeroEndereco, bairroCliente, cidadeCliente, ufCliente, consentimentoLgpd, dataConsentimento) VALUES
  ('Maria Souza',  '52998224725', '1990-05-14', '71999990000', 'maria@email.com', '42700000', 'Rua das Flores', '120', 'Centro', 'Lauro de Freitas', 'BA', TRUE, NOW()),
  ('João Santos',  '11144477735', '1985-11-02', '71988887777', NULL,              NULL,       NULL,             NULL,  NULL,     NULL,               NULL, TRUE, NOW());

INSERT INTO tb_fidelidade (idCliente, pontosAcumulados, pontosUtilizados, saldoPontos) VALUES
  (1, 150, 0, 150),
  (2,   0, 0,   0);
