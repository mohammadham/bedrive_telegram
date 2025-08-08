<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Storage\TelegramStorageDriver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Mockery;

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
}

