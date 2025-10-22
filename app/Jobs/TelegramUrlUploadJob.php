<?php

namespace App\Jobs;

use Common\Files\Telegram\TelegramUrlUploadWithProgress;
use Common\Files\Telegram\TelegramUploadProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class TelegramUrlUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 🔧 Timeout برای فایل‌های بزرگ (2 ساعت)
     */
    public $timeout = 7200; // 2 hours

    /**
     * ❌ فقط یک تلاش (retry باید دستی از UI باشد)
     */
    public $tries = 1;

    /**
     * ❌ عدم fail کردن خودکار در timeout
     */
    public $failOnTimeout = false;

    /**
     * Job properties
     */
    public string $url;
    public array $fileOptions;
    public array $uploadOptions;
    public string $sessionId;

    /**
     * 🆔 تعریف Unique ID برای Job
     */
    public function uniqueId(): string
    {
        return 'telegram-upload-' . $this->sessionId;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $url,
        array $fileOptions,
        array $uploadOptions,
        string $sessionId
    ) {
        $this->url = $url;
        $this->fileOptions = $fileOptions;
        $this->uploadOptions = $uploadOptions;
        $this->sessionId = $sessionId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('TelegramUrlUploadJob handle() STARTED', [
            'session_id' => $this->sessionId,
            'url' => $this->url,
            'pid' => getmypid(),
        ]);

        $progressService = new TelegramUploadProgressService();
        $uploadService = new TelegramUrlUploadWithProgress();

        try {
            // ✅ بررسی اینکه آیا Job لغو شده است یا نه
            $progress = $progressService->getProgress($this->sessionId);
            
            if (!$progress) {
                Log::error('Progress session not found', ['session_id' => $this->sessionId]);
                return;
            }

            if ($progress->isCancelled()) {
                Log::info('Job cancelled before start', ['session_id' => $this->sessionId]);
                return;
            }

            // شروع آپلود با session موجود
            Log::info('TelegramUrlUploadJob started', [
                'session_id' => $this->sessionId,
                'url' => $this->url,
            ]);
            
            $result = $uploadService->uploadFromUrl(
                $this->url,
                $this->fileOptions,
                $this->uploadOptions,
                $this->sessionId
            );

            // ✅ بررسی مجدد cancel بعد از تکمیل
            $progress->refresh();
            if ($progress->isCancelled()) {
                Log::info('Job cancelled after completion', ['session_id' => $this->sessionId]);
                return;
            }

            Log::info('TelegramUrlUploadJob completed', [
                'session_id' => $this->sessionId,
                'file_entry_id' => $result['file_entry']->id ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('TelegramUrlUploadJob failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // علامت‌گذاری به عنوان failed در progress
            try {
                $progressService->markAsFailed($this->sessionId, $e->getMessage());
            } catch (\Exception $progressError) {
                Log::error('Failed to update progress on job failure', [
                    'session_id' => $this->sessionId,
                    'progress_error' => $progressError->getMessage(),
                ]);
            }

            // Re-throw برای Laravel queue failure handling
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('TelegramUrlUploadJob permanently failed', [
            'session_id' => $this->sessionId,
            'url' => $this->url,
            'error' => $exception->getMessage(),
        ]);

        // به‌روزرسانی وضعیت progress به failed
        try {
            $progressService = new TelegramUploadProgressService();
            $progressService->markAsFailed(
                $this->sessionId, 
                "Job failed: " . $exception->getMessage()
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark progress as failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
