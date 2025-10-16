<?php

namespace App\Jobs;

use Common\Files\Telegram\TelegramUrlUploadWithProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TelegramUrlUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 🔧 Timeout برای فایل‌های بزرگ (2 ساعت)
     * برای فایل 2GB با سرعت 1MB/s نیاز به ~35 دقیقه
     */
    public $timeout = 7200; // 2 hours for large files

    /**
     * 🔄 تعداد تلاش: 3 بار (برای مشکلات شبکه)
     * Retry اضافی توسط TelegramRetryService هم مدیریت می‌شود
     */
    public $tries = 3;

    /**
     * ⏱️ زمان انتظار بین تلاش‌های مجدد (Exponential backoff)
     * [30s, 2min, 5min]
     */
    public $backoff = [30, 120, 300];

    /**
     * ❌ عدم fail کردن خودکار در timeout
     * (برای اینکه failed() فراخوانی شود و cleanup انجام شود)
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
     * 🆔 تعریف Unique ID برای Job (برای جلوگیری از duplicate UUID error)
     * این ID برای failed_jobs table استفاده می‌شود
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
        // ⚠️ نکته: onQueue() باید در dispatch فراخوانی شود نه constructor
        // در TelegramUrlUploadController این کار انجام شده است
        // // Queue را مشخص می‌کنیم
        // $this->onQueue('telegram-uploads');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('TelegramUrlUploadJob handle() CALLED', [
            'session_id' => $this->sessionId,
            'url' => $this->url,
            'pid' => getmypid(),
      'memory_usage' => memory_get_usage(true),
    ]);

        try {

            // استفاده از سرویس با progress tracking
            $uploadService = new TelegramUrlUploadWithProgress();
                        Log::info('TelegramUrlUploadJob started', [
                'session_id' => $this->sessionId,
                'url' => $this->url,
            ]);
            // آپلود فایل با session موجود
            $result = $uploadService->uploadFromUrl(
                $this->url,
                $this->fileOptions,
                $this->uploadOptions,
                $this->sessionId
            );

            Log::info('TelegramUrlUploadJob completed', [
                'session_id' => $this->sessionId,
                'file_entry_id' => $result['file_entry']->id ?? null,
            'result_keys' => array_keys($result),
        ]);
        } catch (\Exception $e) {
            Log::error('TelegramUrlUploadJob failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        // علامت‌گذاری به عنوان failed در progress
        try {
            $progressService = new \Common\Files\Telegram\TelegramUploadProgressService();
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
     * 
     * این متد وقتی فراخوانی می‌شود که Job پس از تمام تلاش‌ها fail شود
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('TelegramUrlUploadJob permanently failed', [
            'session_id' => $this->sessionId,
            'url' => $this->url,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
        ]);

        // 🔄 به‌روزرسانی وضعیت progress به failed
        try {
            $progressService = new \Common\Files\Telegram\TelegramUploadProgressService();
            $progressService->markAsFailed(
                $this->sessionId, 
                "Job permanently failed after {$this->tries} attempts: " . $exception->getMessage()
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark progress as failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
