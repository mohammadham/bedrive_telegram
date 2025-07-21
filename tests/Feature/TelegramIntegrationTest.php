<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Storage\TelegramStorageDriver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class TelegramIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_telegram_driver_can_be_instantiated()
    {
        $config = [
            'api_id' => 'test_id',
            'api_hash' => 'test_hash',
            'phone' => '+1234567890',
        ];

        $driver = new TelegramStorageDriver($config);
        $this->assertInstanceOf(TelegramStorageDriver::class, $driver);
    }

    public function test_telegram_status_endpoint()
    {
        $response = $this->actingAs($this->user)
                        ->getJson('/api/v1/telegram/status');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'status',
                    'configured'
                ]);
    }

    public function test_telegram_install_endpoint()
    {
        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/telegram/install');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message'
                ]);
    }

    public function test_telegram_test_upload_requires_configuration()
    {
        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/telegram/test-upload');

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Telegram API credentials not configured'
                ]);
    }
}

