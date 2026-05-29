<?php

namespace App\Core\Middlewares;

class Sanitizer
{
	/**
	 * Normaliza recursivamente o input recebido.
	 *
	 * Objetivos:
	 * - NÃO escapar HTML (isso é responsabilidade da camada de view)
	 * - Remover espaços extras de strings
	 * - Converter strings "null", "undefined", "nan" para null
	 * - Converter strings booleanas para bool
	 * - Converter números numéricos para int/float
	 * - Sanitizar arrays recursivamente
	 */
	public static function sanitize(array $input): array
	{
		$clean = [];

		foreach ($input as $key => $value) {
			// Normaliza a chave (sem escapar HTML)
			$cleanKey = trim((string)$key);

			// Arrays → recursivo
			if (is_array($value)) {
				$clean[$cleanKey] = self::sanitize($value);
				continue;
			}

			// Valores escalares já tipados → mantém
			if (is_int($value) || is_float($value) || is_bool($value) || is_null($value)) {
				$clean[$cleanKey] = $value;
				continue;
			}

			// Converte qualquer coisa para string normalizada
			$value = trim((string)$value);

			// Strings vazias → null (opcional mas útil para APIs)
			if ($value === '') {
				$clean[$cleanKey] = null;
				continue;
			}

			$lower = strtolower($value);

			// Strings "null like"
			if (in_array($lower, ['null', 'undefined', 'nan'], true)) {
				$clean[$cleanKey] = null;
				continue;
			}

			// Booleanos vindos do front
			if (in_array($lower, ['true', 'false'], true)) {
				$clean[$cleanKey] = $lower === 'true';
				continue;
			}

			// Inteiros
			if (ctype_digit($value)) {
				$clean[$cleanKey] = (int)$value;
				continue;
			}

			// Floats
			if (is_numeric($value)) {
				$clean[$cleanKey] = (float)$value;
				continue;
			}

			// String normal (mantém conteúdo original)
			$clean[$cleanKey] = $value;
		}

		return $clean;
	}

	public function sanitizeFileName(string $name): string
	{
		$name = trim($name);

		// remove path traversal
		$name = basename($name);

		// remove caracteres perigosos
		$name = preg_replace('/[^\w\s\-.]/u', '', $name);

		// troca espaços por _
		$name = preg_replace('/\s+/', '_', $name);

		// evita nome vazio
		if ($name === '') {
			$name = 'file_' . time();
		}

		return $name;
	}

	public function parseDateValue(mixed $value): ?\DateTime
	{
		if (!is_string($value)) return null;

		// limpeza básica
		$value = trim($value);
		$value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

		if ($value === '') {
			return null;
		}

		// formatos aceitos do frontend
		$formats = [
			'd/m/Y H:i:s',
			'd/m/Y H:i',
			'd/m/Y',
			'Y-m-d H:i:s',
			'Y-m-d H:i',
			'Y-m-d',
			'Y-m-d\TH:i:s',
			'Y-m-d\TH:i',
			'd-m-Y H:i:s',
			'd-m-Y H:i',
			'd-m-Y',
		];

		foreach ($formats as $format) {
			$date = \DateTime::createFromFormat($format, $value);
			if ($date && $date->format($format) === $value) {
				return $date;
			}
		}

		// fallback seguro (timestamp unix ou string parseável)
		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return null;
		}

		return (new \DateTime())->setTimestamp($timestamp);
	}

	/**
	 * [
	 *	 	[
	 *		    'name' => 'foto.png',
	 *		    'tmp'  => '/tmp/php123',
	 *		    'size' => 12345,
	 *		    'type' => 'image/png',
	 *		    'error'=> 0,
	 *		    'ext'  => 'png'
	 *		]
	 *	]
	 * $files = $request->getFiles(
	 *		'arquivo',
	 *		5, // 5MB
	 *		['image/png','image/jpeg','application/pdf']
	 *	);
	 *
	 * @param string $key
	 * @param integer $maxSizeMb
	 * @param array $allowedMime
	 * @return array
	 */
	public function getFiles(string $key, int $maxSizeMb = 10, array $allowedMime = []): array
	{
		if (!isset($_FILES[$key])) return [];

		$files = $_FILES[$key];
		$normalized = [];

		// Normaliza estrutura (single vs multiple upload)
		if (!is_array($files['name'])) {
			$files = [
				'name'     => [$files['name']],
				'type'     => [$files['type']],
				'tmp_name' => [$files['tmp_name']],
				'error'    => [$files['error']],
				'size'     => [$files['size']],
			];
		}

		foreach ($files['name'] as $i => $name) {

			if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

			$tmp  = $files['tmp_name'][$i];
			$size = $files['size'][$i];
			$type = mime_content_type($tmp) ?: $files['type'][$i];

			// valida tamanho
			$maxBytes = $maxSizeMb * 1024 * 1024;
			if ($size > $maxBytes) continue;

			// valida mime se informado
			if (!empty($allowedMime) && !in_array($type, $allowedMime, true)) continue;

			// sanitiza nome do arquivo
			$safeName = $this->sanitizeFileName($name);

			$normalized[] = [
				'name'  => $safeName,
				'tmp'   => $tmp,
				'size'  => $size,
				'type'  => $type,
				'error' => 0,
				'ext'   => strtolower(pathinfo($safeName, PATHINFO_EXTENSION))
			];
		}

		return $normalized;
	}

	public function getBoolean(mixed $value, bool $default = false): bool
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_string($value)) {
			$lower = strtolower($value);
			if (in_array($lower, ['true', '1', 'yes', "on"], true)) {
				return true;
			}
			if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
				return false;
			}
		}
		return $default;
	}

	public function getString(mixed $value, ?string $default = null): ?string
	{
		if (!is_string($value)) {
			return $default;
		}

		// trim básico
		$value = trim($value);

		// remove caracteres de controle invisíveis (segurança/log/DB)
		// remove \x00-\x1F e \x7F (control chars ASCII)
		$value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

		// normaliza espaços múltiplos
		$value = preg_replace('/\s+/u', ' ', $value);

		// proteção contra strings gigantes (ataque comum)
		$maxLength = 5000;
		if (mb_strlen($value) > $maxLength) {
			$value = mb_substr($value, 0, $maxLength);
		}

		return $value;
	}

	public function getInt(mixed $value, ?int $default = null): ?int
	{
		// Já é inteiro
		if (is_int($value)) {
			return $value;
		}

		// Float → converte apenas se for inteiro exato (10.0 ok, 10.5 não)
		if (is_float($value)) {
			return floor($value) == $value ? (int)$value : $default;
		}

		if (!is_string($value)) {
			return $default;
		}

		$value = trim($value);

		if ($value === '') {
			return $default;
		}

		// Remove tudo que não seja número ou sinal negativo
		$value = preg_replace('/[^\d\-]/u', '', $value);

		if ($value === '' || $value === '-') {
			return $default;
		}

		// Evita overflow absurdo (ataque comum)
		if (strlen($value) > 19) {
			return $default;
		}

		if (!preg_match('/^-?\d+$/', $value)) {
			return $default;
		}

		return (int)$value;
	}

	public function getFloat(mixed $value, ?float $default = null): ?float
	{
		if (is_float($value) || is_int($value)) {
			return (float)$value;
		}

		if (!is_string($value)) {
			return $default;
		}

		$value = trim($value);

		if ($value === '') {
			return $default;
		}

		// Remove moeda e qualquer caractere que não seja número , . ou -
		$value = preg_replace('/[^\d,.\-]/u', '', $value);

		if ($value === '' || $value === '-' || $value === '.' || $value === ',') {
			return $default;
		}

		// Detecta formato brasileiro: 1.234,56
		if (str_contains($value, ',') && str_contains($value, '.')) {
			$value = str_replace('.', '', $value); // remove separador milhar
			$value = str_replace(',', '.', $value); // vírgula vira decimal
		}
		// Apenas vírgula → decimal brasileiro
		elseif (str_contains($value, ',')) {
			$value = str_replace(',', '.', $value);
		}

		if (!is_numeric($value)) {
			return $default;
		}

		return (float)$value;
	}

	public function getEmail(string $value, ?string $default = null): ?string
	{
		if (!self::isValidEmailValue($value)) return $default;

		return $value;
	}

	public function isEmail(string $value): bool
	{
		return self::isValidEmailValue($value);
	}

	private static function isValidEmailValue(mixed $value): bool
	{
		if (!is_string($value)) {
			return false;
		}

		$value = trim($value);
		if ($value === '') {
			return false;
		}

		// O @ em ASCII/hex é 0x40; o problema aqui não é o caractere em si,
		// e sim evitar que o método seja desviado para Request::isEmail().
		$value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

		if (strlen($value) > 254) {
			return false;
		}

		return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
	}


	public function isNumeric(mixed $value): bool
	{
		if (is_int($value) || is_float($value)) {
			return true;
		}

		if (!is_string($value)) return false;

		$value = trim($value);

		if ($value === '') return false;

		// Remove tudo que não seja número, ponto ou sinal negativo
		$value = preg_replace('/[^\d\.\-]/u', '', $value);

		if ($value === '' || $value === '-' || $value === '.') {
			return false;
		}

		return is_numeric($value);
	}

	public function isBoolean(mixed $value): bool
	{
		if (is_bool($value)) return true;

		if (!is_string($value)) return false;

		$lower = strtolower($value);
		return in_array($lower, ['true', 'false', '1', '0'], true);
	}

	public function isArray(mixed $value): bool
	{
		return is_array($value);
	}

	public function isNull(mixed $value): bool
	{
		if (is_null($value)) return true;

		if (!is_string($value)) return false;

		$lower = strtolower($value);
		return in_array($lower, ['null', 'undefined', 'nan'], true);
	}

	public function isDate(mixed $value): bool
	{
		return $this->parseDateValue($value) !== null;
	}

	public function getCpfFormatado(string $cpf): ?string
	{
		if (!$this->isCpf($cpf)) {
			return null;
		}

		return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
	}

	public function getCpfSemFormatacao(string $cpf): ?string
	{
		if (!$this->isCpf($cpf)) {
			return null;
		}

		return preg_replace('/[^0-9]/', '', $cpf);
	}

	public function isCpf(string $cpf): bool
	{
		if (empty($cpf)) {
			return false;
		}
		// Extrai somente os números
		$cpf = preg_replace('/[^0-9]/is', '', $cpf);
		// Verifica se foi informado todos os digitos corretamente
		if (strlen($cpf) != 11) {
			return false;
		}
		// Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
		if (preg_match('/(\d)\1{10}/', $cpf)) {
			return false;
		}
		// Faz o calculo para validar o CPF
		for ($t = 9; $t < 11; $t++) {
			for ($d = 0, $c = 0; $c < $t; $c++) {
				$d += $cpf[$c] * (($t + 1) - $c);
			}
			$d = ((10 * $d) % 11) % 10;
			if ($cpf[$c] != $d) {
				return false;
			}
		}
		return true;
	}
}
