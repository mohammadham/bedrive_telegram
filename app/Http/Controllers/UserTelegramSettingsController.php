<?php

namespace App\Http\Controllers;

use App\Models\FileEntry;
use App\Models\User;
use Common\Core\BaseController;
use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramStorageService;
use Common\Files\Telegram\Exceptions\TelegramException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserTelegramSettingsController extends BaseController
{
    protected TelegramFileManager $telegramManager;
    protected TelegramStorageService $storageService;

    public function __construct()
    {
        $this->middleware('auth');
        $this->telegramManager = new TelegramFileManager();
        $this->storageService = new TelegramStorageService();
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
            return $this->error('Forward target is required when auto-forward is enabled', 422);
        }

        // اگر target وجود دارد، format آن را چک کن
        if (!empty($validated['forward_target'])) {
            $target = $validated['forward_target'];
            
            // باید یا کانال/گروه ID (-100...) یا username (@...) باشد
            if (!preg_match('/^-100\d+$/', $target) && !preg_match('/^@\w+$/', $target)) {
                return $this->error('Invalid Telegram ID format. Use channel/group ID (-1001234567890) or username (@username)', 422);
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

            return $this->error('Failed to update settings', 500);
        }
    }

    /**
     * Upload existing file to Telegram
     */
    public function uploadToTelegram(Request $request, int $fileId): JsonResponse
    {
        $validated = $request->validate([
            'caption' => 'nullable|string|max:1024',
        ]);

        /** @var User $user */
        $user = $request->user();

        // پیدا کردن فایل
        $fileEntry = FileEntry::where('id', $fileId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // چک کردن اینکه آیا قبلاً در تلگرام آپلود شده یا نه
        if ($fileEntry->telegramMetadata && $fileEntry->telegramMetadata->isUploadCompleted()) {
            return $this->error('File is already uploaded to Telegram', 422);
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

            // آپلود به تلگرام
            $result = $this->storageService->uploadFile(
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
                ],
            ]);

        } catch (TelegramException $e) {
            return $this->error('Failed to upload to Telegram: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            Log::error('Failed to upload file to Telegram', [
                'file_id' => $fileId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to upload file to Telegram', 500);
        }
    }

    /**
     * Forward file to specific Telegram ID
     */
    public function forwardFile(Request $request, int $fileId): JsonResponse
    {
        $validated = $request->validate([
            'target_id' => 'required|string|max:100',
        ]);

        /** @var User $user */
        $user = $request->user();

        // پیدا کردن فایل
        $fileEntry = FileEntry::where('id', $fileId)
            ->where('user_id', $user->id)
            ->with('telegramMetadata')
            ->firstOrFail();

        // چک کردن اینکه فایل در تلگرام باشد
        if (!$fileEntry->telegramMetadata || !$fileEntry->telegramMetadata->isUploadCompleted()) {
            return $this->error('File is not uploaded to Telegram', 422);
        }

        $metadata = $fileEntry->telegramMetadata;
        $targetId = $validated['target_id'];

        // Validate target ID format
        if (!preg_match('/^-100\d+$/', $targetId) && !preg_match('/^@\w+$/', $targetId) && !preg_match('/^\d+$/', $targetId)) {
            return $this->error('Invalid Telegram ID format', 422);
        }

        try {
            // Forward message به target
            $client = $metadata->isUploadedViaBot()
                ? $this->telegramManager->getBotClient()
                : $this->telegramManager->getUserClient();

            // Forward message
            $result = $client->forwardMessage(
                $metadata->channel_id,
                $metadata->message_id,
                $targetId
            );

            return $this->success([
                'message' => 'File forwarded successfully',
                'forward_result' => $result,
            ]);

        } catch (TelegramException $e) {
            return $this->error('Failed to forward file: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            Log::error('Failed to forward file', [
                'file_id' => $fileId,
                'target_id' => $targetId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to forward file', 500);
        }
    }

    /**
     * Check if Telegram driver is enabled
     */
    protected function isTelegramDriverEnabled(): bool
    {
        $driver = config('common.site.uploads_disk');
        return $driver === 'telegram';
    }
}
