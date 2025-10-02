<?php

namespace App\Jobs;

use App\Models\User;
use Common\Files\Telegram\TelegramUrlUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bulk URL Upload Job
 * 
 * Processes bulk URL uploads asynchronously
 */
class TelegramBulkUrlUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $urls;
    public int $userId;
    public array $options;
    public string $jobId;

    /**
     * Create a new job instance
     */
    public function __construct(array $urls, int $userId, array $options = [], ?string $jobId = null)
    {
        $this->urls = $urls;
        $this->userId = $userId;
        $this->options = $options;
        $this->jobId = $jobId ?? uniqid('bulk_upload_', true);
    }

    /**
     * Execute the job
     */
    public function handle(TelegramUrlUploadService $uploadService): void
    {
        Log::info('Bulk URL upload job started', [
            'job_id' => $this->jobId,
            'user_id' => $this->userId,
            'total_urls' => count($this->urls),
        ]);

        $results = $uploadService->uploadBulkUrls(
            $this->urls,
            [
                'user_id' => $this->userId,
                'owner_id' => $this->userId,
            ],
            $this->options
        );

        // Store results in cache for retrieval
        cache()->put(
            "telegram_bulk_upload:{$this->jobId}",
            $results,
            now()->addHours(24)
        );

        Log::info('Bulk URL upload job completed', [
            'job_id' => $this->jobId,
            'successful' => $results['successful'],
            'failed' => $results['failed'],
        ]);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk URL upload job failed', [
            'job_id' => $this->jobId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);

        // Store error in cache
        cache()->put(
            "telegram_bulk_upload:{$this->jobId}",
            [
                'error' => true,
                'message' => $exception->getMessage(),
                'total' => count($this->urls),
                'successful' => 0,
                'failed' => count($this->urls),
            ],
            now()->addHours(24)
        );
    }
}