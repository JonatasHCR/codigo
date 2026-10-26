# Loja Modelo — Sistema de gestão para loja de roupas e calçados

Sistema web com **PDV, estoque com grade, caixa, trocas, fidelidade e relatórios**, construído a partir do
levantamento de requisitos (20 RFs e 10 RNFs).

**Stack (RNF01):** HTML5 + CSS3 + JavaScript (ES6+) · **PHP 8.3** (Apache) · MySQL 8 · Docker

---

## Como rodar

### Com Docker (recomendado; igual em qualquer computador)
Pré-requisito: **Docker Desktop** ligado.

```bash
cp .env.example .env          # e troque as senhas (no Windows: copy .env.example .env)
docker compose up -d --build
```
Abra **http://localhost:3000**. Na primeira vez, o MySQL cria as tabelas (`database/schema.sql`) e os dados de teste (`database/seed.sql`),
e o Docker baixa as bibliotecas PHP com o **Composer** (só o FPDF, para os PDFs).

> Banco criado **antes** das fotos de exemplo? O `seed.sql` só roda em banco novo. Para colocar as fotos nos produtos
> de exemplo sem apagar nada, rode o comando que está no início de `database/atualizacoes/001-fotos-exemplo.sql`.

### Sem Docker (XAMPP/WAMP)
1. Rode `composer install` na pasta do projeto.
2. Aponte o Apache para a pasta **`public/`** (com `mod_rewrite` e `AllowOverride All`, por causa do `public/.htaccess`).
3. Crie o banco com `database/schema.sql` e `database/seed.sql`.
4. Ajuste o `.env` (`DB_HOST=localhost`, `DB_PORT=3306`...). O `src/bootstrap.php` lê esse arquivo sozinho.
5. Para as fotos dos produtos, o Apache precisa poder gravar em `public/uploads/` (a pasta é criada no primeiro envio).

### Usuários de teste
| Login | Senha | Perfil | O que vê |
|---|---|---|---|
| `admin` | `admin123` | Administrador | Tudo, inclusive usuários |
| `gerente` | `gerente123` | Gerente | Tudo menos usuários; autoriza descontos e cancelamentos |
| `vendedor` | `vendedor123` | Vendedor | PDV, caixa, vendas, trocas, clientes, produtos |
| `caixa` | `caixa123` | Caixa | Igual ao vendedor; entra direto no PDV |
| `estoque` | `estoque123` | Estoquista | Produtos, estoque, fornecedores, categorias |

> ⚠️ Troque essas senhas se o sistema for usado de verdade. Para criar outro admin:
> `docker compose exec app php scripts/criarAdmin.php novoAdmin SenhaForte123`

### Roteiro rápido de demonstração
1. Entre como **caixa** → **Caixa** → abra com R$ 100,00.
2. **PDV** → leia/digite o código `7890000000028` (Camisa Polo M Azul) e aperte **Enter**.
3. Cliente: digite `Maria` → ela tem 150 pontos → resgate 100 pontos (R$ 5,00).
4. Pague em dinheiro com valor maior → veja o troco → **Concluir compra** → **Imprimir cupom** / **WhatsApp**.
5. Desconto de 15% → o sistema pede **login e senha do gerente** (`gerente` / `gerente123`).
6. **Trocas** → nº da venda → devolução → gera **vale-troca** → use o código como pagamento no PDV.
7. Entre como **gerente** → **Painel** (alertas de estoque) → **Relatórios** (tela, PDF e CSV).

---

## Estrutura

```
public/            ← o que o navegador acessa (DocumentRoot do Apache)
  api.php          ← ponto de entrada da API (tudo em /api/... cai aqui pelo .htaccess)
  .htaccess        ← reescrita /api → api.php + cabeçalhos de segurança
  *.html, css/, js/, fonts/   ← telas (HTML, CSS e JavaScript puros)
  img/produtos/    ← fotos de exemplo dos produtos do seed
  uploads/         ← fotos enviadas pela tela de produtos (no Docker, volume fotos-produtos)
src/               ← BACK-END em PHP (protegido: fora da pasta pública)
  bootstrap.php    ← autoload, .env, fuso horário
  rotas.php        ← todas as URLs da API e quem pode acessar cada uma
  Config/          ← Database (PDO + transações), Sessao (sessão no MySQL, 15 min)
  Core/            ← Router, Requisicao, Resposta, ErroApp, Validador
  Middlewares/     ← Auth (login e perfil), Validacoes (regras de cada formulário)
  Controllers/     ← regras de negócio de cada módulo
  Models/          ← SQL de cada módulo
  Utils/           ← Cpf, Dinheiro, Loja, Autorizacao, Imagem (upload de fotos), Pdf (FPDF), Csv
database/          ← schema.sql, seed.sql e atualizacoes/ (scripts para bancos que já existiam)
scripts/           ← criarAdmin.php, backup.bat, smoke.js (teste)
docker/            ← apache.conf e php.ini usados na imagem
```

---

## Testes
```bash
npm run smoke
```
Teste de ponta a ponta da API (**86 verificações**), escrito em JavaScript e rodado com o Node.js do PC: ele só chama a API por HTTP,
então funciona com qualquer linguagem no servidor. Cobre login e bloqueio, permissões, cadastros, grade, entrada de estoque,
foto do produto (upload, PHP disfarçado recusado), promoção, venda com transação (inclusive **rollback** com estoque insuficiente), troco, parcelas, autorização do gerente,
troca, devolução, vale-troca, fidelidade, cancelamento, caixa, os 7 relatórios em JSON/PDF/CSV e LGPD.

---

## Onde cada requisito está

| Requisito | Back-end (`src/`) | Tela (`public/`) |
|---|---|---|
| RF01 Clientes (+ CEP pela ViaCEP) | `Models/ClienteModel.php` | `clientes.html` |
| RF02 Fornecedores (CNPJ validado) | `Models/FornecedorModel.php` | `fornecedores.html` |
| RF03 Categorias | `Models/CategoriaModel.php` | `categorias.html` |
| RF04 Produtos (com foto) · RF05 Grade · RF17 Consulta | `Models/ProdutoModel.php`, `Utils/Imagem.php` | `produtos.html` |
| RF06 Funcionários | `Models/UsuarioModel.php` | `usuarios.html` |
| RF07 Login, bloqueio na 3ª tentativa, log, menu por perfil | `Controllers/AuthController.php`, `Middlewares/Auth.php` | `login.html`, `js/menu.js` |
| RF08 Entrada de mercadorias | `Models/EstoqueModel.php` | `estoque.html` |
| RF09–RF11, RF18, RF19 Venda, pagamentos, promoções, fidelidade, comprovante | `Models/VendaModel.php` (uma transação) | `pdv.html` |
| RF11 Cadastro de promoções | `Models/PromocaoModel.php` | `promocoes.html` |
| RF12 Cancelamento (com gerente) | `VendaModel::cancelar` | `vendas.html` |
| RF13 Trocas e devoluções · RF14 Vale-troca | `Models/TrocaModel.php` | `trocas.html` |
| RF15 Caixa | `Models/CaixaModel.php` | `caixa.html` |
| RF16 Alerta de estoque mínimo | `EstoqueModel::verificarEstoqueMinimo` | `index.html`, `estoque.html` |
| RF20 Relatórios (7 tipos, PDF e CSV) | `Models/RelatorioModel.php`, `Utils/Pdf.php`, `Utils/Csv.php` | `relatorios.html` |

| RNF | Como foi atendido |
|---|---|
| 01 Tecnologia | HTML5, CSS3, JS ES6+ (módulos), PHP 8.3 + Apache, MySQL 8, Docker |
| 02 Responsividade | Grid `auto-fit`, Flexbox, media queries; testado em 360px |
| 03 Usabilidade | pt-BR, R$, DD/MM/AAAA, leitor de código de barras no PDV |
| 04 Desempenho | Índices, `LIMIT`, prepared statements, debounce nas buscas |
| 05 Segurança | `password_hash` (bcrypt), sessão de 15 min sem uso, cookie `HttpOnly`, perfis no back-end, cabeçalhos de segurança; HTTPS em produção |
| 06 LGPD | Consentimento, CPF mascarado nas listas, anonimização (ADMIN) |
| 07 Disponibilidade e backup | `restart: unless-stopped`; `scripts/backup.bat` (diário, 30 dias) |
| 08 Compatibilidade | Chrome/Firefox/Edge; leitor USB (Enter); cupom PDF de 80 mm |
| 09 Integridade | Transações PDO com `FOR UPDATE` + `CHECK` no banco; validação no front e no back |
| 10 Manutenibilidade | MVC, camelCase, PSR-4 (Composer), HTML semântico, CSS separado, Git |

---

## Observações
- **NFC-e simulada:** a chave de 44 dígitos tem o formato real, mas não é transmitida à SEFAZ (documento sem valor fiscal).
- **WhatsApp / e-mail:** o comprovante abre o WhatsApp (`wa.me`) ou o e-mail (`mailto:`) do próprio computador, com o texto pronto.
- **Backup:** `scripts\backup.bat` salva em `backups/` e apaga cópias com mais de 30 dias.
- **Recomeçar do zero** (apaga TODOS os dados): `docker compose down -v` e depois `docker compose up -d`.
- **Histórico:** o back-end foi escrito primeiro em Node.js (Express) e depois migrado para PHP, mantendo a mesma API e o mesmo banco.
