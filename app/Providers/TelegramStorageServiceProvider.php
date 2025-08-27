<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use App\Services\Storage\TelegramStorageDriver;
use League\Flysystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use App\Services\Storage\TelegramFilesystemAdapter;

class TelegramStorageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Storage::extend('telegram', function ($app, $config) {
            // $telegramConfig = [
            //     'api_id' => $config['api_id'] ?? env('TELEGRAM_API_ID'),
            //     'api_hash' => $config['api_hash'] ?? env('TELEGRAM_API_HASH'),
            //     'phone' => $config['phone'] ?? env('TELEGRAM_PHONE'),
            // ];

            $chatId = $config['chat_id'] ?? env('TELEGRAM_CHAT_ID');

            $driver = new TelegramStorageDriver();
            $adapter = new TelegramFilesystemAdapter($driver, $chatId);
            return new FilesystemAdapter(
                new Filesystem($adapter, $config),
                $adapter,
                $config,
            );
        });
    }
}

