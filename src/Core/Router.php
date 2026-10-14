<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Roteador: liga cada URL da API ao método do controller que a atende.
 *
 *   $r->grupo('/api/clientes', [Auth::login()], function (Router $r) {
 *       $r->get('/:id', [ClienteController::class, 'buscar']);
 *   });
 *
 * "Guardas" são funções que rodam ANTES do controller (login, perfil) —
 * fazem o papel dos middlewares do Express.
 */
final class Router
{
    private array $rotas = [];
    private string $prefixo = '';
    private array $guardasDoGrupo = [];

    public function grupo(string $prefixo, array $guardas, callable $definir): void
    {
        [$prefixoAnterior, $guardasAnteriores] = [$this->prefixo, $this->guardasDoGrupo];
        $this->prefixo .= $prefixo;
        $this->guardasDoGrupo = [...$this->guardasDoGrupo, ...$guardas];
        $definir($this);
        [$this->prefixo, $this->guardasDoGrupo] = [$prefixoAnterior, $guardasAnteriores];
    }

    public function get(string $caminho, array $acao, array $guardas = []): void { $this->adicionar('GET', $caminho, $acao, $guardas); }
    public function post(string $caminho, array $acao, array $guardas = []): void { $this->adicionar('POST', $caminho, $acao, $guardas); }
    public function put(string $caminho, array $acao, array $guardas = []): void { $this->adicionar('PUT', $caminho, $acao, $guardas); }
    public function patch(string $caminho, array $acao, array $guardas = []): void { $this->adicionar('PATCH', $caminho, $acao, $guardas); }

    private function adicionar(string $metodo, string $caminho, array $acao, array $guardas): void
    {
        $completo = rtrim($this->prefixo . $caminho, '/') ?: '/';
        // "/api/clientes/:id" → expressão regular que captura o id
        $regex = '#^' . preg_replace('#:([a-zA-Z]+)#', '(?P<$1>[^/]+)', $completo) . '$#';
        $this->rotas[] = [$metodo, $regex, $acao, [...$this->guardasDoGrupo, ...$guardas]];
    }

    public function despachar(Requisicao $req): void
    {
        foreach ($this->rotas as [$metodo, $regex, $acao, $guardas]) {
            if ($metodo !== $req->metodo || !preg_match($regex, $req->caminho, $partes)) continue;

            $req->params = array_filter($partes, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($guardas as $guarda) $guarda($req);   // lança ErroApp se não puder passar
            [$classe, $funcao] = $acao;
            $classe::$funcao($req);
            return;
        }
        throw new ErroApp('Rota não encontrada', 404);
    }
}
