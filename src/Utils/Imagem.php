<?php
declare(strict_types=1);

namespace App\Utils;

use App\Core\ErroApp;
use finfo;

/**
 * Upload de imagens (foto do produto).
 * Segurança: o tipo é conferido pelo CONTEÚDO do arquivo (não pelo nome nem pelo que o navegador diz),
 * o nome é gerado pelo servidor e só JPG, PNG e WebP são aceitos. Assim ninguém consegue enviar um .php.
 */
final class Imagem
{
    private const TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const TAMANHO_MAXIMO = 2 * 1024 * 1024;   // 2 MB
    private const PASTA = 'uploads/produtos';         // dentro de public/ (volume do Docker)

    /** Salva o arquivo enviado e devolve o caminho público (ex.: uploads/produtos/produto-3-a1b2c3.jpg) */
    public static function salvar(?array $arquivo, string $prefixo): string
    {
        $erro = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;
        $grandeDemais = in_array($erro, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($arquivo['size'] ?? 0) > self::TAMANHO_MAXIMO
            // Acima do post_max_size o PHP descarta o envio inteiro: chega sem arquivo nenhum
            || ($erro === UPLOAD_ERR_NO_FILE && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > self::TAMANHO_MAXIMO);
        if ($grandeDemais) throw new ErroApp('A imagem deve ter no máximo 2 MB');
        if ($erro === UPLOAD_ERR_NO_FILE) throw new ErroApp('Escolha uma imagem');
        if ($erro !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) throw new ErroApp('Falha ao receber a imagem');

        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($arquivo['tmp_name']);
        $extensao = self::TIPOS[$tipo] ?? throw new ErroApp('Formato inválido: envie JPG, PNG ou WebP');
        if (getimagesize($arquivo['tmp_name']) === false) throw new ErroApp('O arquivo não é uma imagem válida');

        $pasta = self::raiz() . '/' . self::PASTA;
        if (!is_dir($pasta)) mkdir($pasta, 0775, true);
        $nome = $prefixo . '-' . bin2hex(random_bytes(6)) . '.' . $extensao;
        if (!move_uploaded_file($arquivo['tmp_name'], "$pasta/$nome")) throw new ErroApp('Não foi possível salvar a imagem', 500);
        return self::PASTA . '/' . $nome;
    }

    /** Apaga uma foto enviada. As fotos de exemplo (img/produtos) não são apagadas. */
    public static function apagar(?string $caminho): void
    {
        if (!$caminho || !str_starts_with($caminho, self::PASTA . '/') || str_contains($caminho, '..')) return;
        $arquivo = self::raiz() . '/' . $caminho;
        if (is_file($arquivo)) unlink($arquivo);
    }

    private static function raiz(): string
    {
        return dirname(__DIR__, 2) . '/public';
    }
}
