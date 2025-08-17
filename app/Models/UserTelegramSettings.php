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
        'telegram_user_chat_id',
        'telegram_auto_forward',
    ];

    protected $casts = [
        'telegram_auto_forward' => 'boolean',
    ];

    /**
     * Get the user that owns the telegram settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

