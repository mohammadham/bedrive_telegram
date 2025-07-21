<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\FileEntry;
use App\Models\ShortLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $fileEntry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->fileEntry = FileEntry::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_user_can_create_short_link()
    {
        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/short-links', [
                            'file_id' => $this->fileEntry->id,
                        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'short_link' => [
                            'short_code',
                            'file_entry_id',
                            'user_id',
                        ],
                        'short_url'
                    ]
                ]);

        $this->assertDatabaseHas('short_links', [
            'file_entry_id' => $this->fileEntry->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_create_short_link_with_password()
    {
        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/short-links', [
                            'file_id' => $this->fileEntry->id,
                            'password' => 'secret123',
                        ]);

        $response->assertStatus(200);

        $shortLink = ShortLink::where('file_entry_id', $this->fileEntry->id)->first();
        $this->assertNotNull($shortLink->password);
        $this->assertTrue($shortLink->requiresPassword());
    }

    public function test_user_can_create_short_link_with_expiration()
    {
        $expiresAt = Carbon::now()->addDays(7);

        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/short-links', [
                            'file_id' => $this->fileEntry->id,
                            'expires_at' => $expiresAt->toISOString(),
                        ]);

        $response->assertStatus(200);

        $shortLink = ShortLink::where('file_entry_id', $this->fileEntry->id)->first();
        $this->assertEquals($expiresAt->format('Y-m-d H:i'), $shortLink->expires_at->format('Y-m-d H:i'));
    }

    public function test_user_cannot_create_duplicate_short_link()
    {
        // Create first short link
        ShortLink::create([
            'short_code' => 'test1234',
            'file_entry_id' => $this->fileEntry->id,
            'user_id' => $this->user->id,
        ]);

        // Try to create another
        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/short-links', [
                            'file_id' => $this->fileEntry->id,
                        ]);

        $response->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'A short link already exists for this file. Delete it first to create a new one.'
                ]);
    }

    public function test_user_can_list_short_links()
    {
        ShortLink::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
                        ->getJson('/api/v1/short-links');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'short_code',
                                'file_entry_id',
                                'stats',
                                'short_url'
                            ]
                        ]
                    ]
                ]);
    }

    public function test_user_can_update_short_link()
    {
        $shortLink = ShortLink::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
                        ->putJson("/api/v1/short-links/{$shortLink->id}", [
                            'password' => 'newpassword',
                            'max_downloads' => 100,
                        ]);

        $response->assertStatus(200);

        $shortLink->refresh();
        $this->assertTrue($shortLink->requiresPassword());
        $this->assertEquals(100, $shortLink->max_downloads);
    }

    public function test_user_can_delete_short_link()
    {
        $shortLink = ShortLink::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
                        ->deleteJson("/api/v1/short-links/{$shortLink->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('short_links', ['id' => $shortLink->id]);
    }

    public function test_public_can_access_short_link_info()
    {
        $shortLink = ShortLink::factory()->create([
            'user_id' => $this->user->id,
            'file_entry_id' => $this->fileEntry->id,
        ]);

        $response = $this->getJson("/api/v1/s/{$shortLink->short_code}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'file' => [
                            'name',
                            'size',
                            'mime_type'
                        ],
                        'requires_password',
                        'download_count',
                    ]
                ]);
    }

    public function test_expired_short_link_returns_error()
    {
        $shortLink = ShortLink::factory()->create([
            'user_id' => $this->user->id,
            'file_entry_id' => $this->fileEntry->id,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->getJson("/api/v1/s/{$shortLink->short_code}");

        $response->assertStatus(410)
                ->assertJson([
                    'success' => false,
                    'message' => 'This link has expired or is no longer available'
                ]);
    }

    public function test_short_link_with_max_downloads_limit()
    {
        $shortLink = ShortLink::factory()->create([
            'user_id' => $this->user->id,
            'file_entry_id' => $this->fileEntry->id,
            'max_downloads' => 1,
            'download_count' => 1,
        ]);

        $response = $this->getJson("/api/v1/s/{$shortLink->short_code}");

        $response->assertStatus(410);
    }
}

