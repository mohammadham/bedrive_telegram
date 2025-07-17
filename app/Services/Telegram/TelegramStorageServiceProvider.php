<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class TelegramStorageServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Storage::extend('telegram', function ($app, $config) {
            $adapter = new TelegramAdapter();
            $filesystem = new Filesystem($adapter);

            return new TelegramStorage($filesystem);
        });
    }

    public function register()
    {
        //
    }
}
