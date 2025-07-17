<?php

namespace Tests\Feature;

use App\Models\FileEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TelegramStorageTest extends TestCase
{
    public function test_it_uploads_a_file_to_telegram()
    {
        Storage::fake('telegram');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->actingAs($this->getRegularUser())
            ->postJson('api/v1/uploads', [
                'file' => $file,
                'parentId' => null,
            ])
            ->assertStatus(201);

        $entry = FileEntry::first();

        Storage::disk('telegram')->assertExists($entry->file_name);
    }
}
