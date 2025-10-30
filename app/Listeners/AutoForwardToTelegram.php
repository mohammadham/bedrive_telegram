<?php

namespace App\Listeners;

use Common\Files\Events\FileUploaded;
use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramTargetDetector;
use Common\Files\Telegram\Exceptions\TelegramException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Common\Files\Telegram\TelegramCaptionParser;

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
        // Don't initialize TelegramFileManager here!
        // It will be created lazily when needed
    }
    /**
     * Check if Telegram driver is enabled
     */
    protected function isTelegramDriverEnabled(): bool
    {
        // Check if telegram is set as uploads or public disk driver
        $uploadsDriver = config('common.site.uploads_disk_driver');
        $publicDriver = config('common.site.public_disk_driver');
        
        return $uploadsDriver === 'telegram' || $publicDriver === 'telegram';
    }
    /**
     * Handle the event.
     */
    public function handle(FileUploaded $event): void
    {
        $fileEntry = $event->fileEntry;
        
        // Check if the file is stored on Telegram disk
        if (! $this->isTelegramDriverEnabled()) {
            return;
        }
        
        // بارگذاری relations لازم - telegramMetadata حالا در Common\Files\FileEntry هم هست
        try {
            $fileEntry->load(['owner', 'telegramMetadata']);
        } catch (\Exception $e) {
            Log::error('Auto-forward: Failed to load relations', [
                'file_entry_id' => $fileEntry->id,
                'error' => $e->getMessage(),
            ]);
            return;
        }
        
        // دریافت owner (user)
        $user = $fileEntry->owner;
        if (!$user) {
            Log::debug('Auto-forward skipped: no owner', [
                'file_entry_id' => $fileEntry->id,
            ]);
            return;
        }
        
        Log::debug('Auto-forward: Checking user settings', [
            'file_entry_id' => $fileEntry->id,
            'owner_id' => $user->id,
            'has_auto_forward' => $user->hasTelegramAutoForward(),
        ]);

        // چک کنیم که auto-forward فعال است
        if (!$user->hasTelegramAutoForward()) {
            return;
        }

        $targetId = $user->getTelegramForwardTarget();
        if (empty($targetId)) {
            return;
        }

        // چک کنیم که فایل در تلگرام آپلود شده و metadata موجود است
        $metadata = $fileEntry->telegramMetadata;
        if (!$metadata) {
            // اولین بار که FileUploaded dispatch می‌شود، metadata هنوز link نشده
            // دفعه دوم که از LinkTelegramMetadataToFileEntry dispatch می‌شود، metadata موجود است
            Log::debug('Auto-forward skipped: metadata not yet linked', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
            ]);
            return;
        }
        
        if (!$metadata->isUploadCompleted()) {
            Log::warning('Auto-forward skipped: upload not completed', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
                'metadata_status' => $metadata->upload_status,
            ]);
            return;
        }

try {
            // 🔧 CRITICAL FIX: در BeDrive، تنظیمات server در .env ذخیره می‌شوند
            // و queue worker .env را cache می‌کند! پس باید fresh بخوانیم
            $envSettings = (new \Common\Settings\DotEnvEditor())->load();
            
            $config = [
                'bot_token' => $envSettings['storage_telegram_bot_token'] ?? null,
                'channel_id' => $envSettings['storage_telegram_channel_id'] ?? null,
                'api_id' => $envSettings['storage_telegram_api_id'] ?? null,
                'api_hash' => $envSettings['storage_telegram_api_hash'] ?? null,
                'phone' => $envSettings['storage_telegram_phone'] ?? null,
            ];
            
            Log::debug('Auto-forward: Loading Telegram config from .env (fresh)', [
                'has_bot_token' => !empty($config['bot_token']),
                'has_channel_id' => !empty($config['channel_id']),
                'has_api_id' => !empty($config['api_id']),
                'has_phone' => !empty($config['phone']),
                'env_keys_found' => count(array_filter($config)),
            ]);
            // Initialize TelegramFileManager با channel_id از metadata
            // Constructor signature: __construct(?string $channelId, array $config)
            $telegramManager = new TelegramFileManager($metadata->channel_id, $config);
            
            // 🔍 SMART TARGET DETECTION:
            // تشخیص نوع target و انتخاب روش مناسب forward
            $targetDetection = TelegramTargetDetector::detectTarget($targetId);
            $forwardMethod = $targetDetection['method']; // 'bot' or 'user'
            
            Log::info('Auto-forward: Processing forward', [
                'file_id' => $fileEntry->id,
                'user_id' => $user->id,
                'target_id' => $targetId,
                'source_channel_id' => $metadata->channel_id,
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

            // 🎨 Parse caption template از settings
            $captionTemplate = $envSettings['telegram_forward_caption_template'] ?? null;
            $caption = null;
            if (!empty($captionTemplate)) {
                $caption = TelegramCaptionParser::parse($captionTemplate, $fileEntry);
                Log::debug('Auto-forward: Caption parsed', [
                    'template' => $captionTemplate,
                    'caption' => $caption,
                ]);
            }

            // Forward message با caption
            $result = $client->forwardMessage(
                $metadata->channel_id,
                $metadata->message_id,
                $targetDetection['target_normalized'] ?? $targetId,
                $caption
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
