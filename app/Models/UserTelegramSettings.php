<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTelegramSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'auto_send_to_telegram',
    ];

    protected $casts = [
        'auto_send_to_telegram' => 'boolean',
    ];

    /**
     * Get the user that owns the telegram settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

