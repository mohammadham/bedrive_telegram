<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_entry_id',
        'telegram_file_id',
        'telegram_chat_id',
    ];

    public function fileEntry(): BelongsTo
    {
        return $this->belongsTo(FileEntry::class);
    }
}
