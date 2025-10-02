<?php

namespace Common\Files\Providers;

use Common\Files\Adapters\TelegramAdapter;
use Common\Files\Telegram\TelegramFileManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class TelegramServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('telegram', function ($app, $config) {
            // Telegram configuration
            $telegramConfig = [
                'channel_id' => $config['channel_id'] ?? config('services.telegram.channel_id'),
                'prefix' => $config['prefix'] ?? '',
            ];

            // Create Telegram adapter
            $adapter = new TelegramAdapter($telegramConfig);

            // Return Laravel Filesystem adapter
            return new FilesystemAdapter(
                new Filesystem($adapter, $telegramConfig),
                $adapter,
                $telegramConfig,
            );
        });
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        // Register TelegramFileManager as singleton
        $this->app->singleton(TelegramFileManager::class, function ($app) {
            return new TelegramFileManager(
                config('services.telegram.channel_id')
            );
        });
    }
}
