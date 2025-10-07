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
            // Load telegram settings directly from database if available
            // This ensures we always get fresh settings, not cached ones
            $telegramConfig = [
                'bot_token' => settings('storage_telegram_bot_token') ?? $config['bot_token'] ?? null,
                'channel_id' => settings('storage_telegram_channel_id') ?? $config['channel_id'] ?? config('services.telegram.channel_id') ?? null,
                'api_id' => settings('storage_telegram_api_id') ?? $config['api_id'] ?? null,
                'api_hash' => settings('storage_telegram_api_hash') ?? $config['api_hash'] ?? null,
                'phone' => settings('storage_telegram_phone') ?? $config['phone'] ?? null,
                'prefix' => $config['prefix'] ?? '',
            ];

            // Create Telegram adapter with full config
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
            // Load telegram settings from database first, then fallback to config
            $telegramConfig = [
                'bot_token' => settings('storage_telegram_bot_token') ?? config('services.telegram.bot_token'),
                'channel_id' => settings('storage_telegram_channel_id') ?? config('services.telegram.channel_id'),
                'api_id' => settings('storage_telegram_api_id') ?? config('services.telegram.api_id'),
                'api_hash' => settings('storage_telegram_api_hash') ?? config('services.telegram.api_hash'),
                'phone' => settings('storage_telegram_phone') ?? config('services.telegram.phone'),
            ];

            // Return TelegramFileManager with full config
            return new TelegramFileManager($telegramConfig['channel_id'], $telegramConfig);
        });
    }
}
