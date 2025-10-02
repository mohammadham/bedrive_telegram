<?php

namespace Tests\Feature\Telegram;

use App\Models\FileEntry;
use App\Models\TelegramFileMetadata;
use App\Models\User;
use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Telegram Feature Tests
 * 
 * این تست‌ها تمام functionality تلگرام را چک می‌کنند:
 * - User settings (auto-forward)
 * - File upload to Telegram
 * - File forward
 * - Metadata management
 */
class TelegramFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected FileEntry $file;

    protected function setUp(): void
    {
        parent::setUp();

        // ایجاد کاربر تستی
        $this->user = User::factory()->create();
    }

    /** @test */
    public function user_can_get_telegram_settings()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/user/telegram/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'auto_forward',
                'forward_target',
                'driver_enabled',
            ]);
    }

    /** @test */
    public function user_can_enable_auto_forward_with_valid_target()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/telegram/settings', [
                'auto_forward' => true,
                'forward_target' => '-1001234567890',
            ]);

        $response->assertOk();

        $this->user->refresh();
        $this->assertTrue($this->user->hasTelegramAutoForward());
        $this->assertEquals('-1001234567890', $this->user->getTelegramForwardTarget());
    }

    /** @test */
    public function user_can_enable_auto_forward_with_username()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/telegram/settings', [
                'auto_forward' => true,
                'forward_target' => '@mychannel',
            ]);

        $response->assertOk();

        $this->user->refresh();
        $this->assertTrue($this->user->hasTelegramAutoForward());
        $this->assertEquals('@mychannel', $this->user->getTelegramForwardTarget());
    }

    /** @test */
    public function user_cannot_enable_auto_forward_without_target()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/telegram/settings', [
                'auto_forward' => true,
                'forward_target' => '',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function user_cannot_enable_auto_forward_with_invalid_target_format()
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/telegram/settings', [
                'auto_forward' => true,
                'forward_target' => 'invalid-format',
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function user_can_disable_auto_forward()
    {
        // ابتدا فعال کن
        $this->user->enableTelegramAutoForward('-1001234567890');

        // سپس غیرفعال کن
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/user/telegram/settings', [
                'auto_forward' => false,
                'forward_target' => '',
            ]);

        $response->assertOk();

        $this->user->refresh();
        $this->assertFalse($this->user->hasTelegramAutoForward());
        $this->assertNull($this->user->getTelegramForwardTarget());
    }

    /** @test */
    public function user_can_only_access_own_files()
    {
        $otherUser = User::factory()->create();
        $otherFile = FileEntry::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/user/telegram/upload/{$otherFile->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function telegram_metadata_relationship_works()
    {
        $file = FileEntry::factory()->create(['user_id' => $this->user->id]);
        
        $metadata = TelegramFileMetadata::create([
            'file_entry_id' => $file->id,
            'telegram_file_id' => 'test_file_id',
            'message_id' => 12345,
            'channel_id' => '-1001234567890',
            'upload_method' => 'bot',
            'original_file_size' => 1024,
            'telegram_file_type' => 'document',
            'upload_status' => 'completed',
            'uploaded_at' => now(),
        ]);

        $this->assertNotNull($file->telegramMetadata);
        $this->assertEquals('test_file_id', $file->telegramMetadata->telegram_file_id);
        $this->assertTrue($file->telegramMetadata->isUploadCompleted());
        $this->assertTrue($file->telegramMetadata->isUploadedViaBot());
    }

    /** @test */
    public function user_model_methods_work_correctly()
    {
        $user = User::factory()->create();

        // Initially false
        $this->assertFalse($user->hasTelegramAutoForward());
        $this->assertNull($user->getTelegramForwardTarget());

        // Enable
        $user->enableTelegramAutoForward('-1001234567890');
        $user->refresh();

        $this->assertTrue($user->hasTelegramAutoForward());
        $this->assertEquals('-1001234567890', $user->getTelegramForwardTarget());

        // Disable
        $user->disableTelegramAutoForward();
        $user->refresh();

        $this->assertFalse($user->hasTelegramAutoForward());
        $this->assertNull($user->getTelegramForwardTarget());
    }

    /** @test */
    public function metadata_helper_methods_work()
    {
        $file = FileEntry::factory()->create();
        $metadata = TelegramFileMetadata::create([
            'file_entry_id' => $file->id,
            'telegram_file_id' => 'test',
            'message_id' => 123,
            'channel_id' => '-100123',
            'upload_method' => 'bot',
            'original_file_size' => 5242880, // 5MB
            'telegram_file_type' => 'document',
            'upload_status' => 'pending',
        ]);

        $this->assertTrue($metadata->isUploadedViaBot());
        $this->assertFalse($metadata->isUploadedViaUserAccount());
        $this->assertFalse($metadata->isUploadCompleted());

        $metadata->markAsCompleted();
        $this->assertTrue($metadata->isUploadCompleted());

        $this->assertEquals('5.00 MB', $metadata->getFormattedFileSize());
    }

    /** @test */
    public function cascade_delete_works()
    {
        $file = FileEntry::factory()->create();
        $metadata = TelegramFileMetadata::create([
            'file_entry_id' => $file->id,
            'telegram_file_id' => 'test',
            'message_id' => 123,
            'channel_id' => '-100123',
            'upload_method' => 'bot',
            'original_file_size' => 1024,
            'telegram_file_type' => 'document',
            'upload_status' => 'completed',
        ]);

        $metadataId = $metadata->id;

        // حذف file
        $file->delete();

        // metadata هم باید حذف شود
        $this->assertNull(TelegramFileMetadata::find($metadataId));
    }
}
