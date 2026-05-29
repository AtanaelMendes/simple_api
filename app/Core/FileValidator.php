<?php

namespace App\Core\Middlewares;

/**
 * Objeto encadeável retornado por Request::getFileVal().
 *
 * Terminais:
 *   ->value       — propriedade mágica; retorna o array $_FILES ou null (campo opcional)
 *   ->required()  — lança InvalidArgumentException se nenhum arquivo foi enviado
 *
 * Validadores (retornam $this; só executam se o arquivo não for null):
 *   ->invalidType()          — lança se houve erro de upload
 *   ->maxSize(int $bytes)    — lança se o tamanho exceder $bytes
 *   ->maxFileSize(string)    — lança se o tamanho exceder o valor legível ('2mb', '500kb', etc.)
 *   ->mimeType(string[])     — lança se o MIME type não estiver na lista permitida
 *   ->extension(string[])    — lança se a extensão não estiver na lista permitida
 *
 * Exemplos:
 *   $photo = $request->getFileVal('photo')
 *       ->maxSize(2 * 1024 * 1024)
 *       ->maxFileSize('3mb')
 *       ->required('Foto de perfil é obrigatória!');
 *
 *   $doc = $request->getFileVal('doc')
 *       ->invalidType('Falha no envio do arquivo')
 *       ->mimeType(['application/pdf'], 'Apenas PDF é aceito')
 *       ->extension(['pdf'], 'Extensão inválida')
 *       ->maxFileSize('10mb')
 *       ->value;  // opcional
 */
class FileValidator
{
    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int}|null $fileData
     */
    public function __construct(
        private readonly string $key,
        private readonly mixed $fileData,
        private readonly bool $uploadValid,
    ) {}

    /** Acesso via ->value: retorna o array $_FILES[$key] ou null. */
    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            return $this->fileData;
        }
        return null;
    }

    /** Terminal obrigatório: lança se nenhum arquivo foi enviado. */
    public function required(string $message = 'Arquivo obrigatório!'): mixed
    {
        if ($this->fileData === null) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this->fileData;
    }

    /** Lança se houve erro no upload (ex.: falha de transferência, sem permissão). */
    public function invalidType(string $message = 'Erro no envio do arquivo!'): static
    {
        if (!$this->uploadValid) {
            throw new \InvalidArgumentException($message, 400);
        }
        return $this;
    }

    /**
     * Lança se o tamanho exceder $maxBytes (int → bytes) ou se as dimensões excederem
     * [largura, altura] em pixels (array → validação de imagem).
     */
    public function maxSize(int|array $maxBytes, string $message = 'Arquivo muito grande!'): static
    {
        if ($this->fileData === null) {
            return $this;
        }

        if (is_array($maxBytes)) {
            [$maxWidth, $maxHeight] = $maxBytes;
            $imageInfo = @getimagesize($this->fileData['tmp_name']);
            if ($imageInfo === false) {
                throw new \InvalidArgumentException('Arquivo inválido: não é uma imagem.', 400);
            }
            [$width, $height] = $imageInfo;
            if ($width > $maxWidth || $height > $maxHeight) {
                throw new \InvalidArgumentException(
                    "A imagem deve ter no máximo {$maxWidth}x{$maxHeight}px. Recebida: {$width}x{$height}px.",
                    400
                );
            }
            return $this;
        }

        if ($this->fileData['size'] > $maxBytes) {
            throw new \InvalidArgumentException($message . " (máximo: $maxBytes bytes)", 400);
        }
        return $this;
    }

    /**
     * Lança se o tamanho exceder o valor em formato legível.
     * Aceita: '500', '500b', '2kb', '3mb', '1gb' (case-insensitive).
     */
    public function maxFileSize(string $size, string $message = 'Arquivo muito grande!'): static
    {
        return $this->maxSize($this->parseSize($size), $message. " (máximo: $size)");
    }

    /**
     * Lança se o MIME type do arquivo não estiver na lista permitida.
     * Exemplo: ->mimeType(['image/jpeg', 'image/png'])
     */
    public function mimeType(array $allowed, string $message = 'Tipo de arquivo não permitido!'): static
    {
        if ($this->fileData !== null && !in_array($this->fileData['type'], $allowed, true)) {
            throw new \InvalidArgumentException($message . " (permitido: " . implode(', ', $allowed) . ")", 400);
        }
        return $this;
    }

    /**
     * Lança se a extensão do arquivo não estiver na lista permitida (sem ponto, case-insensitive).
     * Exemplo: ->extension(['jpg', 'png', 'gif'])
     */
    public function extension(array $allowed, string $message = 'Extensão de arquivo não permitida!'): static
    {
        if ($this->fileData !== null) {
            $ext = strtolower(pathinfo($this->fileData['name'], PATHINFO_EXTENSION));
            $allowed = array_map('strtolower', $allowed);
            if (!in_array($ext, $allowed, true)) {
                throw new \InvalidArgumentException($message . " (permitido: " . implode(', ', $allowed) . ")", 400);
            }
        }
        return $this;
    }

    // -------------------------------------------------------------------------

    /** Converte string de tamanho legível para bytes. */
    private function parseSize(string $size): int
    {
        $size  = strtolower(trim($size));
        $unit  = ltrim(preg_replace('/[0-9.]/', '', $size));
        $value = (float) preg_replace('/[^0-9.]/', '', $size);

        return (int) match ($unit) {
            'kb'    => $value * 1024,
            'mb'    => $value * 1024 ** 2,
            'gb'    => $value * 1024 ** 3,
            default => $value,   // 'b' ou sem unidade → bytes
        };
    }
}
