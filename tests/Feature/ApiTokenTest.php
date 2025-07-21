<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_generate_api_token()
    {
        $response = $this->actingAs($this->user)
                        ->postJson('/api/v1/user/generate-api-token');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'api_token',
                        'created_at'
                    ]
                ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->api_token);
        $this->assertNotNull($this->user->api_token_created_at);
    }

    public function test_user_can_revoke_api_token()
    {
        // First generate a token
        $this->user->update([
            'api_token' => Hash::make('test_token'),
            'api_token_created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
                        ->deleteJson('/api/v1/user/revoke-api-token');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'message' => 'API token revoked successfully'
                    ]
                ]);

        $this->user->refresh();
        $this->assertNull($this->user->api_token);
        $this->assertNull($this->user->api_token_created_at);
    }

    public function test_api_token_authentication_works()
    {
        $token = 'test_api_token';
        $this->user->update([
            'api_token' => Hash::make($token),
            'api_token_created_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/files');

        $response->assertStatus(200);
    }

    public function test_invalid_api_token_returns_unauthorized()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token',
        ])->getJson('/api/v1/files');

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid API token'
                ]);
    }

    public function test_missing_api_token_returns_unauthorized()
    {
        $response = $this->getJson('/api/v1/files');

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'API token required'
                ]);
    }
}

