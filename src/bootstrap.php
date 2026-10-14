<?php
/**
 * Inicialização: autoload das classes, variáveis de ambiente, fuso horário e sessão.
 * Usado pelo public/api.php e pelos scripts (criarAdmin.php).
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Fora do Docker (ex.: XAMPP), lê o arquivo .env da raiz do projeto
$arquivoEnv = __DIR__ . '/../.env';
if (getenv('DB_HOST') === false && is_file($arquivoEnv)) {
    foreach (file($arquivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if (str_starts_with(trim($linha), '#') || !str_contains($linha, '=')) continue;
        [$nome, $valor] = array_map('trim', explode('=', $linha, 2));
        putenv("$nome=$valor");
    }
}

date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');

function producao(): bool
{
    return getenv('APP_ENV') === 'production';
}
