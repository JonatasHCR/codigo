<?php
declare(strict_types=1);

namespace App\Utils;

/** RF20: CSV que abre certo no Excel brasileiro */
final class Csv
{
    /** "Relatório de Vendas" → "relatorio-de-vendas" (nome de arquivo sem acentos) */
    public static function nomeArquivo(string $titulo): string
    {
        $semAcento = (string) iconv('UTF-8', 'ASCII//TRANSLIT', $titulo);
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($semAcento)), '-');
    }

    public static function gerar(array $dados): void
    {
        $campo = fn ($valor) => '"' . str_replace('"', '""', (string) ($valor ?? '')) . '"';
        $formatar = function ($valor, ?string $tipo) {
            if ($valor === null) return '';
            return match ($tipo) {
                'moeda', 'numero' => str_replace('.', ',', (string) $valor),   // decimal com vírgula
                'data' => Pdf::dataBr((string) $valor),
                default => $valor,
            };
        };

        $linhas = [array_map(fn ($c) => $c['titulo'], $dados['colunas'])];
        foreach ($dados['linhas'] as $l) {
            $linhas[] = array_map(fn ($c) => $formatar($l[$c['campo']] ?? null, $c['tipo'] ?? null), $dados['colunas']);
        }
        $csv = implode("\r\n", array_map(fn ($linha) => implode(';', array_map($campo, $linha)), $linhas));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . self::nomeArquivo($dados['titulo']) . '.csv"');
        echo "\u{FEFF}" . $csv;   // BOM: o Excel reconhece UTF-8 (acentos certos)
    }
}
