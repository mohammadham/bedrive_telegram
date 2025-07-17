<?php

namespace App\Services\Telegram;

use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;

class TelegramStorage extends FilesystemAdapter
{
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
    }
}
