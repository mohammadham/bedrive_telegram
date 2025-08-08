<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Storage\TelegramStorageDriver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Mockery;
use Illuminate\Support\Facades\Http;

class TelegramIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
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

        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Test upload failed'
                ]);
    }

    public function test_file_upload_with_telegram_driver()
    {
        // Set default filesystem to telegram
        config(['filesystems.default' => 'telegram']);

        // Mock the TelegramStorageDriver
        $mock = Mockery::mock(TelegramStorageDriver::class);
        $this->app->instance(TelegramStorageDriver::class, $mock);

        $mock->shouldReceive('uploadFile')
            ->once()
            ->with(Mockery::any(), 'test_file.txt', Mockery::any())
            ->andReturn([
                'success' => true,
                'file_id' => 'tg_12345',
                'path' => 'test_file.txt',
                'output' => 'File ID: tg_12345'
            ]);

        // Mock the file upload
        Storage::fake('local');
        $file = UploadedFile::fake()->create('test_file.txt', 100);

        // Assuming there is a general file upload endpoint
        // And it uses the default storage driver
        $response = $this->actingAs($this->user)
                         ->postJson('/api/v1/files/upload', [
                             'file' => $file,
                             'parentId' => null, // root directory
                         ]);

        $response->assertStatus(200);

        // Assert that the file was "uploaded" to telegram
        $this->assertDatabaseHas('file_entries', [
            'name' => 'test_file.txt',
            'file_name' => 'test_file.txt',
            'type' => 'text/plain',
        ]);
    }

    public function test_configure_bot_endpoint_success()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/telegram/configure-bot', [
                            'token' => 'fake-bot-token',
                        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertDatabaseHas('settings', [
            'name' => 'telegram_bot_token',
            'value' => 'fake-bot-token',
        ]);
        $this->assertDatabaseHas('settings', [
            'name' => 'telegram_webhook_set',
            'value' => '1',
        ]);
    }

    public function test_delete_file_with_bot()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        config(['settings.telegram_bot_token' => 'fake-bot-token']);

        $driver = app(TelegramStorageDriver::class);
        $result = $driver->deleteFile('12345', 'test-chat');

        $this->assertTrue($result);
    }

    public function test_file_exists_with_bot()
    {
        Http::fake([
            'api.telegram.org/botfake-bot-token/forwardMessage' => Http::response(['ok' => true, 'result' => ['message_id' => '54321']]),
            'api.telegram.org/botfake-bot-token/deleteMessage' => Http::response(['ok' => true, 'result' => true]),
        ]);

        config(['settings.telegram_bot_token' => 'fake-bot-token']);

        $driver = app(TelegramStorageDriver::class);
        $result = $driver->fileExists('12345', 'test-chat');

        $this->assertTrue($result);
    }
}

