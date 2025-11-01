<?php

namespace App\Http\Controllers;
use Exception;
use App\Models\FileEntry;
use App\Models\User;
use Common\Core\BaseController;
use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramStorageService;
use Common\Files\Telegram\TelegramTargetDetector;
use Common\Files\Telegram\TelegramCaptionParser;
use Common\Files\Telegram\Exceptions\TelegramException;
use Common\Settings\DotEnvEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserTelegramSettingsController extends BaseController
{
    protected ?TelegramFileManager $telegramManager = null;
    protected ?TelegramStorageService $storageService = null;

    public function __construct()
    {
        $this->middleware('auth');
        // تأخیر در initialization - فقط وقتی نیاز باشد ایجاد می‌شوند
    }

    /**
     * Get or initialize TelegramFileManager lazily
     */
    protected function getTelegramManager(?string $channelId = null): TelegramFileManager
    {
        if (!$this->telegramManager) {
            // اگر channelId داده نشده، از user's forward_target استفاده کن
            // یا از channel_id اصلی admin
            $user = auth()->user();
                        // 🔧 CRITICAL FIX: در BeDrive، تنظیمات در .env ذخیره می‌شوند
            // باید fresh بخوانیم (مثل AutoForwardToTelegram)
              $envSettings = (new DotEnvEditor())->load();
            
            $effectiveChannelId = $channelId 
                ?? ($user->telegram_forward_target ?: null)
                ?? ($envSettings['storage_telegram_channel_id'] ?? null)
                ?? config('services.telegram.channel_id');
            
            $config = [
                'bot_token' => $envSettings['storage_telegram_bot_token'] ?? config('services.telegram.bot_token') ?? null,
                'api_id' => $envSettings['storage_telegram_api_id'] ?? config('services.telegram.api_id') ?? null,
                'api_hash' => $envSettings['storage_telegram_api_hash'] ?? config('services.telegram.api_hash') ?? null,
                'phone' => $envSettings['storage_telegram_phone'] ?? config('services.telegram.phone') ?? null,
            ];
            Log::info('Manual forward: Loading Telegram config from .env (fresh)', [
                'has_bot_token' => !empty($config['bot_token']),
                'has_channel_id' => !empty($effectiveChannelId),
                'has_api_id' => !empty($config['api_id']),
                'has_phone' => !empty($config['phone']),
            ]);
            $this->telegramManager = new TelegramFileManager($effectiveChannelId, $config);
        }
        return $this->telegramManager;
    }

    /**
     * Get or initialize TelegramStorageService lazily
     */
    protected function getStorageService(): TelegramStorageService
    {
        if (!$this->storageService) {
            $this->storageService = new TelegramStorageService();
        }
        return $this->storageService;
    }

    /**
     * Get user telegram settings
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success([
            'auto_forward' => $user->hasTelegramAutoForward(),
            'forward_target' => $user->getTelegramForwardTarget(),
            'driver_enabled' => $this->isTelegramDriverEnabled(),
        ]);
    }

    /**
     * Update user telegram settings
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'auto_forward' => 'required|boolean',
            'forward_target' => 'nullable|string|max:100',
        ]);

        /** @var User $user */
        $user = $request->user();

        // اگر auto_forward فعال است، target الزامی است
        if ($validated['auto_forward'] && empty($validated['forward_target'])) {
            return $this->error('Forward target is required when auto-forward is enabled',[], 422);
        }

        // اگر target وجود دارد، format آن را چک کن
        if (!empty($validated['forward_target'])) {
            $target = $validated['forward_target'];
            
            // باید یا کانال/گروه ID (-100...) یا username (@...) یا user ID باشد
            if (!preg_match('/^-100\d+$/', $target) && !preg_match('/^@\w+$/', $target) && !preg_match('/^\d+$/', $target)) {
                return $this->error('Invalid Telegram ID format. Use channel/group ID (-1001234567890), username (@username), or user ID',[], 422);
            }
        }

        try {
            if ($validated['auto_forward']) {
                $user->enableTelegramAutoForward($validated['forward_target']);
            } else {
                $user->disableTelegramAutoForward();
            }

            return $this->success([
                'message' => 'Telegram settings updated successfully',
                'settings' => [
                    'auto_forward' => $user->hasTelegramAutoForward(),
                    'forward_target' => $user->getTelegramForwardTarget(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update telegram settings', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to update settings',[], 500);
        }
    }

    /**
     * Upload existing file to Telegram
     */
    public function uploadToTelegram(Request $request, int $fileId): JsonResponse
    {
        $validated = $request->validate([
            'caption' => 'nullable|string|max:1024',
            'channel_id' => 'nullable|string|max:100', // اختیاری - کاربر می‌تواند کانال خاص را مشخص کند
        ]);

        /** @var User $user */
        $user = $request->user();

        // پیدا کردن فایل
        $fileEntry = null;
        try{
        $fileEntry = FileEntry::where('id', $fileId)
            ->where('user_id', $user->id)
            ->firstOrFail();
        } catch (\Exception $e) {
        
            Log::error('Failed to get file telegramMetadata', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $fileEntry = FileEntry::where('id', $fileId)
            ->where('owner_id', $user->id)
            ->firstOrFail();
        }
        if (!$fileEntry || $fileEntry == null) {
            return $this->error('فایل یافت نشد یا اجازه دسترسی به آن ندارید', [], 404);
        }
        // چک کردن اینکه آیا قبلاً در تلگرام آپلود شده یا نه
        if ($fileEntry->telegramMetadata && $fileEntry->telegramMetadata->isUploadCompleted()) {
            return $this->error('File is already uploaded to Telegram',[], 422);
        }

        try {
            // دانلود فایل از storage فعلی
            $tempPath = storage_path('app/temp/' . $fileEntry->hash);
            
            // دایرکتوری temp را ایجاد کن
            if (!file_exists(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            // دانلود فایل
            $disk = Storage::disk($fileEntry->disk_prefix ?? config('common.site.uploads_disk'));
            $contents = $disk->get($fileEntry->getStoragePath());
            file_put_contents($tempPath, $contents);

            // تعیین channel_id - اولویت: request > user's forward_target > admin channel
            $channelId = $validated['channel_id'] 
                ?? $user->getTelegramForwardTarget() 
                ?? config('services.telegram.channel_id');

            // آپلود به تلگرام با استفاده از storage service
            $result = $this->getStorageService()->uploadFile(
                $tempPath,
                [
                    'id' => $fileEntry->id,
                    'name' => $fileEntry->name,
                    'user_id' => $user->id,
                ],
                [
                    'caption' => $validated['caption'] ?? $fileEntry->name,
                ]
            );

            // حذف فایل موقت
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return $this->success([
                'message' => 'File uploaded to Telegram successfully',
                'metadata' => [
                    'message_id' => $result['metadata']->message_id,
                    'file_id' => $result['metadata']->telegram_file_id,
                    'upload_method' => $result['metadata']->upload_method,
                    'channel_id' => $result['metadata']->channel_id,
                ],
            ]);

        } catch (TelegramException $e) {
            // حذف فایل موقت در صورت خطا
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            return $this->error('Failed to upload to Telegram: ' . $e->getMessage(),[], 500);
        } catch (\Exception $e) {
            // حذف فایل موقت در صورت خطا
            if (isset($tempPath) && file_exists($tempPath)) {
                unlink($tempPath);
            }
            
            Log::error('Failed to upload file to Telegram', [
                'file_id' => $fileId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to upload file to Telegram', [],500);
        }
    }

    /**
     * Forward file to specific Telegram ID or Username
     * 
     * Supports:
     * - Channel/Group ID: -1001234567890
     * - Public Channel/Group Username: @channelname or channelname
     * - User ID: 123456789
     * - User Username: @username or username
     */
    public function forwardFile(Request $request, int $fileId): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => 'required|string|max:100',
        ]);

        /** @var User $user */
        $user = $request->user();

        // پیدا کردن فایل
        $fileEntry = null;
        try{
        $fileEntry = FileEntry::where('id', $fileId)
            ->where('user_id', $user->id)
            ->with('telegramMetadata')
            ->firstOrFail();
       } catch (\Exception $e) {
        
            Log::error('Failed to get file telegramMetadata', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            $fileEntry = FileEntry::where('id', $fileId)
            ->where('owner_id', $user->id)
            ->with('telegramMetadata')
            ->firstOrFail();
        }
        // چک کردن اینکه فایل در تلگرام باشد
        if (!$fileEntry->telegramMetadata || !$fileEntry->telegramMetadata->isUploadCompleted()) {
            return $this->error('File is not uploaded to Telegram', [],422);
        }

        $metadata = $fileEntry->telegramMetadata;
        $targetId = $validated['target_id'];

        // Validate target ID format - supports both numeric IDs and usernames
        // - Channel/Group ID: -100XXXXXXXXXX or -XXXXXXXXX
        // - Username: @username or username (5-32 chars, alphanumeric + underscore)
        // - User ID: positive integer
        if (!preg_match('/^-100\d+$/', $targetId) && 
            !preg_match('/^-\d{9,10}$/', $targetId) &&
            !preg_match('/^@?[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $targetId) && 
            !preg_match('/^\d{5,12}$/', $targetId)) {
            return $this->error('Invalid Telegram ID or username format. Supported: -1001234567890, @username, or user ID', [], 422);
        }

        try {
            // Initialize manager with proper channel
            $manager = $this->getTelegramManager($metadata->channel_id);
            
            // 🔍 SMART TARGET DETECTION:
            // تشخیص نوع target و انتخاب روش مناسب forward
            $targetDetection = TelegramTargetDetector::detectTarget($targetId);
            $forwardMethod = $targetDetection['method']; // 'bot' or 'user'
            
            Log::info('Manual forward: Target detected', [
                'target_id' => $targetId,
                'detected_type' => $targetDetection['type'],
                'forward_method' => $forwardMethod,
                'reason' => $targetDetection['reason'],
            ]);
            
            // انتخاب client مناسب بر اساس نوع target
            // ✅ کانال/گروه → Bot Client
            // ✅ User Account → User Client
            $client = ($forwardMethod === 'bot')
                ? $manager->getBotClient()
                : $manager->getUserClient();
            
            // پردازش Caption Template (اگر تنظیم شده باشد)
            $envSettings = (new DotEnvEditor())->load();
            $caption = null;
            $captionTemplate = settings('telegram_forward_caption_template') ?? $envSettings['telegram_forward_caption_template'] ?? null;
            if (!empty($captionTemplate)) {
                $caption = TelegramCaptionParser::parse($captionTemplate, $fileEntry);
                Log::info('Manual forward: Caption template parsed', [
                    'template' => $captionTemplate,
                    'caption_length' => strlen($caption ?? ''),
                ]);
            }
            
            // Forward message با caption (اگر وجود دارد)
            $result = $client->forwardMessage(
                $metadata->channel_id,
                $metadata->message_id,
                $targetDetection['target_normalized'] ?? $targetId,
                $metadata->upload_method,
                $caption
            );

            return $this->success([
                'message' => 'File forwarded successfully',
                'forward_result' => $result,
                'target_info' => [
                    'type' => $targetDetection['type'],
                    'method' => $forwardMethod,
                    'reason' => $targetDetection['reason'],
                ],
            ]);

        } catch (TelegramException $e) {
            return $this->error('Failed to forward file: ' . $e->getMessage(),[], 500);
        } catch (\Exception $e) {
            Log::error('Failed to forward file', [
                'file_id' => $fileId,
                'target_id' => $targetId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to forward file',[], 500);
        }
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
}
