<?php

namespace Common\Files\Telegram;

use Illuminate\Support\Facades\Log;

/**
 * Telegram Target Type Detector
 * 
 * تشخیص نوع target ID تلگرام:
 * - Channel/Group (public or private)
 * - User Account
 * 
 * و انتخاب بهترین روش برای forward
 */
class TelegramTargetDetector
{
    /**
     * تشخیص نوع target و روش مناسب forward
     * 
     * @param string $targetId Target ID (channel, group, or user) - supports numeric IDs and usernames
     * @return array ['type' => 'channel|user', 'method' => 'bot|user', 'reason' => string, 'target_normalized' => string]
     * 
     * Supported formats:
     * - Private Channel/Group: -100XXXXXXXXXX
     * - Public Channel/Group: @username or username
     * - Old-style Group: -XXXXXXXXX
     * - User ID: XXXXXXXXX (positive integer)
     * - User Username: @username or username
     */
    public static function detectTarget(string $targetId): array
    {
        // Normalize target
        $targetId = trim($targetId);
        
        Log::info('Detecting target type', ['target_id' => $targetId]);
        
        // 1. Private Channel/Group ID format: -100XXXXXXXXXX
        if (preg_match('/^-100\d{10,}$/', $targetId)) {
            Log::info('Target detected as: Private Channel/Group', [
                'target_id' => $targetId,
                'pattern' => 'Starts with -100',
            ]);
            
            return [
                'type' => 'channel',
                'method' => 'bot',
                'reason' => 'Private channel/group detected. Bot can forward to channels.',
                'target_normalized' => $targetId,
                'is_username' => false,
            ];
        }
        
        // 2. Public Channel/Group with @username
        if (preg_match('/^@[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $targetId)) {
            Log::info('Target detected as: Public Channel/Group (username)', [
                'target_id' => $targetId,
                'pattern' => 'Starts with @',
            ]);
            
            return [
                'type' => 'channel',
                'method' => 'bot',
                'reason' => 'Public channel/group username detected. Bot can forward to public channels.',
                'target_normalized' => $targetId,
                'is_username' => true,
            ];
        }
        
        // 3. Old-style Group ID: negative number without -100 prefix
        // Format: -XXXXXXXXX (usually 9-10 digits)
        if (preg_match('/^-\d{9,10}$/', $targetId)) {
            Log::info('Target detected as: Old-style Group ID', [
                'target_id' => $targetId,
                'pattern' => 'Negative number (old group format)',
            ]);
            
            return [
                'type' => 'group',
                'method' => 'bot',
                'reason' => 'Old-style group ID detected. Bot can forward to groups.',
                'target_normalized' => $targetId,
                'is_username' => false,
            ];
        }
        
        // 4. User ID: positive integer
        // Telegram user IDs are positive integers (usually 9-10 digits)
        if (preg_match('/^\d{5,12}$/', $targetId)) {
            Log::info('Target detected as: User Account (numeric ID)', [
                'target_id' => $targetId,
                'pattern' => 'Positive integer',
            ]);
            
            return [
                'type' => 'user',
                'method' => 'user',
                'reason' => 'User ID detected. User Account (MTProto) must be used to forward to users.',
                'target_normalized' => (int) $targetId,
                'is_username' => false,
            ];
        }
        
        // 5. Username without @ (we'll add it)
        // This can be either a channel or a user - we'll try both methods
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,31}$/', $targetId)) {
            $normalized = '@' . $targetId;
            
            Log::info('Target detected as: Username without @', [
                'target_id' => $targetId,
                'normalized' => $normalized,
            ]);
            
            // برای username های بدون @، ابتدا Bot را امتحان می‌کنیم (سریعتر است)
            // اگر کار نکرد، User Account استفاده می‌شود
            return [
                'type' => 'username',
                'method' => 'bot', // Changed from 'user' to 'bot' - bot is faster for public channels
                'reason' => 'Username detected. Bot method will be tried first for public channels/groups.',
                'target_normalized' => $normalized,
                'is_username' => true,
                'fallback_method' => 'user', // If bot fails, try user method
            ];
        }
        
        // 6. Unknown format - default to user method (safer)
        Log::warning('Unknown target format, defaulting to User Account method', [
            'target_id' => $targetId,
        ]);
        
        return [
            'type' => 'unknown',
            'method' => 'user',
            'reason' => 'Unknown target format. Using User Account as fallback (safer).',
            'target_normalized' => $targetId,
            'is_username' => false,
        ];
    }
    
    /**
     * بررسی سریع: آیا target یک کانال/گروه است؟
     * 
     * @param string $targetId
     * @return bool
     */
    public static function isChannel(string $targetId): bool
    {
        $detection = self::detectTarget($targetId);
        return in_array($detection['type'], ['channel', 'group']);
    }
    
    /**
     * بررسی سریع: آیا target یک کاربر است؟
     * 
     * @param string $targetId
     * @return bool
     */
    public static function isUser(string $targetId): bool
    {
        $detection = self::detectTarget($targetId);
        return $detection['type'] === 'user';
    }
    
    /**
     * دریافت روش مناسب forward برای target
     * 
     * @param string $targetId
     * @return string 'bot' or 'user'
     */
    public static function getForwardMethod(string $targetId): string
    {
        $detection = self::detectTarget($targetId);
        return $detection['method'];
    }
    
    /**
     * دریافت توضیحات کامل برای debugging
     * 
     * @param string $targetId
     * @return string
     */
    public static function explainTarget(string $targetId): string
    {
        $detection = self::detectTarget($targetId);
        
        return sprintf(
            "Target: %s | Type: %s | Method: %s | Reason: %s",
            $targetId,
            $detection['type'],
            $detection['method'],
            $detection['reason']
        );
    }
}
