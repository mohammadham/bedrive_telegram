<?php

namespace Common\Files\Providers;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class DynamicStorageDiskProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('dynamic-uploads', function (
            Application $app,
            $initialConfig,
        ) {
            return $this->resolveDisk('uploads', $initialConfig);
        });

        Storage::extend('dynamic-public', function (
            Application $app,
            $initialConfig,
        ) {
            return $this->resolveDisk('public', $initialConfig);
        });
    }

    public function register()
    {
        //
    }

    private function resolveDisk(string $type, array $initialConfig): Filesystem
    {
        $driverName = config("common.site.{$type}_disk_driver") ?? 'local';
        
        // Get base config from services
        $config = array_merge(
            $initialConfig,
            config("services.$driverName") ?? [],
        );
        
        // For telegram driver, also load settings from database
        if ($driverName === 'telegram') {
            $telegramSettings = array_filter([
                'bot_token' => settings('storage_telegram_bot_token') ?? env('STORAGE_TELEGRAM_BOT_TOKEN'),
                'channel_id' => settings('storage_telegram_channel_id') ?? env('STORAGE_TELEGRAM_CHANNEL_ID') ,
                'api_id' => settings('storage_telegram_api_id')?? env('STORAGE_TELEGRAM_API_ID') ,
                'api_hash' => settings('storage_telegram_api_hash')?? env('STORAGE_TELEGRAM_API_HASH'),
                'phone' => settings('storage_telegram_phone')?? env('STORAGE_TELEGRAM_PHONE'),
                'session_file' => settings('storage_telegram_session_file')?? env('STORAGE_TELEGRAM_SESSION_FILE'),
            ], fn($value) => !empty($value));
            
            Log::info('DynamicStorageDiskProvider loading Telegram settings', [
                'type' => $type,
                'settings_loaded' => array_keys($telegramSettings),
                'channel_id' => $telegramSettings['channel_id'] ?? 'MISSING',
            ]);
            
            $config = array_merge($config, $telegramSettings);
        }
        
        $config['driver'] = $driverName;

        // set root based on drive type and name
        $config['root'] =
            $driverName === 'local'
                ? $config['local_root']
                : $config['remote_root'];

        // unset "storage" url from remote drives as "$disk->url()" will generate "storage/file_entry.jpg" url
        if (
            $driverName !== 'local' &&
            Arr::get($config, 'url') === $config['remote_root']
        ) {
            unset($config['url']);
        }

        if (isset($config['port'])) {
            $config['port'] = (int) $config['port'];
        }

        $dynamicConfigKey = "{$type}_{$driverName}";
        Config::set("filesystems.disks.{$dynamicConfigKey}", $config);

        return Storage::disk($dynamicConfigKey);
    }
}
