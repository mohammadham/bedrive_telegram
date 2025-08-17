<?php

namespace App\Models;

use Common\Auth\BaseUser;
use Common\Workspaces\Workspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class User extends BaseUser
{
    use HasApiTokens, HasFactory;

    public function telegramSettings(): HasOne
    {
        return $this->hasOne(UserTelegramSettings::class);
    }

    protected function telegramUserChatId(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->telegramSettings?->telegram_user_chat_id,
        );
    }

    protected function telegramAutoForward(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->telegramSettings?->telegram_auto_forward,
        );
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function routeNotificationForFcm(): string|array|null
    {
        return $this->fcmTokens()
            ->get()
            ->pluck('token')
            ->toArray();
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(FcmToken::class);
    }

    public function loadFcmToken(): ?string
    {
        if ($this->currentAccessToken()) {
            $token =
                $this->fcmTokens()
                    ->where('device_id', $this->currentAccessToken()->name)
                    ->first()->token ?? null;
            $this['fcm_token'] = $token;
            return $token;
        }
        return null;
    }
}
