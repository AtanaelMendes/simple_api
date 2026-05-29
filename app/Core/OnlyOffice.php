<?php

namespace App\Core;

use App\Core\Utilities\DAO;

/**
 * Helper JWT para o OnlyOffice.
 *
 * OnlyOffice usa HMAC-SHA256 (HS256) para assinar a configuração do editor
 * e validar callbacks. Implementação manual — sem dependência externa.
 *
 * Configuração (.env):
 *   ONLYOFFICE_SECRET=sua_chave_secreta
 */
class OnlyOffice
{
    /**
     * Resolve o secret do OnlyOffice priorizando o ambiente atual.
     */
    public static function secret(): string
    {
        $envSecret = trim((string) ($_ENV['ONLYOFFICE_SECRET'] ?? ''));
        if ($envSecret !== '') {
            return $envSecret;
        }

        // Evita cache de sessão desatualizado para JWT crítico do editor.
        try {
            $dao = new DAO();
            $rows = $dao->select('SELECT onlyoffice_secret FROM sysfat_sofdoc_config ORDER BY id DESC LIMIT 1');
            if ($dao->hasRows($rows)) {
                $row = $dao->first($rows);
                $dbSecret = trim((string) ($row['onlyoffice_secret'] ?? ''));
                if ($dbSecret !== '') {
                    return $dbSecret;
                }
            }
        } catch (\Throwable) {
            // Fallback silencioso para manter compatibilidade.
        }

        return trim((string) SofDocConfigHelper::get('ONLYOFFICE_SECRET', ''));
    }

    /**
     * Gera token JWT HS256 assinado com ONLYOFFICE_SECRET.
     *
     * @param array $payload Dados a assinar (geralmente o config do editor)
     * @return string Token JWT
     */
    public static function generateJwt(array $payload): string
    {
        $header  = self::b64Encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $body    = self::b64Encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $secret  = self::secret();
        $sig     = self::b64Encode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        return "{$header}.{$body}.{$sig}";
    }

    /**
     * Valida JWT recebido no header Authorization do callback.
     *
     * @param string $token Token JWT
     * @return array|null   Payload decodificado, ou null se inválido
     */
    public static function validateJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $sig] = $parts;
        $secret   = self::secret();
        $expected = self::b64Encode(hash_hmac('sha256', "{$header}.{$body}", $secret, true));

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $decoded = json_decode(self::b64Decode($body), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Gera a chave de documento ÚNICA por abertura do editor.
     *
     * Chaves distintas forçam nova sessão de edição no OnlyOffice,
     * evitando que o editor abra em modo somente leitura (colisão).
     *
     * Formato: <hash8_filekey>_<timestamp_base36>_<random4>
     * Exemplo: a1b2c3d4_lj3k8z_e5f2   (max 50 chars)
     *
     * @param string $fileKey Chave R2 do arquivo
     * @return string         Chave única de até 26 chars
     */
    public static function documentKey(string $fileKey): string
    {
        $hash   = substr(md5($fileKey), 0, 8);
        $time   = base_convert((string) time(), 10, 36);
        $random = bin2hex(random_bytes(2));
        return "{$hash}_{$time}_{$random}";
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    private static function b64Encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64Decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}