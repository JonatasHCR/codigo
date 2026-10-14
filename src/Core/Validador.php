<?php
declare(strict_types=1);

namespace App\Core;

use App\Utils\Cpf;

/**
 * Validação no servidor (RNF09) — faz o papel do express-validator.
 * Cada método LIMPA o dado (sanitizer) e CONFERE (validator).
 * No primeiro problema lança ErroApp 400 com a mensagem; o controller nem continua.
 */
final class Validador
{
    public function __construct(private array $dados) {}

    /** Os dados já limpos e convertidos (números, textos sem espaços nas pontas...) */
    public function dados(): array
    {
        return $this->dados;
    }

    private function falhar(string $mensagem): never
    {
        throw new ErroApp($mensagem, 400);
    }

    // "Vazio" no sentido do optional({ values: 'falsy' }): null, '', false, 0
    private function vazio(string $campo): bool
    {
        $v = $this->dados[$campo] ?? null;
        return $v === null || $v === '' || $v === false || $v === 0 || $v === '0';
    }

    public function texto(string $campo, string $mensagem, int $maximo = 0, bool $obrigatorio = true): self
    {
        $valor = trim((string) ($this->dados[$campo] ?? ''));
        $this->dados[$campo] = $valor;
        if ($valor === '') {
            if ($obrigatorio) $this->falhar($mensagem);
            return $this;
        }
        if ($maximo && mb_strlen($valor) > $maximo) $this->falhar("Texto muito longo em $campo (máximo $maximo caracteres)");
        return $this;
    }

    /** Remove tudo que não for dígito: "529.982.247-25" → "52998224725" */
    public function somenteNumeros(string $campo): self
    {
        $this->dados[$campo] = preg_replace('/\D/', '', (string) ($this->dados[$campo] ?? ''));
        return $this;
    }

    public function cpf(string $campo, string $mensagem = 'CPF inválido', bool $obrigatorio = true): self
    {
        $this->somenteNumeros($campo);
        if ($this->dados[$campo] === '' && !$obrigatorio) return $this;
        if (!Cpf::cpfValido($this->dados[$campo])) $this->falhar($mensagem);
        return $this;
    }

    public function cnpj(string $campo, string $mensagem = 'CNPJ inválido'): self
    {
        $this->somenteNumeros($campo);
        if (!Cpf::cnpjValido($this->dados[$campo])) $this->falhar($mensagem);
        return $this;
    }

    /** Só dígitos, com quantidade entre $minimo e $maximo (telefone, CEP, código de barras) */
    public function digitos(string $campo, int $minimo, int $maximo, string $mensagem, bool $obrigatorio = true): self
    {
        if (!$obrigatorio && $this->vazio($campo)) return $this;
        $this->somenteNumeros($campo);
        $tamanho = strlen($this->dados[$campo]);
        if ($tamanho < $minimo || $tamanho > $maximo) $this->falhar($mensagem);
        return $this;
    }

    public function email(string $campo, string $mensagem = 'E-mail inválido'): self
    {
        if ($this->vazio($campo)) return $this;
        $this->dados[$campo] = trim((string) $this->dados[$campo]);
        if (!filter_var($this->dados[$campo], FILTER_VALIDATE_EMAIL)) $this->falhar($mensagem);
        return $this;
    }

    /** Data no formato AAAA-MM-DD (o que o <input type="date"> envia) */
    public function data(string $campo, string $mensagem, bool $obrigatorio = true): self
    {
        if (!$obrigatorio && $this->vazio($campo)) return $this;
        $valor = (string) ($this->dados[$campo] ?? '');
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $p) || !checkdate((int) $p[2], (int) $p[3], (int) $p[1])) {
            $this->falhar($mensagem);
        }
        return $this;
    }

    public function uf(string $campo): self
    {
        if ($this->vazio($campo)) return $this;
        $valor = strtoupper(trim((string) $this->dados[$campo]));
        $ufs = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
            'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
        if (!in_array($valor, $ufs, true)) $this->falhar('UF inválida');
        $this->dados[$campo] = $valor;
        return $this;
    }

    public function booleano(string $campo): self
    {
        $this->dados[$campo] = filter_var($this->dados[$campo] ?? false, FILTER_VALIDATE_BOOLEAN);
        return $this;
    }

    public function inteiro(string $campo, string $mensagem, ?int $minimo = null, ?int $maximo = null, bool $obrigatorio = true): self
    {
        if (!$obrigatorio && $this->vazio($campo)) return $this;
        $valor = filter_var($this->dados[$campo] ?? null, FILTER_VALIDATE_INT);
        if ($valor === false || ($minimo !== null && $valor < $minimo) || ($maximo !== null && $valor > $maximo)) {
            $this->falhar($mensagem);
        }
        $this->dados[$campo] = $valor;
        return $this;
    }

    /** Número com casas decimais. $maiorQue = valor estritamente maior; $minimo = maior ou igual */
    public function decimal(string $campo, string $mensagem, ?float $minimo = null, ?float $maiorQue = null, ?float $maximo = null, bool $obrigatorio = true): self
    {
        if (!$obrigatorio && ($this->dados[$campo] ?? null) === null) return $this;
        $valor = filter_var($this->dados[$campo] ?? null, FILTER_VALIDATE_FLOAT);
        if ($valor === false
            || ($minimo !== null && $valor < $minimo)
            || ($maiorQue !== null && $valor <= $maiorQue)
            || ($maximo !== null && $valor > $maximo)) {
            $this->falhar($mensagem);
        }
        $this->dados[$campo] = $valor;
        return $this;
    }

    public function opcao(string $campo, array $permitidas, string $mensagem, bool $obrigatorio = true): self
    {
        if (!$obrigatorio && $this->vazio($campo)) return $this;
        if (!in_array($this->dados[$campo] ?? null, $permitidas, true)) $this->falhar($mensagem);
        return $this;
    }

    public function lista(string $campo, string $mensagem): self
    {
        $valor = $this->dados[$campo] ?? null;
        if (!is_array($valor) || !array_is_list($valor) || count($valor) === 0) $this->falhar($mensagem);
        return $this;
    }

    public function formato(string $campo, string $regex, string $mensagem): self
    {
        $valor = trim((string) ($this->dados[$campo] ?? ''));
        if (!preg_match($regex, $valor)) $this->falhar($mensagem);
        $this->dados[$campo] = $valor;
        return $this;
    }

    /** Regra livre: se a condição for falsa, falha com a mensagem */
    public function regra(bool $condicao, string $mensagem): self
    {
        if (!$condicao) $this->falhar($mensagem);
        return $this;
    }

    /** Lê um campo já validado (para regras que comparam dois campos) */
    public function valor(string $campo): mixed
    {
        return $this->dados[$campo] ?? null;
    }

    /** Substitui um campo (ex.: a lista de itens já validada) */
    public function definir(string $campo, mixed $valor): self
    {
        $this->dados[$campo] = $valor;
        return $this;
    }
}
