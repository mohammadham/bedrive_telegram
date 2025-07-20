<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserTelegramSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Common\Core\BaseController;

class UserApiController extends BaseController
{
    /**
     * Generate API token for the authenticated user
     */
    public function generateApiToken(): JsonResponse
    {
        $user = Auth::user();
        
        // Generate a new API token
        $token = Str::random(60);
        
        $user->update([
            'api_token' => Hash::make($token),
            'api_token_created_at' => now(),
        ]);

        return $this->success([
            'api_token' => $token,
            'created_at' => $user->api_token_created_at,
        ]);
    }

    /**
     * Revoke API token for the authenticated user
     */
    public function revokeApiToken(): JsonResponse
    {
        $user = Auth::user();
        
        $user->update([
            'api_token' => null,
            'api_token_created_at' => null,
        ]);

        return $this->success(['message' => 'API token revoked successfully']);
    }

    /**
     * Get API token status
     */
    public function getApiTokenStatus(): JsonResponse
    {
        $user = Auth::user();
        
        return $this->success([
            'has_token' => !empty($user->api_token),
            'created_at' => $user->api_token_created_at,
        ]);
    }

    /**
     * Get user's Telegram settings
     */
    public function getTelegramSettings(User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $settings = UserTelegramSettings::where('user_id', $user->id)->first();

        return $this->success([
            'settings' => [
                'telegram_chat_id' => $settings->telegram_chat_id ?? '',
                'auto_send_to_telegram' => $settings->auto_send_to_telegram ?? false,
            ]
        ]);
    }

    /**
     * Update user's Telegram settings
     */
    public function updateTelegramSettings(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);
        $validator = Validator::make($request->all(), [
            'telegram_chat_id' => 'nullable|string|max:255',
            'auto_send_to_telegram' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }
        
        UserTelegramSettings::updateOrCreate(
            ['user_id' => $user->id],
            [
                'telegram_chat_id' => $request->input('telegram_chat_id'),
                'auto_send_to_telegram' => $request->input('auto_send_to_telegram', false),
            ]
        );

        return $this->success(['message' => 'Telegram settings updated successfully']);
    }
}

