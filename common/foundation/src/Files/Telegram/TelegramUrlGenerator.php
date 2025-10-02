<?php

namespace Common\Files\Telegram;

use App\Models\FileEntry;
use App\Models\TelegramFileMetadata;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * URL Generator for Telegram Files
 * 
 * تولید URLهای دانلود برای فایل‌های تلگرام
 * (چون تلگرام کانال خصوصی URL عمومی ندارد)
 */
class TelegramUrlGenerator
{
    /**
     * Generate temporary download URL
     * 
     * @param FileEntry|TelegramFileMetadata $file
     * @param int $expiresInMinutes
     * @return string
     */
    public static function temporary($file, int $expiresInMinutes = 60): string
    {
        $fileId = $file instanceof FileEntry ? $file->id : $file->file_entry_id;

        return URL::temporarySignedRoute(
            'telegram.download',
            now()->addMinutes($expiresInMinutes),
            ['file' => $fileId]
        );
    }

    /**
     * Generate permanent download URL (requires authentication)
     * 
     * @param FileEntry|TelegramFileMetadata $file
     * @return string
     */
    public static function permanent($file): string
    {
        $fileId = $file instanceof FileEntry ? $file->id : $file->file_entry_id;

        return route('telegram.download', ['file' => $fileId]);
    }

    /**
     * Generate streaming URL for video/audio
     * 
     * @param FileEntry|TelegramFileMetadata $file
     * @param int $expiresInMinutes
     * @return string
     */
    public static function stream($file, int $expiresInMinutes = 60): string
    {
        $fileId = $file instanceof FileEntry ? $file->id : $file->file_entry_id;

        return URL::temporarySignedRoute(
            'telegram.stream',
            now()->addMinutes($expiresInMinutes),
            ['file' => $fileId]
        );
    }

    /**
     * Generate thumbnail URL for images/videos
     * 
     * @param FileEntry|TelegramFileMetadata $file
     * @param string $size (small, medium, large)
     * @return string|null
     */
    public static function thumbnail($file, string $size = 'medium'): ?string
    {
        $metadata = $file instanceof TelegramFileMetadata 
            ? $file 
            : $file->telegramMetadata;

        if (!$metadata) {
            return null;
        }

        // چک کنیم فایل عکس یا ویدیو باشد
        if (!in_array($metadata->telegram_file_type, ['photo', 'video'])) {
            return null;
        }

        $fileId = $file instanceof FileEntry ? $file->id : $metadata->file_entry_id;

        return route('telegram.thumbnail', [
            'file' => $fileId,
            'size' => $size
        ]);
    }

    /**
     * Generate direct Telegram URL (for Bot API files only)
     * 
     * @param TelegramFileMetadata $metadata
     * @return string|null
     */
    public static function directTelegramUrl(TelegramFileMetadata $metadata): ?string
    {
        // فقط برای Bot API که file_path دارند
        if ($metadata->upload_method !== 'bot') {
            return null;
        }

        $botToken = config('services.telegram.bot_token');
        
        if (!$botToken || !$metadata->telegram_file_id) {
            return null;
        }

        // این URL فقط با Bot Token قابل دسترسی است
        return "https://api.telegram.org/bot{$botToken}/getFile?file_id={$metadata->telegram_file_id}";
    }

    /**
     * Generate public share link
     * 
     * @param FileEntry $file
     * @param int|null $expiresInHours
     * @return string
     */
    public static function share(FileEntry $file, ?int $expiresInHours = null): string
    {
        // ایجاد یک share token
        $token = Str::random(32);
        
        // ذخیره token در metadata (در production باید در جدول جداگانه باشد)
        $metadata = $file->telegramMetadata;
        if ($metadata) {
            $metadataArray = $metadata->metadata ?? [];
            $metadataArray['share_token'] = $token;
            if ($expiresInHours) {
                $metadataArray['share_expires_at'] = now()->addHours($expiresInHours)->toIso8601String();
            }
            $metadata->metadata = $metadataArray;
            $metadata->save();
        }

        return route('telegram.share', ['token' => $token]);
    }

    /**
     * Verify share token
     * 
     * @param string $token
     * @return TelegramFileMetadata|null
     */
    public static function verifyShareToken(string $token): ?TelegramFileMetadata
    {
        $metadata = TelegramFileMetadata::whereJsonContains('metadata->share_token', $token)
            ->first();

        if (!$metadata) {
            return null;
        }

        // چک انقضا
        $expiresAt = data_get($metadata->metadata, 'share_expires_at');
        if ($expiresAt && now()->isAfter($expiresAt)) {
            return null;
        }

        return $metadata;
    }

    /**
     * Generate admin preview URL
     * 
     * @param FileEntry $file
     * @return string
     */
    public static function adminPreview(FileEntry $file): string
    {
        return route('admin.telegram.preview', ['file' => $file->id]);
    }

    /**
     * Generate embed code for file
     * 
     * @param FileEntry $file
     * @param array $options
     * @return string
     */
    public static function embedCode(FileEntry $file, array $options = []): string
    {
        $url = self::temporary($file, $options['expires'] ?? 60);
        $type = $file->type;

        if (str_starts_with($type, 'image/')) {
            return sprintf(
                '<img src="%s" alt="%s" />',
                $url,
                htmlspecialchars($file->name)
            );
        }

        if (str_starts_with($type, 'video/')) {
            return sprintf(
                '<video controls><source src="%s" type="%s"></video>',
                $url,
                $type
            );
        }

        if (str_starts_with($type, 'audio/')) {
            return sprintf(
                '<audio controls><source src="%s" type="%s"></audio>',
                $url,
                $type
            );
        }

        return sprintf(
            '<a href="%s" download>%s</a>',
            $url,
            htmlspecialchars($file->name)
        );
    }
}
