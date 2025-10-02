<?php

namespace Common\Files\Telegram;

use App\Models\TelegramFileMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Path Mapper for Telegram Storage
 * 
 * تلگرام مفهوم path و directory ندارد، این کلاس یک سیستم مجازی
 * برای مدیریت paths ایجاد می‌کند و آن‌ها را به metadata map می‌کند
 */
class TelegramPathMapper
{
    /**
     * Cache key prefix
     */
    protected const CACHE_PREFIX = 'telegram_path:';

    /**
     * Cache TTL (seconds)
     */
    protected const CACHE_TTL = 3600; // 1 hour

    /**
     * Map path to telegram metadata
     * 
     * @param string $path
     * @return TelegramFileMetadata|null
     */
    public static function resolve(string $path): ?TelegramFileMetadata
    {
        $cacheKey = self::CACHE_PREFIX . md5($path);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($path) {
            return TelegramFileMetadata::whereJsonContains('metadata->path', $path)
                ->orWhere('metadata->original_path', $path)
                ->first();
        });
    }

    /**
     * Store path mapping
     * 
     * @param string $path
     * @param TelegramFileMetadata $metadata
     * @return void
     */
    public static function store(string $path, TelegramFileMetadata $metadata): void
    {
        // ذخیره path در metadata JSON
        $currentMetadata = $metadata->metadata ?? [];
        $currentMetadata['path'] = $path;
        $currentMetadata['original_path'] = $path;
        $currentMetadata['directory'] = dirname($path);
        $currentMetadata['filename'] = basename($path);
        
        $metadata->metadata = $currentMetadata;
        $metadata->save();

        // Cache path mapping
        $cacheKey = self::CACHE_PREFIX . md5($path);
        Cache::put($cacheKey, $metadata, self::CACHE_TTL);
    }

    /**
     * Delete path mapping
     * 
     * @param string $path
     * @return void
     */
    public static function forget(string $path): void
    {
        $cacheKey = self::CACHE_PREFIX . md5($path);
        Cache::forget($cacheKey);
    }

    /**
     * Move/rename path
     * 
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function move(string $from, string $to): bool
    {
        $metadata = self::resolve($from);
        
        if (!$metadata) {
            return false;
        }

        // حذف mapping قدیمی
        self::forget($from);

        // ایجاد mapping جدید
        self::store($to, $metadata);

        return true;
    }

    /**
     * List all files in a directory
     * 
     * @param string $directory
     * @param bool $recursive
     * @return \Illuminate\Support\Collection
     */
    public static function listDirectory(string $directory = '', bool $recursive = false): \Illuminate\Support\Collection
    {
        $directory = trim($directory, '/');
        
        $query = TelegramFileMetadata::whereNotNull('metadata');

        if ($directory) {
            if ($recursive) {
                // لیست تمام فایل‌های زیرمجموعه
                $query->where(function ($q) use ($directory) {
                    $q->whereJsonContains('metadata->directory', $directory)
                      ->orWhere('metadata->directory', 'LIKE', $directory . '/%');
                });
            } else {
                // فقط فایل‌های مستقیم در این پوشه
                $query->whereJsonContains('metadata->directory', $directory);
            }
        }

        return $query->get();
    }

    /**
     * Get all unique directories
     * 
     * @return array
     */
    public static function getDirectories(): array
    {
        $directories = TelegramFileMetadata::whereNotNull('metadata')
            ->get()
            ->map(function ($metadata) {
                return data_get($metadata->metadata, 'directory');
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return array_filter($directories);
    }

    /**
     * Check if directory exists (has files)
     * 
     * @param string $directory
     * @return bool
     */
    public static function directoryExists(string $directory): bool
    {
        $directory = trim($directory, '/');
        
        return TelegramFileMetadata::whereJsonContains('metadata->directory', $directory)
            ->exists();
    }

    /**
     * Generate unique path if file already exists
     * 
     * @param string $path
     * @return string
     */
    public static function generateUniquePath(string $path): string
    {
        if (!self::resolve($path)) {
            return $path;
        }

        $directory = dirname($path);
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $counter = 1;
        do {
            $newFilename = $filename . '_' . $counter;
            $newPath = ($directory !== '.' ? $directory . '/' : '') . $newFilename;
            if ($extension) {
                $newPath .= '.' . $extension;
            }
            $counter++;
        } while (self::resolve($newPath));

        return $newPath;
    }

    /**
     * Normalize path
     * 
     * @param string $path
     * @return string
     */
    public static function normalizePath(string $path): string
    {
        // حذف / اضافی
        $path = trim($path, '/');
        
        // حذف . و ..
        $parts = explode('/', $path);
        $normalized = [];
        
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($normalized);
            } else {
                $normalized[] = $part;
            }
        }

        return implode('/', $normalized);
    }

    /**
     * Get file metadata by path
     * 
     * @param string $path
     * @return array|null
     */
    public static function getFileInfo(string $path): ?array
    {
        $metadata = self::resolve($path);
        
        if (!$metadata) {
            return null;
        }

        return [
            'path' => data_get($metadata->metadata, 'path'),
            'size' => $metadata->original_file_size,
            'mime_type' => $metadata->original_mime_type,
            'last_modified' => $metadata->uploaded_at?->timestamp ?? $metadata->updated_at->timestamp,
            'telegram_file_id' => $metadata->telegram_file_id,
            'message_id' => $metadata->message_id,
            'upload_method' => $metadata->upload_method,
        ];
    }

    /**
     * Clear all path cache
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        // این در production باید با tag-based cache پیاده‌سازی شود
        Cache::flush();
    }
}
