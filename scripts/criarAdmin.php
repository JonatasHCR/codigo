<?php
/**
 * Cria um usuário ADMIN com a senha criptografada (bcrypt).
 * Uso:  docker compose exec app php scripts/criarAdmin.php <login> <senha>
 */
declare(strict_types=1);

use App\Config\Database;

require __DIR__ . '/../src/bootstrap.php';

[, $login, $senha] = array_pad($argv, 3, null);
if (!$login || !$senha) {
    echo "Uso: php scripts/criarAdmin.php <login> <senha>\n";
    exit(1);
}
if (strlen($senha) < 6) {
    echo "A senha precisa de pelo menos 6 caracteres.\n";
    exit(1);
}

try {
    Database::conexao()->prepare(
        "INSERT INTO tb_usuario (nomeUsuario, loginUsuario, senhaHash, perfilAcesso) VALUES ('Administrador', ?, ?, 'ADMIN')"
    )->execute([$login, password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10])]);
    echo "Administrador \"$login\" criado.\n";
} catch (PDOException $erro) {
    echo ($erro->errorInfo[1] ?? null) === 1062 ? "Esse login já existe.\n" : $erro->getMessage() . "\n";
    exit(1);
}
