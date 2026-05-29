<?php

namespace App\Core\Middlewares;

/**
 * Objeto encadeável retornado pelos métodos *Val() do Request.
 *
 * Terminais:
 *   ->value       — propriedade mágica; retorna o valor sem lançar (campo opcional)
 *   ->required()             — método; lança InvalidArgumentException se null (campo obrigatório)
 *   ->requiredIfEmpty([...])    — método; lança se este campo E todos os listados estiverem vazios
 *   ->requiredIfNotEmpty([...]) — método; lança se este campo estiver vazio E qualquer listado estiver preenchido
 *
 * Validadores (retornam $this para encadeamento; só executam se o valor não for null):
 *   ->invalidType()       — lança se o tipo não pôde ser convertido
 *   ->strMinLength(n)     — lança se strlen < n
 *   ->strMaxLength(n)     — lança se strlen > n
 *   ->minVal(n)               — lança se valor < n
 *   ->maxVal(n)               — lança se valor > n
 *   ->minDate('dd/mm/yyyy')   — lança se data < limite  (alias: notMenorQue)
 *   ->maxDate('dd/mm/yyyy')   — lança se data > limite  (alias: notMaiorQue)
 *   ->betweenDate($de, $ate)  — lança se data fora do intervalo
 *   ->notMaiorQue($data)      — alias de maxDate
 *   ->notMenorQue($data)      — alias de minDate
 *
 * Formatos de data aceitos: d/m/Y, d/m/Y H:i:s, Y-m-d, Y-m-d H:i:s e variantes.
 *
 * Exemplos:
 *   $nome  = $request->getStringVal('nome')->strMinLength(3, 'Mín. 3 letras')->value;
 *   $nome  = $request->getStringVal('nome')->strMinLength(3)->strMaxLength(100)->required();
 *   $valor = $request->getIntVal('valor')->invalidType()->minVal(0)->required();
 *   $dt    = $request->getDataVal('dt')->minDate('01/01/2020')->maxDate('31/12/2030')->required();
 */
class Validator
{
    public function __construct(
        private readonly string $key,
        private readonly mixed $rawValue,
        private readonly mixed $typedValue,
        private readonly bool $typeValid,
        private readonly ?array $allParams = null,
    ) {}

    /** Acesso via ->value: retorna o valor sem lançar exceção. */
    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            return $this->typedValue;
        }
        return null;
    }

    /** Terminal obrigatório: lança se o valor for null, caso contrário retorna o valor. */
    public function required(string $message = 'Parâmetros obrigatórios insuficientes!'): mixed
    {
        if ($this->typedValue === null) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this->typedValue;
    }

    /**
     * Lança se este campo estiver vazio E todos os campos listados em $fields também estiverem vazios.
     * Útil para grupos onde pelo menos um campo deve ser fornecido.
     *
     * Exemplo: ->requiredIfEmpty(['email', 'nome'])
     * → lança somente se 'email' e 'nome' também estiverem ausentes/vazios.
     */
    public function requiredIfEmpty(array $fields, string $message = 'Parâmetros obrigatórios insuficientes!'): static
    {
        if (!is_null($this->typedValue) && $this->typedValue !== '') {
            return $this;
        }

        foreach ($fields as $field) {
            $sibling = $this->allParams[$field] ?? null;
            if (!is_null($sibling) && $sibling !== '') {
                return $this;
            }
        }

        throw new \InvalidArgumentException($message, 400);
    }

    /**
     * Lança se este campo estiver vazio E qualquer um dos campos listados em $fields estiver preenchido.
     * Útil para campos que se tornam obrigatórios quando um campo dependente é informado.
     *
     * Exemplo: ->requiredIfNotEmpty(['cargo_id'])
     * → lança se 'cargo_id' estiver preenchido mas este campo estiver vazio.
     */
    public function requiredIfNotEmpty(array $fields, string $message = 'Parâmetros obrigatórios insuficientes!'): static
    {
        if (!is_null($this->typedValue) && $this->typedValue !== '') {
            return $this;
        }

        foreach ($fields as $field) {
            $sibling = $this->allParams[$field] ?? null;
            if (!is_null($sibling) && $sibling !== '') {
                throw new \InvalidArgumentException($message, 400);
            }
        }

        return $this;
    }

    /** Lança se o tipo não pôde ser convertido (ex.: "abc" para inteiro, email com formato inválido). */
    public function invalidType(string $message = 'Parâmetros inválidos!'): static
    {
        if (!$this->typeValid) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Lança se o comprimento da string for menor que $min. */
    public function strMinLength(int $min, string $message = 'Valor muito curto!'): static
    {
        if ($this->typedValue !== null && mb_strlen((string) $this->typedValue) < $min) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Lança se o valor não for igual a $other. */
    public function equal(mixed $other, string $message = 'Os valores não correspondem!'): static
    {
        if ($this->typedValue !== null && $this->typedValue !== $other) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Lança se o comprimento da string for maior que $max. */
    public function strMaxLength(int $max, string $message = 'Valor muito longo!'): static
    {
        if ($this->typedValue !== null && mb_strlen((string) $this->typedValue) > $max) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Lança se o valor numérico for menor que $min. */
    public function minVal(int|float $min, string $message = 'Valor abaixo do mínimo!'): static
    {
        if ($this->typedValue !== null && $this->typedValue < $min) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Lança se o valor numérico for maior que $max. */
    public function maxVal(int|float $max, string $message = 'Valor acima do máximo!'): static
    {
        if ($this->typedValue !== null && $this->typedValue > $max) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Validadores de data
    // -------------------------------------------------------------------------

    /** Lança se a data for anterior a $minDate. */
    public function minDate(string $minDate, string $message = 'Data anterior ao mínimo permitido!'): static
    {
        if ($this->typedValue === null) return $this;

        $value = $this->toDateTime($this->typedValue);
        $limit = $this->toDateTime($minDate);

        if ($value !== null && $limit !== null && $value < $limit) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Lança se a data for posterior a $maxDate. */
    public function maxDate(string $maxDate, string $message = 'Data posterior ao máximo permitido!'): static
    {
        if ($this->typedValue === null) return $this;

        $value = $this->toDateTime($this->typedValue);
        $limit = $this->toDateTime($maxDate);

        if ($value !== null && $limit !== null && $value > $limit) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Lança se a data não estiver no intervalo [$de, $ate]. */
    public function betweenDate(string $de, string $ate, string $message = 'Data fora do intervalo permitido!'): static
    {
        if ($this->typedValue === null) return $this;

        $value = $this->toDateTime($this->typedValue);
        $from  = $this->toDateTime($de);
        $to    = $this->toDateTime($ate);

        if ($value !== null && $from !== null && $to !== null && ($value < $from || $value > $to)) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /** Alias de maxDate: lança se a data for maior que $data. */
    public function notMaiorQue(string $data, string $message = 'Data não pode ser maior que o limite!'): static
    {
        return $this->maxDate($data, $message);
    }

    /** Alias de minDate: lança se a data for menor que $data. */
    public function notMenorQue(string $data, string $message = 'Data não pode ser menor que o limite!'): static
    {
        return $this->minDate($data, $message);
    }

    // -------------------------------------------------------------------------

    /**
     * Converte string ou DateTime para \DateTime.
     * Aceita: d/m/Y, d/m/Y H:i:s, Y-m-d, Y-m-d H:i:s e variantes comuns.
     */
    private function toDateTime(mixed $value): ?\DateTime
    {
        if ($value instanceof \DateTime) return $value;
        if (!is_string($value)) return null;

        $value = trim($value);
        if ($value === '') return null;

        // Palavras-chave de data relativa (pt-BR e en)
        $keywords = [
            'hoje'    => 'now',
            'today'   => 'now',
            'amanha'  => '+1 day',
            'amanhã'  => '+1 day',
            'ontem'   => '-1 day',
        ];
        if (isset($keywords[strtolower($value)])) {
            return new \DateTime($keywords[strtolower($value)]);
        }

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
        ];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt;
            }
        }

        $ts = strtotime($value);
        return $ts !== false ? (new \DateTime())->setTimestamp($ts) : null;
    }
}