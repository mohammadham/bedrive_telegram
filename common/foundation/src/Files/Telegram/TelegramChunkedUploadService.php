<?php

namespace Common\Files\Telegram;

use App\Models\TelegramUploadSession;
use App\Models\TelegramUploadProgress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 8.3: Chunked Upload Service
 * 
 * سرویس برای download و upload با قابلیت resume
 */
class TelegramChunkedUploadService
{
    protected TelegramUrlUploadService $uploadService;
    protected TelegramUploadProgressService $progressService;
    
    /**
     * Default chunk size: 5MB
     */
    protected const DEFAULT_CHUNK_SIZE = 5 * 1024 * 1024;

    /**
     * Max retry attempts per chunk
     */
    protected const MAX_CHUNK_RETRIES = 3;

    public function __construct()
    {
        $this->uploadService = new TelegramUrlUploadService();
        $this->progressService = new TelegramUploadProgressService();
    }

    /**
     * شروع یک آپلود جدید با chunking
     */
    public function startChunkedUpload(
        string $url,
        array $fileData = [],
        int $chunkSize = self::DEFAULT_CHUNK_SIZE
    ): TelegramUploadSession {
        $userId = $fileData['user_id'] ?? auth()->id();
        $filename = $fileData['name'] ?? $this->extractFilename($url);

        // ایجاد progress tracker
        $progress = $this->progressService->createSession($userId, $url, $filename, null);

        // ایجاد upload session
        $session = TelegramUploadSession::create([
            'session_id' => TelegramUploadSession::generateSessionId(),
            'user_id' => $userId,
            'progress_id' => $progress->id,
            'url' => $url,
            'filename' => $filename,
            'chunk_size' => $chunkSize,
            'status' => 'initialized',
        ]);

        // دریافت حجم فایل
        try {
            $totalSize = $this->getRemoteFileSize($url);
            $session->initialize($totalSize, $chunkSize);
            
            Log::info('Chunked upload session initialized', [
                'session_id' => $session->session_id,
                'total_size' => $totalSize,
                'total_chunks' => $session->total_chunks,
            ]);

        } catch (\Exception $e) {
            $session->markAsFailed('Failed to get file size: ' . $e->getMessage());
            throw $e;
        }

        return $session;
    }

    /**
     * Resume یک session
     */
    public function resumeSession(string $sessionId): array
    {
        $session = TelegramUploadSession::where('session_id', $sessionId)->firstOrFail();

        if (!$session->canResume()) {
            return [
                'success' => false,
                'message' => 'Session cannot be resumed',
                'reason' => !$session->is_resumable 
                    ? 'Non-resumable session' 
                    : 'Already completed or in wrong state',
            ];
        }

        try {
            // Resume download اگر ناتمام است
            if (!$session->isDownloadComplete()) {
                $session->resume();
                $this->downloadChunks($session);
            }

            // اگر download کامل شد، شروع upload
            if ($session->isDownloadComplete() && !$session->isCompleted()) {
                $this->uploadToTelegram($session);
            }

            return [
                'success' => true,
                'message' => 'Session resumed successfully',
                'session' => $session,
            ];

        } catch (\Exception $e) {
            $session->markAsFailed($e->getMessage());
            
            Log::error('Session resume failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Download chunks
     */
    protected function downloadChunks(TelegramUploadSession $session): void
    {
        $session->startDownload();

        // ایجاد یا باز کردن temp file
        $tempPath = $session->temp_path ?? sys_get_temp_dir() . '/' . $session->session_id . '_' . $session->filename;
        $session->update(['temp_path' => $tempPath]);

        $fp = fopen($tempPath, 'c+'); // Create or open for read/write

        while (($nextChunk = $session->getNextChunkIndex()) !== null) {
            // چک کردن اگر paused شده
            $session->refresh();
            if ($session->isPaused()) {
                fclose($fp);
                return;
            }

            try {
                $this->downloadChunk($session, $nextChunk, $fp);
            } catch (\Exception $e) {
                fclose($fp);
                throw $e;
            }
        }

        fclose($fp);
        $session->markDownloadCompleted();
    }

    /**
     * Download یک chunk
     */
    protected function downloadChunk(TelegramUploadSession $session, int $chunkIndex, $fileHandle): void
    {
        $range = $session->getChunkRange($chunkIndex);
        $retries = 0;

        while ($retries < self::MAX_CHUNK_RETRIES) {
            try {
                $response = Http::timeout(60)
                    ->withHeaders([
                        'Range' => "bytes={$range['start']}-{$range['end']}",
                    ])
                    ->get($session->url);

                if ($response->successful() || $response->status() === 206) { // 206 = Partial Content
                    // نوشتن chunk در موقعیت صحیح
                    fseek($fileHandle, $range['start']);
                    fwrite($fileHandle, $response->body());

                    // علامت‌گذاری به عنوان completed
                    $session->markChunkCompleted($chunkIndex, $range['size']);

                    // Update progress
                    $this->progressService->updateDownloadProgress(
                        $session->progress->session_id,
                        $session->downloaded_bytes,
                        null, // speed
                        null  // eta
                    );

                    Log::debug('Chunk downloaded', [
                        'session_id' => $session->session_id,
                        'chunk' => $chunkIndex,
                        'progress' => $session->download_percentage . '%',
                    ]);

                    return; // موفق شد
                }

                throw new \Exception("HTTP {$response->status()}");

            } catch (\Exception $e) {
                $retries++;
                
                if ($retries >= self::MAX_CHUNK_RETRIES) {
                    throw new \Exception("Failed to download chunk {$chunkIndex} after {$retries} attempts: " . $e->getMessage());
                }

                // Wait before retry (exponential backoff)
                sleep(pow(2, $retries));
            }
        }
    }

    /**
     * Upload فایل کامل شده به تلگرام
     */
    protected function uploadToTelegram(TelegramUploadSession $session): void
    {
        $session->startUpload();

        $this->progressService->startUpload($session->progress->session_id);

        try {
            $result = $this->uploadService->uploadFile(
                $session->temp_path,
                [
                    'user_id' => $session->user_id,
                    'name' => $session->filename,
                ]
            );

            $session->markAsCompleted($result['metadata']->telegram_file_id);

            $this->progressService->markAsCompleted(
                $session->progress->session_id,
                $result['file_entry']->id ?? null
            );

            // پاکسازی temp file
            $session->cleanupTempFile();

            Log::info('Chunked upload completed', [
                'session_id' => $session->session_id,
                'file_entry_id' => $result['file_entry']->id ?? null,
            ]);

        } catch (\Exception $e) {
            $session->markAsFailed('Upload to Telegram failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Pause یک session
     */
    public function pauseSession(string $sessionId): bool
    {
        $session = TelegramUploadSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return false;
        }

        if (in_array($session->status, ['downloading', 'uploading'])) {
            $session->pause();
            return true;
        }

        return false;
    }

    /**
     * Cancel یک session
     */
    public function cancelSession(string $sessionId): bool
    {
        $session = TelegramUploadSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return false;
        }

        $session->markAsCancelled();
        $session->cleanupTempFile();

        // Cancel هم progress
        if ($session->progress) {
            $session->progress->markAsCancelled();
        }

        return true;
    }

    /**
     * دریافت حجم فایل remote
     */
    protected function getRemoteFileSize(string $url): int
    {
        $response = Http::head($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to get file size');
        }

        $size = $response->header('Content-Length');

        if (!$size) {
            throw new \Exception('Server does not provide Content-Length');
        }

        return (int) $size;
    }

    /**
     * استخراج نام فایل از URL
     */
    protected function extractFilename(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path);
        
        if (empty($filename) || strpos($filename, '.') === false) {
            return 'file_' . time();
        }

        return $filename;
    }

    /**
     * دریافت لیست sessions برای user
     */
    public function getUserSessions(int $userId, bool $resumableOnly = false): array
    {
        $query = TelegramUploadSession::where('user_id', $userId);

        if ($resumableOnly) {
            $query->resumable();
        }

        return $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * دریافت آمار sessions
     */
    public function getSessionStatistics(int $userId): array
    {
        return [
            'total_sessions' => TelegramUploadSession::where('user_id', $userId)->count(),
            'resumable' => TelegramUploadSession::where('user_id', $userId)->resumable()->count(),
            'completed' => TelegramUploadSession::where('user_id', $userId)->where('status', 'completed')->count(),
            'paused' => TelegramUploadSession::where('user_id', $userId)->where('status', 'paused')->count(),
            'failed' => TelegramUploadSession::where('user_id', $userId)->where('status', 'failed')->count(),
        ];
    }
}
