<?php

namespace App\Core;

/**
 * Helper para o Collabora Online (CODE via WOPI).
 *
 * Gerencia:
 *   - Codificação/decodificação do fileId (base64url) usado no PATH_INFO do WOPI
 *   - Geração e validação de access tokens HMAC (sem banco, sem sessão)
 *   - Geração de session IDs únicos por abertura
 *
 * Configuração (.env):
 *   COLLABORA_SECRET=chave_hmac   (fallback: ONLYOFFICE_SECRET → 'softdoc-collabora')
 */
class CollaboraHelper
{
    // ── Session ID ────────────────────────────────────────────────────────────

    /**
     * Gera ID de sessão único por abertura do editor.
     *
     * Formato: <hash8_filekey>_<timestamp_base36>_<random4>
     *
     * @param string $fileKey Chave R2 do arquivo
     * @return string         ID único de até 26 chars
     */
    public static function sessionId(string $fileKey): string
    {
        $hash   = substr(md5($fileKey), 0, 8);
        $time   = base_convert((string) time(), 10, 36);
        $random = bin2hex(random_bytes(2));
        return "{$hash}_{$time}_{$random}";
    }

    // ── FileId (path seguro para uso em URLs) ─────────────────────────────────

    /**
     * Codifica fileKey para fileId seguro em URL (base64url, sem padding).
     *
     * Usado no PATH_INFO do CollaboraWopiController:
     *   /app/Core/WebHooks/CollaboraWopiController.php/{fileId}
     */
    public static function encodeFileId(string $fileKey): string
    {
        return rtrim(strtr(base64_encode($fileKey), '+/', '-_'), '=');
    }

    /**
     * Decodifica fileId de volta para fileKey original.
     * Retorna string vazia se o valor for inválido.
     */
    public static function decodeFileId(string $fileId): string
    {
        $base64 = strtr($fileId, '-_', '+/');
        $padLen = (4 - (strlen($base64) % 4)) % 4;
        if ($padLen > 0) {
            $base64 .= str_repeat('=', $padLen);
        }

        $decoded = base64_decode($base64, true);
        return is_string($decoded) ? $decoded : '';
    }

    // ── Access Token (HMAC, sem banco de dados) ───────────────────────────────

    /**
     * Gera access token HMAC para o fileKey informado.
     *
     * Formato: {expires}.{hmac_sha256}
     * Validade: 2 horas (suficiente para uma sessão de edição)
     *
     * @param string $fileKey Chave R2 do arquivo
     * @return string         Token no formato "expires.hmac"
     */
    public static function generateAccessToken(string $fileKey): string
    {
        $expires = time() + 7200;
        $secret  = self::secret();
        $sig     = hash_hmac('sha256', "{$fileKey}:{$expires}", $secret);
        return "{$expires}.{$sig}";
    }

    /**
     * Valida o access token para o fileKey informado.
     *
     * @param string $token   Token recebido na query string (access_token)
     * @param string $fileKey Chave R2 decodificada do PATH_INFO
     * @return bool           true se válido e não expirado
     */
    public static function validateAccessToken(string $token, string $fileKey): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$expires, $sig] = $parts;

        if (!ctype_digit($expires) || (int) $expires < time()) {
            return false;
        }

        $secret   = self::secret();
        $expected = hash_hmac('sha256', "{$fileKey}:{$expires}", $secret);

        return hash_equals($expected, $sig);
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    private static function secret(): string
    {
        $collaboraSecret = trim((string) SofDocConfigHelper::get('COLLABORA_SECRET', ''));
        if ($collaboraSecret !== '') {
            return $collaboraSecret;
        }

        $onlyOfficeSecret = trim((string) SofDocConfigHelper::get('ONLYOFFICE_SECRET', ''));
        if ($onlyOfficeSecret !== '') {
            return $onlyOfficeSecret;
        }

        return 'softdoc-collabora';
    }
}
