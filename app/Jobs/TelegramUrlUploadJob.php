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

    public $timeout = 600; // 10 minutes
    public $tries = 1; // Retry handled by TelegramRetryService

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $sessionId,
        public string $url,
        public string $filename,
        public int $userId
    ) {
        // Queue را مشخص می‌کنیم
        $this->onQueue('telegram-uploads');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('TelegramUrlUploadJob started', [
            'session_id' => $this->sessionId,
            'url' => $this->url,
            'user_id' => $this->userId,
        ]);

        try {
            $service = new TelegramUrlUploadWithProgress();
            
            $result = $service->uploadFromUrl(
                $this->url,
                $this->filename,
                $this->userId,
                $this->sessionId
            );

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
    }
}
