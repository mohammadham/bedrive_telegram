<?php

namespace Common\Files\Telegram;

use App\Models\FileEntry;
use Common\Files\Telegram\Exceptions\TelegramUploadException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Telegram URL Upload Service
 * 
 * Uploads files directly from URLs to Telegram using streaming
 * without storing them on local disk
 */
class TelegramUrlUploadService
{
    protected TelegramFileManager $manager;
    protected int $chunkSize = 8192; // 8KB chunks for streaming

    public function __construct()
    {
        $this->manager = app(TelegramFileManager::class);
    }

    /**
     * Upload file from URL to Telegram using streaming
     *
     * @param string $url
     * @param array $metadata File metadata (name, user_id, etc.)
     * @param array $options Upload options (caption, etc.)
     * @return array
     * @throws TelegramUploadException
     */
    public function uploadFromUrl(
        string $url,
        array $metadata = [],
        array $options = []
    ): array {
        $tempPath = null;

        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw TelegramUploadException::invalidFile('Invalid URL format');
            }

            Log::info('Starting URL upload to Telegram', [
                'url' => $url,
                'user_id' => $metadata['user_id'] ?? null,
            ]);

            // Download file to temp location using streaming
            $tempPath = $this->downloadUrlToTemp($url);

            // Get file info
            $fileSize = filesize($tempPath);
            $mimeType = mime_content_type($tempPath) ?: 'application/octet-stream';
            $fileName = $this->extractFileName($url, $mimeType);

            // Check if file can be uploaded
            $canUpload = TelegramFileManager::canUpload($fileSize);
            if (!$canUpload['can_upload']) {
                throw TelegramUploadException::fileTooLarge(
                    $fileSize,
                    2 * 1024 * 1024 * 1024 // 2GB
                );
            }

            // Upload to Telegram
            $uploadResult = $this->manager->uploadFile($tempPath, null, array_merge($options, [
                'filename' => $fileName,
            ]));

            // Create FileEntry if metadata provided
            $fileEntry = null;
            if (!empty($metadata)) {
                $fileEntry = $this->createFileEntry($uploadResult, $metadata, $fileName, $fileSize, $mimeType);
            }

            Log::info('URL upload completed', [
                'url' => $url,
                'file_entry_id' => $fileEntry?->id,
                'upload_method' => $uploadResult['upload_method'],
                'message_id' => $uploadResult['message_id'],
            ]);

            return [
                'success' => true,
                'file_entry' => $fileEntry,
                'upload_result' => $uploadResult,
                'metadata' => $fileEntry?->telegramMetadata,
            ];

        } catch (\Exception $e) {
            Log::error('URL upload failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            // Clean up temp file
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * Upload multiple files from URLs
     *
     * @param array $urls List of URLs
     * @param array $metadata Common metadata for all files
     * @param array $options Common upload options
     * @return array Results with success/failure for each URL
     */
    public function uploadBulkUrls(
        array $urls,
        array $metadata = [],
        array $options = []
    ): array {
        $results = [];
        
        foreach ($urls as $index => $url) {
            try {
                $result = $this->uploadFromUrl($url, $metadata, $options);
                $results[] = [
                    'url' => $url,
                    'success' => true,
                    'file_entry_id' => $result['file_entry']?->id,
                    'message' => 'Uploaded successfully',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'url' => $url,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                
                // Continue with next URL even if one fails
                Log::warning('Bulk upload - URL failed', [
                    'url' => $url,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total' => count($urls),
            'successful' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results,
        ];
    }

    /**
     * Download file from URL to temporary location using streaming
     *
     * @param string $url
     * @return string Temporary file path
     * @throws TelegramUploadException
     */
    protected function downloadUrlToTemp(string $url): string
    {
        $tempDir = storage_path('app/telegram/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = tempnam($tempDir, 'url_upload_');

        try {
            // Initialize cURL for streaming download
            $ch = curl_init($url);
            $fp = fopen($tempPath, 'wb');

            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 300, // 5 minutes timeout
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_SSL_VERIFYPEER => false, // برای سازگاری بهتر
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_BUFFERSIZE => $this->chunkSize,
            ]);

            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlErrno = curl_errno($ch);

            curl_close($ch);
            fclose($fp);

            if (!$success || ($httpCode < 200 || $httpCode >= 300)) {
                @unlink($tempPath);
                throw new \Exception("Failed to download file. HTTP Code: {$httpCode}. cURL Error: {$error} (errno: {$curlErrno})");
            }

            // Verify file was downloaded
            if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                @unlink($tempPath);
                throw new \Exception('Downloaded file is empty or does not exist');
            }

            return $tempPath;

        } catch (\Exception $e) {
            if (isset($fp) && is_resource($fp)) {
                fclose($fp);
            }
            @unlink($tempPath);
            
            throw TelegramUploadException::uploadFailed(
                'Failed to download file from URL: ' . $e->getMessage(),
                ['url' => $url]
            );
        }
    }

    /**
     * Extract filename from URL or generate one
     *
     * @param string $url
     * @param string $mimeType
     * @return string
     */
    protected function extractFileName(string $url, string $mimeType): string
    {
        // Try to get filename from URL
        $path = parse_url($url, PHP_URL_PATH);
        $basename = $path ? basename($path) : '';
        
        // If we have a valid filename with extension
        if ($basename && pathinfo($basename, PATHINFO_EXTENSION)) {
            return $basename;
        }

        // Generate filename based on mime type
        $extension = $this->getExtensionFromMimeType($mimeType);
        return 'file_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    /**
     * Get file extension from MIME type
     *
     * @param string $mimeType
     * @return string
     */
    protected function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'text/plain' => 'txt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        return $mimeMap[$mimeType] ?? 'bin';
    }

    /**
     * Create FileEntry record
     *
     * @param array $uploadResult
     * @param array $metadata
     * @param string $fileName
     * @param int $fileSize
     * @param string $mimeType
     * @return FileEntry
     */
    protected function createFileEntry(
        array $uploadResult,
        array $metadata,
        string $fileName,
        int $fileSize,
        string $mimeType
    ): FileEntry {
        // Create FileEntry
        $fileEntry = FileEntry::create([
            'name' => $metadata['name'] ?? $fileName,
            'file_name' => $fileName,
            'mime' => $mimeType,
            'file_size' => $fileSize,
            'user_id' => $metadata['user_id'] ?? null,
            'owner_id' => $metadata['owner_id'] ?? $metadata['user_id'] ?? null,
            'disk_prefix' => 'telegram',
            'type' => $this->determineFileType($mimeType),
            'path' => 'telegram/' . $fileName,
        ]);

        // Create Telegram metadata
        $telegramMetadata = TelegramMetadataHelper::createMetadata($fileEntry, [
            'file_id' => $uploadResult['file_id'],
            'message_id' => $uploadResult['message_id'],
            'channel_id' => $uploadResult['channel_id'],
            'upload_method' => $uploadResult['upload_method'],
            'upload_status' => 'completed',
            'uploaded_at' => now(),
        ]);

        return $fileEntry->load('telegramMetadata');
    }

    /**
     * Determine file type from MIME type
     *
     * @param string $mimeType
     * @return string
     */
    protected function determineFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mimeType, 'text/')) {
            return 'text';
        }
        return 'file';
    }

    /**
     * Set chunk size for streaming
     *
     * @param int $size
     */
    public function setChunkSize(int $size): void
    {
        $this->chunkSize = $size;
    }
}