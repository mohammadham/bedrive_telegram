<?php

namespace Database\Seeders;

use App\Models\FileEntry;
use App\Models\TelegramFileMetadata;
use Illuminate\Database\Seeder;

/**
 * Seeder for testing Telegram file metadata
 * This is for development/testing purposes only
 */
class TelegramMetadataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Example: Create sample telegram metadata for existing files
        // This is just for testing - you can remove this in production
        
        $sampleData = [
            [
                'telegram_file_id' => 'BQACAgQAAxkBAAIBB2YzMjU2NjYyNjIyNTY2NjY2NjY2AAI',
                'telegram_file_unique_id' => 'AgADAgADyqcxG',
                'message_id' => 12345,
                'channel_id' => '-1001234567890',
                'upload_method' => 'bot',
                'original_file_size' => 5242880, // 5MB
                'original_mime_type' => 'image/jpeg',
                'telegram_mime_type' => 'image/jpeg',
                'telegram_file_type' => 'photo',
                'upload_status' => 'completed',
                'uploaded_at' => now(),
                'metadata' => [
                    'width' => 1920,
                    'height' => 1080,
                    'has_thumbnail' => true,
                ],
            ],
            [
                'telegram_file_id' => 'BQACAgQAAxkBAAIBB2YzMjU2NjYyNjIyNTY2NjY2NjY3AAI',
                'telegram_file_unique_id' => 'AgADAgADyqcxH',
                'message_id' => 12346,
                'channel_id' => '-1001234567890',
                'upload_method' => 'user',
                'original_file_size' => 104857600, // 100MB
                'original_mime_type' => 'video/mp4',
                'telegram_mime_type' => 'video/mp4',
                'telegram_file_type' => 'video',
                'upload_status' => 'completed',
                'uploaded_at' => now(),
                'session_file' => storage_path('app/telegram/session.madeline'),
                'metadata' => [
                    'duration' => 120,
                    'width' => 1920,
                    'height' => 1080,
                    'codec' => 'h264',
                ],
            ],
        ];

        foreach ($sampleData as $index => $data) {
            // Note: In real usage, file_entry_id should reference an existing FileEntry
            // This is just sample structure
            echo "Sample Telegram metadata structure #{$index}: \n";
            echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
        }

        $this->command->info('Telegram metadata seeder completed!');
        $this->command->info('Note: This seeder only shows sample data structure.');
        $this->command->info('Real metadata will be created automatically when files are uploaded via Telegram driver.');
    }
}
