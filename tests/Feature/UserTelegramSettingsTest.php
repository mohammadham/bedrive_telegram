<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserTelegramSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTelegramSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_get_their_telegram_settings()
    {
        UserTelegramSettings::factory()->create([
            'user_id' => $this->user->id,
            'telegram_chat_id' => '@test_channel',
            'auto_send_to_telegram' => true,
        ]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/v1/user/telegram-settings');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'telegram_chat_id' => '@test_channel',
                        'auto_send_to_telegram' => true,
                    ]
                ]);
    }

    public function test_user_can_update_their_telegram_settings()
    {
        $response = $this->actingAs($this->user)
                        ->putJson('/api/v1/user/telegram-settings', [
                            'telegram_chat_id' => '@new_channel',
                            'auto_send_to_telegram' => true,
                        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'message' => 'Telegram settings updated successfully'
                    ]
                ]);

        $this->assertDatabaseHas('user_telegram_settings', [
            'user_id' => $this->user->id,
            'telegram_chat_id' => '@new_channel',
            'auto_send_to_telegram' => true,
        ]);
    }
}
