-- Coloca as fotos de exemplo nos produtos do seed em um banco que JÁ EXISTIA
-- (o seed.sql só roda quando o banco é criado do zero). Não mexe em produto que já tem foto.
-- Uso: docker compose exec -T db sh -c 'mysql -h127.0.0.1 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/atualizacoes/001-fotos-exemplo.sql
UPDATE tb_produto SET fotoProduto = 'img/produtos/camisa-polo.png'       WHERE codigoProduto = 'CAM-001' AND fotoProduto IS NULL;
UPDATE tb_produto SET fotoProduto = 'img/produtos/calca-jeans.png'       WHERE codigoProduto = 'CAL-001' AND fotoProduto IS NULL;
UPDATE tb_produto SET fotoProduto = 'img/produtos/vestido-midi.png'      WHERE codigoProduto = 'VES-001' AND fotoProduto IS NULL;
UPDATE tb_produto SET fotoProduto = 'img/produtos/tenis-couro.png'       WHERE codigoProduto = 'TEN-001' AND fotoProduto IS NULL;
UPDATE tb_produto SET fotoProduto = 'img/produtos/sandalia-rasteira.png' WHERE codigoProduto = 'SAN-001' AND fotoProduto IS NULL;
