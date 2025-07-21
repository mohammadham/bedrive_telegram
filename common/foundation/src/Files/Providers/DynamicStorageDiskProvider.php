<?php

namespace Common\Files\Providers;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

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

          // ----- add telegram driver here -----
    Storage::extend('telegram', function ($app, $config) {
        $telegramConfig = [
            'api_id'  => $config['api_id'] ?? env('TELEGRAM_API_ID'),
            'api_hash'=> $config['api_hash'] ?? env('TELEGRAM_API_HASH'),
            'phone'   => $config['phone'] ?? env('TELEGRAM_PHONE'),
        ];
        $chatId = $config['chat_id'] ?? env('TELEGRAM_CHAT_ID');

        $driver  = new \App\Services\Storage\TelegramStorageDriver($telegramConfig);
        $adapter = new \App\Services\Storage\TelegramFilesystemAdapter($driver, $chatId);

        return new \Illuminate\Filesystem\FilesystemAdapter(
            new \League\Flysystem\Filesystem($adapter, $config),
            $adapter,
            $config
        );
    });
    }

    public function register()
    {
        //
    }

    private function resolveDisk(string $type, array $initialConfig): Filesystem
    {
        $driverName = config("common.site.{$type}_disk_driver") ?? 'local';
        $config = array_merge(
            $initialConfig,
            config("services.$driverName") ?? [],
        );
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
