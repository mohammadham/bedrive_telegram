<?php

namespace Tests\Feature\Telegram;

use App\Models\User;
use Common\Files\Telegram\TelegramUrlUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TelegramUrlUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_validates_url_format()
    {
        $response = $this->postJson('/api/v1/telegram/upload-url', [
            'url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /** @test */
    public function it_requires_url_field()
    {
        $response = $this->postJson('/api/v1/telegram/upload-url', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    /** @test */
    public function it_validates_bulk_urls()
    {
        $response = $this->postJson('/api/v1/telegram/upload-bulk-urls', [
            'urls' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['urls']);
    }

    /** @test */
    public function it_limits_bulk_urls_to_100()
    {
        $urls = array_fill(0, 101, 'https://example.com/file.jpg');

        $response = $this->postJson('/api/v1/telegram/upload-bulk-urls', [
            'urls' => $urls,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['urls']);
    }

    /** @test */
    public function it_can_validate_url_accessibility()
    {
        $response = $this->postJson('/api/v1/telegram/validate-url', [
            'url' => 'https://httpbin.org/image/png',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'valid',
                'content_type',
                'content_length',
                'can_upload',
            ]);
    }

    /** @test */
    public function url_upload_service_extracts_filename_correctly()
    {
        $service = new TelegramUrlUploadService();
        
        // Using reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFileName');
        $method->setAccessible(true);

        $fileName = $method->invoke($service, 'https://example.com/test.pdf', 'application/pdf');
        $this->assertEquals('test.pdf', $fileName);
    }

    /** @test */
    public function url_upload_service_generates_filename_when_missing()
    {
        $service = new TelegramUrlUploadService();
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFileName');
        $method->setAccessible(true);

        $fileName = $method->invoke($service, 'https://example.com/', 'image/jpeg');
        $this->assertStringContainsString('.jpg', $fileName);
    }

    /** @test */
    public function it_determines_correct_file_type_from_mime()
    {
        $service = new TelegramUrlUploadService();
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('determineFileType');
        $method->setAccessible(true);

        $this->assertEquals('image', $method->invoke($service, 'image/jpeg'));
        $this->assertEquals('video', $method->invoke($service, 'video/mp4'));
        $this->assertEquals('audio', $method->invoke($service, 'audio/mpeg'));
        $this->assertEquals('pdf', $method->invoke($service, 'application/pdf'));
    }
}