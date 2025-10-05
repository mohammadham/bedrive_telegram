<?php

namespace App\Listeners;

use App\Models\FileEntry;
use Common\Files\Events\FileUploaded;
use Common\Files\Telegram\TelegramFileManager;
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
    }

    /**
     * Handle the event.
     */
    public function handle(FileUploaded $event): void
    {
        /** @var FileEntry $fileEntry */
        $fileEntry = $event->fileEntry;

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
            
            // Forward message به target
            $client = $metadata->isUploadedViaBot()
                ? $telegramManager->getBotClient()
                : $telegramManager->getUserClient();
            // // Forward message به target
            // $client = $metadata->isUploadedViaBot()
            //     ? $this->telegramManager->getBotClient()
            //     : $this->telegramManager->getUserClient();

            $result = $client->forwardMessage(
                $metadata->channel_id,
                $metadata->message_id,
                $targetId
            );

            Log::info('File auto-forwarded successfully', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
                'target_id' => $targetId,
                'message_id' => $metadata->message_id,
            ]);

        } catch (TelegramException $e) {
            // Silent fail - فقط log کن
            Log::warning('Auto-forward failed', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
                'target_id' => $targetId,
                'error' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
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
