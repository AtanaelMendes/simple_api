<?php

namespace App\Core;

use App\Core\FileManager;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * Helper para upload de arquivos para Cloudflare R2 (S3-compatible)
 *
 * Estrutura das chaves no bucket:
 * {bucket_name}/{company_id}/tables/{id}/file.ext
 * {bucket_name}/{company_id}/lixeira/{id}/file.ext
 * {bucket_name}/{company_id}/profile_images/user_{id}.jpg
 */
class UploadR2Helper
{
    private static ?S3Client $client = null;

    /**
     * Retorna o company_id configurado (prefixo de pasta no R2)
     */
    private static function getCompanyId(): string
    {
        $id = (string) SofDocConfigHelper::get('COMPANY_ID', '');
        if (empty($id)) {
            throw new \RuntimeException('[UploadR2Helper] company_id não configurado. Acesse Configurações > Geral e defina o ID da empresa.');
        }
        return $id;
    }

    private static function getClient(): S3Client
    {
        if (self::$client === null) {
            $endpoint = rtrim(trim((string) SofDocConfigHelper::get('R2_API_URL', '')), '/');
            $region = trim((string) SofDocConfigHelper::get('R2_REGION', 'auto'));
            $accessId = trim((string) SofDocConfigHelper::get('R2_ACCESS_ID', ''));
            $accessKey = trim((string) SofDocConfigHelper::get('R2_ACCESS_KEY', ''));

            if ($endpoint === '' || $accessId === '' || $accessKey === '') {
                throw new \RuntimeException('[UploadR2Helper] Configuração R2 incompleta (endpoint/credenciais).');
            }

            $credentials = [
                'key'    => $accessId,
                'secret' => $accessKey,
            ];

            $sessionToken = trim((string) SofDocConfigHelper::get('R2_ACCESS_TOKEN', ''));
            $isCloudflareR2Endpoint = str_contains(strtolower($endpoint), '.r2.cloudflarestorage.com');
            if ($sessionToken !== '' && !$isCloudflareR2Endpoint) {
                $credentials['token'] = $sessionToken;
            }

            self::$client = new S3Client([
                'version'                 => 'latest',
                'region'                  => $region !== '' ? $region : 'auto',
                'endpoint'                => $endpoint,
                'signature_version'       => 'v4',
                'credentials'             => $credentials,
                'use_path_style_endpoint' => true,
            ]);
        }

        return self::$client;
    }

    /**
     * Faz upload de um arquivo para o Cloudflare R2
     *
     * @param string $folder    Pasta de destino no bucket (ex: 'profile_images', 'tables/tbl_251_901')
     * @param array  $file      Arquivo no formato $_FILES entry
     * @param int    $maxSizeMb Tamanho máximo permitido em MB (padrão 50)
     * @return string|null      Chave do objeto no bucket ou null em caso de erro
     */
    public static function upload(string $folder, array $file, int $maxSizeMb = 50): ?string
    {
        if (!isset($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        // Validar tamanho
        $limitBytes = $maxSizeMb * 1024 * 1024;
        if ((int)($file['size'] ?? 0) > $limitBytes) {
            error_log("[UploadR2Helper] Arquivo excede o limite de {$maxSizeMb}MB.");
            return null;
        }

        // Validar extensão + MIME via FileManager (whitelist existente)
        if (!FileManager::isAllowedFile($file['tmp_name'], $file['name'])) {
            error_log('[UploadR2Helper] Tipo de arquivo não permitido: ' . ($file['name'] ?? ''));
            return null;
        }

        // Gerar chave única: {company_id}/folder/basename_dmY_His.ext
        $fileInfo  = pathinfo($file['name']);
        $baseName  = preg_replace('/[\/\\\\:*?"<>|\s]+/', '_', $fileInfo['filename'] ?? 'file');
        $ext       = strtolower($fileInfo['extension'] ?? '');
        $fileName  = $baseName . '_' . date('dmY_His') . ($ext !== '' ? '.' . $ext : '');
        $folder    = rtrim($folder, '/');
        $companyId = self::getCompanyId();
        $fileKey   = "{$companyId}/{$folder}/{$fileName}";

        // Detectar Content-Type real
        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected) {
                $mimeType = $detected;
            }
        }

        try {
            $bucket = trim((string) SofDocConfigHelper::get('R2_BUCKET_NAME', ''));
            if ($bucket === '') {
                throw new \RuntimeException('[UploadR2Helper] Bucket R2 não configurado.');
            }

            self::getClient()->putObject([
                'Bucket'      => $bucket,
                'Key'         => $fileKey,
                'SourceFile'  => $file['tmp_name'],
                'ContentType' => $mimeType,
            ]);

            return $fileKey;
        } catch (AwsException $e) {
            error_log('[UploadR2Helper] Erro no upload: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retorna a URL pública de acesso a um objeto no R2
     *
     * @param string $fileKey Chave do objeto no bucket
     * @return string         URL completa para o arquivo
     */
    public static function getFileUrl(?string $fileKey): string
    {
        $fileKey = trim((string) $fileKey);
        if ($fileKey === '') {
            return '';
        }

        $publicUrl = (string) SofDocConfigHelper::get('R2_PUBLIC_URL', '');
        if (!empty(trim($publicUrl))) {
            return rtrim($publicUrl, '/') . '/' . ltrim($fileKey, '/');
        }

        // Fallback: proxy PHP autenticado (evita expor URL privada do R2 ao browser)
        $baseUrl = rtrim($_ENV['BASE_URL'] ?? '', '/');

        return "{$baseUrl}/app/Core/R2Proxy.php?key=" . rawurlencode($fileKey);
    }

    /**
     * Resolve a chave de destino na lixeira para um objeto R2.
     *
     * Regra:
     *   tables/tbl_251_901/3/file.ext  →  lixeira/tbl_251_901/3/file.ext
     *   profile_picture/5/img.jpg      →  lixeira/profile_picture/5/img.jpg
     *
     * @param string $fileKey Chave original do objeto
     * @return string         Chave de destino na lixeira
     */
    private static function buildTrashKey(string $fileKey): string
    {
        $companyId = self::getCompanyId();
        $newPrefix  = $companyId . '/tables/';

        // Novo formato: {company_id}/tables/... → {company_id}/lixeira/...
        if (str_starts_with($fileKey, $newPrefix)) {
            return $companyId . '/lixeira/' . substr($fileKey, strlen($newPrefix));
        }

        // Legado sem company_id: tables/... → lixeira/...
        if (str_starts_with($fileKey, 'tables/')) {
            return 'lixeira/' . substr($fileKey, strlen('tables/'));
        }

        // Fallback: preservar estrutura sob lixeira/
        return 'lixeira/' . $fileKey;
    }

    /**
     * Move um objeto do bucket R2 para a lixeira (soft delete).
     *
     * Em vez de excluir permanentemente, copia o arquivo para
     * lixeira/{caminho_original} e só então remove o original.
     * Isso previne perda irreversível de dados da empresa.
     *
     * Estrutura:
     *   Ativo:    tables/tbl_X_Y/{id}/arquivo.ext
     *   Lixeira:  lixeira/tbl_X_Y/{id}/arquivo.ext
     *
     * @param string $fileKey Chave do objeto no bucket
     * @return bool           True se movido para lixeira com sucesso
     */
    public static function delete(string $fileKey): bool
    {
        $bucket   = (string) SofDocConfigHelper::get('R2_BUCKET_NAME');
        $trashKey = self::buildTrashKey($fileKey);

        try {
            $client = self::getClient();

            // 1. Copiar para lixeira
            $client->copyObject([
                'Bucket'     => $bucket,
                'CopySource' => $bucket . '/' . $fileKey,
                'Key'        => $trashKey,
            ]);

            // 2. Remover original somente após cópia confirmada
            $client->deleteObject([
                'Bucket' => $bucket,
                'Key'    => $fileKey,
            ]);

            error_log("[UploadR2Helper] Arquivo movido para lixeira: {$fileKey} → {$trashKey}");
            return true;
        } catch (AwsException $e) {
            error_log('[UploadR2Helper] Erro ao mover para lixeira: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Faz upload da foto de perfil de um usuário para o Cloudflare R2
     *
     * Estrutura da chave: profile_images/user_{userId}.{ext}
     * O arquivo substitui qualquer imagem anterior do mesmo usuário.
     *
     * @param int   $userId    ID do usuário (id_usuario_fk)
     * @param array $file      Arquivo no formato $_FILES entry
     * @param int   $maxSizeMb Tamanho máximo permitido em MB (padrão 2)
     * @return string|null     Chave do objeto no bucket ou null em caso de erro
     */
    public static function uploadProfilePicture(int $userId, array $file, int $maxSizeMb = 2): ?string
    {
        if (!isset($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $limitBytes = $maxSizeMb * 1024 * 1024;
        if ((int)($file['size'] ?? 0) > $limitBytes) {
            error_log("[UploadR2Helper] Arquivo excede o limite de {$maxSizeMb}MB.");
            return null;
        }

        if (!FileManager::isAllowedFile($file['tmp_name'], $file['name'])) {
            error_log('[UploadR2Helper] Tipo de arquivo não permitido: ' . ($file['name'] ?? ''));
            return null;
        }

        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $companyId = self::getCompanyId();
        $fileKey   = "{$companyId}/profile_images/user_{$userId}" . ($ext !== '' ? '.' . $ext : '');

        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected) {
                $mimeType = $detected;
            }
        }

        try {
            self::getClient()->putObject([
                'Bucket'      => SofDocConfigHelper::get('R2_BUCKET_NAME'),
                'Key'         => $fileKey,
                'SourceFile'  => $file['tmp_name'],
                'ContentType' => $mimeType,
            ]);

            return $fileKey;
        } catch (AwsException $e) {
            error_log('[UploadR2Helper] Erro no upload de foto de perfil: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Remove permanentemente a foto de perfil do R2 (sem lixeira).
     *
     * @param string $fileKey Chave do objeto no bucket
     * @return bool
     */
    public static function deleteProfilePicture(string $fileKey): bool
    {
        try {
            self::getClient()->deleteObject([
                'Bucket' => SofDocConfigHelper::get('R2_BUCKET_NAME'),
                'Key'    => $fileKey,
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('[UploadR2Helper] Erro ao excluir foto de perfil: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica se um valor armazenado é uma chave R2 (não um caminho local legado)
     *
     * Padrão local: "mmYYYY/filename.ext"   (ex: "052026/arquivo.pdf")
     * Padrão R2:    "tables/tbl_X/file.ext" ou "profile_images/user_{id}.ext"
     *
     * @param string $value Valor armazenado no banco de dados
     * @return bool
     */
    public static function isR2Key(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $segments = explode('/', $value, 3);
        if (count($segments) >= 2) {
            return in_array($segments[1], ['tables', 'lixeira', 'profile_images', 'company_logo'], true);
        }

        return false;
    }

    /**
     * Gera URL pré-assinada temporária do R2 (presigned URL).
     *
     * Necessário para que servidores externos (OnlyOffice, M365) baixem
     * o arquivo diretamente do R2 sem passar pelo proxy PHP.
     *
     * @param string $fileKey          Chave do objeto no bucket
     * @param int    $expiresInMinutes Duração da assinatura (padrão 60 min)
     * @return string                  URL pré-assinada
     */
    public static function getSignedUrl(string $fileKey, int $expiresInMinutes = 60): string
    {
        $client = self::getClient();
        $bucket = (string) SofDocConfigHelper::get('R2_BUCKET_NAME');

        $cmd     = $client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key'    => $fileKey,
        ]);
        $request = $client->createPresignedRequest($cmd, "+{$expiresInMinutes} minutes");

        return (string) $request->getUri();
    }
}
