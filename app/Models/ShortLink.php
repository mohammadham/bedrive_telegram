<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class ShortLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'short_code',
        'file_entry_id',
        'user_id',
        'password',
        'expires_at',
        'max_downloads',
        'download_count',
        'is_active',
        'access_log',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'access_log' => 'array',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Get the file entry that owns the short link.
     */
    public function fileEntry(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class);
    }

    /**
     * Get the user that owns the short link.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the short link is valid and accessible
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check expiration
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check download limit
        if ($this->max_downloads && $this->download_count >= $this->max_downloads) {
            return false;
        }

        return true;
    }

    /**
     * Check if password is required
     */
    public function requiresPassword(): bool
    {
        return !empty($this->password);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        if (!$this->requiresPassword()) {
            return true;
        }

        return Hash::check($password, $this->password);
    }

    /**
     * Increment download count and log access
     */
    public function logAccess(array $accessInfo = []): void
    {
        $this->increment('download_count');
        
        $log = $this->access_log ?? [];
        $log[] = array_merge([
            'timestamp' => now()->toISOString(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $accessInfo);

        // Keep only last 100 access logs
        if (count($log) > 100) {
            $log = array_slice($log, -100);
        }

        $this->update(['access_log' => $log]);
    }

    /**
     * Generate a unique short code
     */
    public static function generateShortCode(): string
    {
        do {
            $code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
        } while (self::where('short_code', $code)->exists());

        return $code;
    }

    /**
     * Get the full short URL
     */
    public function getShortUrlAttribute(): string
    {
        return url('/s/' . $this->short_code);
    }

    /**
     * Get access statistics
     */
    public function getAccessStats(): array
    {
        $log = $this->access_log ?? [];
        
        return [
            'total_downloads' => $this->download_count,
            'unique_ips' => count(array_unique(array_column($log, 'ip'))),
            'last_access' => !empty($log) ? end($log)['timestamp'] : null,
            'access_by_day' => $this->getAccessByDay($log),
        ];
    }

    /**
     * Get access statistics grouped by day
     */
    protected function getAccessByDay(array $log): array
    {
        $byDay = [];
        
        foreach ($log as $access) {
            $date = Carbon::parse($access['timestamp'])->format('Y-m-d');
            $byDay[$date] = ($byDay[$date] ?? 0) + 1;
        }

        return $byDay;
    }
}

