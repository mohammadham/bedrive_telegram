<?php

namespace Common\Files\Actions;

use Common\Files\Actions\GetUserSpaceUsage;
use Common\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ValidateFileUpload
{
    protected array $fileData;

    public function execute(array $fileData): Collection|null
    {
        $this->fileData = $fileData;

        $errors = collect([
            'size' => $this->validateMaximumFileSize(),
            'spaceUsage' => $this->validateAllowedStorageSpace(),
            'allowedExtensions' => $this->validateAllowedExtensions(),
            'blockedExtensions' => $this->validateBlockedExtensions(),
        ])->filter(fn($msg) => !is_null($msg));

        if (!$errors->isEmpty()) {
            return $errors;
        }

        return null;
    }

    protected function validateAllowedExtensions(): string|null
    {
        $allowedExtensions = settings('uploads.allowed_extensions');

        if (
            !empty($allowedExtensions) &&
            !$this->extensionMatches($allowedExtensions)
        ) {
            return __('Files of this type are not allowed');
        }

        return null;
    }

    protected function validateBlockedExtensions(): string|null
    {
        $blockedExtensions = settings('uploads.blocked_extensions');

        if (
            !empty($blockedExtensions) &&
            $this->extensionMatches($blockedExtensions)
        ) {
            return __('Files of this type are not allowed');
        }

        return null;
    }

    protected function extensionMatches(array $extensions): bool
    {
        if (empty($extensions) || !isset($this->fileData['extension'])) {
            return false;
        }

        $extensions = array_map(
            fn($ext) => str_replace('.', '', $ext),
            $extensions,
        );

        return in_array(
            str_replace('.', '', $this->fileData['extension']),
            $extensions,
        );
    }

    protected function validateMaximumFileSize(): ?string
    {
        $maxSize = $this->getUserMaxUploadSize();
        if (is_null($maxSize) || !isset($this->fileData['size'])) {
            return null;
        }

        if ((int) $this->fileData['size'] > (int) $maxSize) {
            return __('The file size may not be greater than :size', [
                'size' => self::formatBytes((int) $maxSize),
            ]);
        }

        return null;
    }

    /**
     * Get maximum upload file size based on user's plan or global setting
     *
     * @return int|null Size in bytes
     */
    protected function getUserMaxUploadSize(): ?int
    {
        // Try to get from user's subscription plan first
        if (Auth::check()) {
            $user = Auth::user();
            $subscription = $user->subscriptions()->where('gateway_status', 'active')->first();
            
            if ($subscription && $subscription->product) {
                $planMaxSize = $subscription->product->max_upload_file_size;
                if (!is_null($planMaxSize) && $planMaxSize > 0) {
                    // Convert from MB to bytes
                    return (int) ($planMaxSize * 1024 * 1024);
                }
            }
        }

        // Fall back to global setting
        return app(Settings::class)->get('uploads.max_size');
    }

    protected function validateAllowedStorageSpace(): string|null
    {
        if (!isset($this->fileData['size']) || !Auth::check()) {
            return null;
        }

        $enoughSpace = app(GetUserSpaceUsage::class)->hasEnoughSpaceToUpload(
            $this->fileData['size'],
        );

        if (!$enoughSpace) {
            return self::notEnoughSpaceMessage();
        }

        return null;
    }

    public static function formatBytes(?int $bytes, $unit = 'MB'): string
    {
        if (is_null($bytes)) {
            return '0 bytes';
        }

        if ((!$unit && $bytes >= 1 << 30) || $unit == 'GB') {
            return number_format($bytes / (1 << 30), 1) . 'GB';
        }
        if ((!$unit && $bytes >= 1 << 20) || $unit == 'MB') {
            return number_format($bytes / (1 << 20), 1) . 'MB';
        }
        if ((!$unit && $bytes >= 1 << 10) || $unit == 'KB') {
            return number_format($bytes / (1 << 10), 1) . 'KB';
        }
        return number_format($bytes) . ' bytes';
    }

    public static function notEnoughSpaceMessage(): string
    {
        return __(
            'You have exhausted your allowed space of :space. Delete some files or upgrade your plan.',
            [
                'space' => self::formatBytes(
                    app(GetUserSpaceUsage::class)->getAvailableSpace(),
                ),
            ],
        );
    }
}
