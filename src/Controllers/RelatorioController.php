<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ErroApp;
use App\Core\Requisicao;
use App\Core\Resposta;
use App\Middlewares\Validacoes;
use App\Models\RelatorioModel;
use App\Utils\Csv;
use App\Utils\Pdf;

final class RelatorioController
{
    public static function tipos(Requisicao $req): void
    {
        $tipos = [];
        foreach (RelatorioModel::relatorios() as $tipo => $r) {
            $tipos[] = ['tipo' => $tipo, 'titulo' => $r['titulo'], 'semPeriodo' => $r['semPeriodo'] ?? false];
        }
        Resposta::json($tipos);
    }

    // RF20: uma consulta → três saídas (tela, PDF, CSV)
    public static function gerar(Requisicao $req): void
    {
        $tipo = $req->param('tipo');
        if (!isset(RelatorioModel::relatorios()[$tipo])) throw new ErroApp('Relatório não encontrado', 404);

        $query = Validacoes::periodo($req->query, $tipo !== 'estoque-atual');
        $inicio = $query['inicio'] ?? null;
        $fim = $query['fim'] ?? null;
        $formato = $query['formato'] ?? 'json';

        $dados = RelatorioModel::gerar($tipo, $inicio, $fim);
        $periodo = $dados['semPeriodo'] ? 'posição atual' : Pdf::dataBr($inicio) . ' a ' . Pdf::dataBr($fim);

        match ($formato) {
            'pdf' => Pdf::relatorio($dados, $periodo),
            'csv' => Csv::gerar($dados),
            default => Resposta::json([...$dados, 'periodo' => $periodo]),
        };
    }
}
