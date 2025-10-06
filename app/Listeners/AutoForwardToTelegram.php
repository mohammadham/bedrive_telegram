<?php

namespace App\Listeners;

use App\Models\FileEntry;
use Common\Files\Events\FileUploaded;
use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramTargetDetector;
use Common\Files\Telegram\Exceptions\TelegramException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Auto-forward uploaded files to Telegram if user has enabled this feature
 */
class AutoForwardToTelegram implements ShouldQueue
{
    use InteractsWithQueue;

    // protected TelegramFileManager $telegramManager;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        // $this->telegramManager = new TelegramFileManager();
           // Don't initialize TelegramFileManager here!
        // It will be created lazily when needed
        Log::info('Auto-forward: Target detected');
    }

    /**
     * Handle the event.
     */
    public function handle(FileUploaded $event): void
    {
        /** @var FileEntry $fileEntry */
        $fileEntry = $event->fileEntry;
        Log::info('Auto-forward: Target detecteda');
        // چک کنیم که driver تلگرام فعال است
        if (config('common.site.uploads_disk') !== 'telegram') {
            return;
        }

        // دریافت user
        $user = $fileEntry->user;
        if (!$user) {
            return;
        }

        // چک کنیم که auto-forward فعال است
        if (!$user->hasTelegramAutoForward()) {
            return;
        }

        $targetId = $user->getTelegramForwardTarget();
        if (empty($targetId)) {
            return;
        }

        // چک کنیم که فایل در تلگرام آپلود شده
        $metadata = $fileEntry->telegramMetadata;
        if (!$metadata || !$metadata->isUploadCompleted()) {
            Log::warning('Auto-forward skipped: file not in Telegram', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        try {
            // Initialize TelegramFileManager only when needed (lazy initialization)
            // At this point, config is already loaded from .env
            $telegramManager = app(TelegramFileManager::class);
            
            // 🔍 SMART TARGET DETECTION:
            // تشخیص نوع target و انتخاب روش مناسب forward
            $targetDetection = TelegramTargetDetector::detectTarget($targetId);
            $forwardMethod = $targetDetection['method']; // 'bot' or 'user'
            
            Log::info('Auto-forward: Target detected', [
                'target_id' => $targetId,
                'detected_type' => $targetDetection['type'],
                'forward_method' => $forwardMethod,
                'reason' => $targetDetection['reason'],
                'original_upload_method' => $metadata->upload_method,
            ]);
            
            // انتخاب client مناسب بر اساس نوع target
            // ✅ کانال/گروه → Bot Client
            // ✅ User Account → User Client
            $client = ($forwardMethod === 'bot')
                ? $telegramManager->getBotClient()
                : $telegramManager->getUserClient();
            
            Log::info('Auto-forward: Using client', [
                'client_type' => $client->getClientType(),
                'target_type' => $targetDetection['type'],
            ]);

            // Forward message
            $result = $client->forwardMessage(
                $metadata->channel_id,
                $metadata->message_id,
                $targetDetection['target_normalized'] ?? $targetId
            );

            Log::info('File auto-forwarded successfully', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
                'target_id' => $targetId,
                'target_type' => $targetDetection['type'],
                'forward_method' => $forwardMethod,
                'message_id' => $metadata->message_id,
                'new_message_id' => $result['message_id'] ?? null,
            ]);

        } catch (TelegramException $e) {
            // Silent fail - فقط log کن
            Log::warning('Auto-forward failed', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
                'target_id' => $targetId,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            // هر خطای دیگری
            Log::error('Auto-forward exception', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(FileUploaded $event, \Throwable $exception): void
    {
        Log::error('Auto-forward job failed', [
            'file_id' => $event->fileEntry->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
