<?php

namespace Common\Files\Telegram;

use Common\Files\FileEntry;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Helper برای parse کردن caption template
 * 
 * متغیرهای قابل استفاده:
 * - {filename} - نام فایل
 * - {original_name} - نام اصلی فایل
 * - {size} - حجم فایل (formatted)
 * - {type} - نوع فایل (extension)
 * - {mime} - MIME type
 * - {date} - تاریخ آپلود
 * - {user} - نام کاربر
 */
class TelegramCaptionParser
{
    /**
     * Parse caption template با جایگزینی متغیرها
     *
     * @param string|null $template
     * @param FileEntry $fileEntry
     * @return string|null
     */
    public static function parse(?string $template, FileEntry $fileEntry): ?string
    {
        if (empty($template)) {
            return null;
        }

        $variables = self::getVariables($fileEntry);

        $caption = $template;
        foreach ($variables as $key => $value) {
            $caption = str_replace('{' . $key . '}', $value, $caption);
        }

        // حذف متغیرهای باقیمانده که جایگزین نشدند
        $caption = preg_replace('/\{[^}]+\}/', '', $caption);

        // محدود کردن طول caption (Telegram limit: 1024 characters)
        if (strlen($caption) > 1024) {
            $caption = Str::limit($caption, 1020, '...');
        }

        return trim($caption);
    }

    /**
     * دریافت تمام متغیرهای قابل استفاده
     *
     * @param FileEntry $fileEntry
     * @return array
     */
    protected static function getVariables(FileEntry $fileEntry): array
    {
        return [
            'filename' => $fileEntry->name,
            'original_name' => $fileEntry->name,
            'size' => self::formatBytes($fileEntry->file_size ?? 0),
            'type' => $fileEntry->extension ?? 'file',
            'mime' => $fileEntry->mime ?? 'application/octet-stream',
            'date' => Carbon::parse($fileEntry->created_at)->format('Y-m-d H:i'),
            'user' => $fileEntry->users?->first()?->display_name ?? 'Unknown',
        ];
    }

    /**
     * فرمت کردن bytes به human readable
     *
     * @param int $bytes
     * @return string
     */
    protected static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * بررسی اینکه template معتبر است یا خیر
     *
     * @param string|null $template
     * @return bool
     */
    public static function isValid(?string $template): bool
    {
        if (empty($template)) {
            return true;
        }

        // بررسی طول
        if (strlen($template) > 2048) {
            return false;
        }

        // بررسی متغیرهای معتبر
        $validVariables = [
            'filename',
            'original_name',
            'size',
            'type',
            'mime',
            'date',
            'user',
        ];

        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        $usedVariables = $matches[1];

        // اگر متغیر نامعتبری استفاده شده، false برگردان
        foreach ($usedVariables as $variable) {
            if (!in_array($variable, $validVariables)) {
                return false;
            }
        }

        return true;
    }

    /**
     * دریافت لیست متغیرهای معتبر
     *
     * @return array
     */
    public static function getAvailableVariables(): array
    {
        return [
            'filename' => 'File name',
            'original_name' => 'Original file name',
            'size' => 'File size (formatted)',
            'type' => 'File extension',
            'mime' => 'MIME type',
            'date' => 'Upload date',
            'user' => 'User name',
        ];
    }
}
