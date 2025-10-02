<?php

namespace Common\Files\Telegram;

use App\Models\TelegramUploadProgress;
use Illuminate\Support\Facades\Log;
use Common\Files\Telegram\Exceptions\TelegramException;
use Common\Files\Telegram\Exceptions\TelegramDownloadException;
use Common\Files\Telegram\Exceptions\TelegramUploadException;
use Common\Files\Telegram\TelegramUrlUploadWithProgress;

/**
 * Phase 8.4: Auto-Retry Service
 * 
 * مدیریت retry برای آپلودهای ناموفق
 */
class TelegramRetryService
{
    protected TelegramUrlUploadWithProgress $uploadService;

    /**
     * خطاهای قابل retry
     */
    protected const RETRYABLE_ERROR_PATTERNS = [
        '/timeout/i',
        '/connection/i',
        '/network/i',
        '/temporary/i',
        '/rate limit/i',
        '/too many requests/i',
        '/503/i',
        '/502/i',
        '/504/i',
    ];

    /**
     * خطاهای غیرقابل retry
     */
    protected const NON_RETRYABLE_ERROR_PATTERNS = [
        '/not found/i',
        '/404/i',
        '/unauthorized/i',
        '/forbidden/i',
        '/403/i',
        '/invalid/i',
        '/file too large/i',
        '/unsupported/i',
    ];

    public function __construct()
    {
        $this->uploadService = new TelegramUrlUploadWithProgress();
    }

    /**
     * بررسی آیا خطا قابل retry است
     */
    public function isRetryable(string $errorMessage): bool
    {
        // اول چک کنیم non-retryable نباشد
        foreach (self::NON_RETRYABLE_ERROR_PATTERNS as $pattern) {
            if (preg_match($pattern, $errorMessage)) {
                return false;
            }
        }

        // بعد چک کنیم retryable باشد
        foreach (self::RETRYABLE_ERROR_PATTERNS as $pattern) {
            if (preg_match($pattern, $errorMessage)) {
                return true;
            }
        }

        // Default: retryable (safe choice)
        return true;
    }

    /**
     * تشخیص نوع خطا از Exception
     */
    public function classifyError(\Throwable $exception): array
    {
        $isRetryable = true;
        $category = 'unknown';

        if ($exception instanceof TelegramDownloadException) {
            $category = 'download';
            $code = $exception->getCode();
            $isRetryable = !in_array($code, [404, 403, 401]); // Not found, Forbidden, Unauthorized
        } elseif ($exception instanceof TelegramUploadException) {
            $category = 'upload';
            $code = $exception->getCode();
            $isRetryable = !in_array($code, [413, 403, 401]); // Too large, Forbidden, Unauthorized
        } else {
            // Generic exception - بررسی پیغام
            $isRetryable = $this->isRetryable($exception->getMessage());
        }

        return [
            'is_retryable' => $isRetryable,
            'category' => $category,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
    }

    /**
     * Retry یک upload ناموفق
     */
    public function retryUpload(string $sessionId): array
    {
        $progress = TelegramUploadProgress::where('session_id', $sessionId)->firstOrFail();

        // بررسی امکان retry
        if (!$progress->canRetry()) {
            return [
                'success' => false,
                'message' => 'Cannot retry this upload',
                'reason' => !$progress->is_retryable 
                    ? 'Non-retryable error' 
                    : 'Max retries reached',
            ];
        }

        // افزایش شمارنده
        $progress->incrementRetry();

        Log::info('Retrying Telegram upload', [
            'session_id' => $sessionId,
            'retry_count' => $progress->retry_count,
            'url' => $progress->url,
        ]);

        try {
            // تلاش مجدد برای آپلود
            $result = $this->uploadService->uploadFromUrl(
                $progress->url,
                [
                    'user_id' => $progress->user_id,
                    'name' => $progress->filename,
                ],
                []
            );

            return [
                'success' => true,
                'message' => 'Retry successful',
                'result' => $result,
            ];

        } catch (\Exception $e) {
            // تشخیص نوع خطا
            $errorInfo = $this->classifyError($e);

            Log::error('Retry failed', [
                'session_id' => $sessionId,
                'retry_count' => $progress->retry_count,
                'error' => $errorInfo,
            ]);

            // اگر non-retryable است، علامت بزنیم
            if (!$errorInfo['is_retryable']) {
                $progress->markAsNonRetryable($e->getMessage());
                
                return [
                    'success' => false,
                    'message' => 'Non-retryable error',
                    'error' => $errorInfo,
                ];
            }

            // اگر هنوز می‌توان retry کرد، schedule کنیم
            if ($progress->fresh()->canRetry()) {
                $progress->scheduleNextRetry();
                
                return [
                    'success' => false,
                    'message' => 'Retry scheduled',
                    'next_retry_at' => $progress->next_retry_at,
                    'retry_count' => $progress->retry_count,
                ];
            }

            // Max retries رسیدیم
            $progress->markAsFailed('Max retries reached: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Max retries reached',
                'error' => $errorInfo,
            ];
        }
    }

    /**
     * پردازش موارد آماده برای retry
     */
    public function processRetryQueue(int $limit = 10): array
    {
        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'scheduled' => 0,
        ];

        $uploads = TelegramUploadProgress::readyForRetry()
            ->orderBy('next_retry_at')
            ->limit($limit)
            ->get();

        foreach ($uploads as $progress) {
            $results['processed']++;

            $retryResult = $this->retryUpload($progress->session_id);

            if ($retryResult['success']) {
                $results['succeeded']++;
            } elseif (isset($retryResult['next_retry_at'])) {
                $results['scheduled']++;
            } else {
                $results['failed']++;
            }
        }

        Log::info('Retry queue processed', $results);

        return $results;
    }

    /**
     * دریافت آمار retry
     */
    public function getRetryStatistics(): array
    {
        return [
            'pending_retries' => TelegramUploadProgress::readyForRetry()->count(),
            'total_retries' => TelegramUploadProgress::where('retry_count', '>', 0)->count(),
            'non_retryable' => TelegramUploadProgress::where('is_retryable', false)->count(),
            'max_retries_reached' => TelegramUploadProgress::whereRaw('retry_count >= max_retries')->count(),
        ];
    }

    /**
     * لغو retry برای یک session
     */
    public function cancelRetry(string $sessionId): bool
    {
        $progress = TelegramUploadProgress::where('session_id', $sessionId)->first();

        if (!$progress) {
            return false;
        }

        $progress->update([
            'is_retryable' => false,
            'next_retry_at' => null,
        ]);

        return true;
    }
}
