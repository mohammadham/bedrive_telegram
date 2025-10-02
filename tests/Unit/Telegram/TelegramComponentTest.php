<?php

namespace Tests\Unit\Telegram;

use Common\Files\Telegram\TelegramFileManager;
use Common\Files\Telegram\TelegramPathMapper;
use Common\Files\Telegram\TelegramMetadataHelper;
use Tests\TestCase;

/**
 * Unit Tests for Telegram Components
 */
class TelegramComponentTest extends TestCase
{
    /** @test */
    public function can_determine_upload_method_correctly()
    {
        // فایل کوچک: Bot API
        $result = TelegramMetadataHelper::determineUploadMethod(10 * 1024 * 1024); // 10MB
        $this->assertEquals('bot', $result);

        // فایل متوسط: Bot API (limit)
        $result = TelegramMetadataHelper::determineUploadMethod(49 * 1024 * 1024); // 49MB
        $this->assertEquals('bot', $result);

        // فایل بزرگ: User Account
        $result = TelegramMetadataHelper::determineUploadMethod(75 * 1024 * 1024); // 75MB
        $this->assertEquals('user', $result);

        // فایل خیلی بزرگ: User Account
        $result = TelegramMetadataHelper::determineUploadMethod(500 * 1024 * 1024); // 500MB
        $this->assertEquals('user', $result);
    }

    /** @test */
    public function can_determine_telegram_file_type()
    {
        $this->assertEquals('photo', TelegramMetadataHelper::determineTelegramFileType('image/jpeg'));
        $this->assertEquals('photo', TelegramMetadataHelper::determineTelegramFileType('image/png'));
        $this->assertEquals('video', TelegramMetadataHelper::determineTelegramFileType('video/mp4'));
        $this->assertEquals('audio', TelegramMetadataHelper::determineTelegramFileType('audio/mpeg'));
        $this->assertEquals('document', TelegramMetadataHelper::determineTelegramFileType('application/pdf'));
        $this->assertEquals('document', TelegramMetadataHelper::determineTelegramFileType('unknown/type'));
    }

    /** @test */
    public function can_check_upload_capability()
    {
        // فایل قابل آپلود با Bot
        $result = TelegramMetadataHelper::canUploadFile(10 * 1024 * 1024);
        $this->assertTrue($result['can_upload']);
        $this->assertEquals('bot', $result['method']);

        // فایل قابل آپلود با User
        $result = TelegramMetadataHelper::canUploadFile(100 * 1024 * 1024);
        $this->assertTrue($result['can_upload']);
        $this->assertEquals('user', $result['method']);

        // فایل خیلی بزرگ
        $result = TelegramMetadataHelper::canUploadFile(3 * 1024 * 1024 * 1024); // 3GB
        $this->assertFalse($result['can_upload']);
        $this->assertStringContainsString('exceeds', $result['reason']);
    }

    /** @test */
    public function path_normalization_works_correctly()
    {
        $this->assertEquals('file.pdf', TelegramPathMapper::normalizePath('./file.pdf'));
        $this->assertEquals('file.pdf', TelegramPathMapper::normalizePath('/file.pdf'));
        $this->assertEquals('docs/file.pdf', TelegramPathMapper::normalizePath('docs//file.pdf'));
        $this->assertEquals('file.pdf', TelegramPathMapper::normalizePath('docs/../file.pdf'));
        $this->assertEquals('', TelegramPathMapper::normalizePath(''));
        $this->assertEquals('docs/report.pdf', TelegramPathMapper::normalizePath('./docs/./report.pdf'));
    }

    /** @test */
    public function can_determine_upload_method_from_file_manager()
    {
        // < 50MB
        $check = TelegramFileManager::canUpload(30 * 1024 * 1024);
        $this->assertTrue($check['can_upload']);
        $this->assertEquals('bot', $check['method']);

        // > 50MB < 2GB
        $check = TelegramFileManager::canUpload(100 * 1024 * 1024);
        $this->assertTrue($check['can_upload']);
        $this->assertEquals('user', $check['method']);

        // > 2GB
        $check = TelegramFileManager::canUpload(2.5 * 1024 * 1024 * 1024);
        $this->assertFalse($check['can_upload']);
    }

    /** @test */
    public function formatted_file_size_works()
    {
        $sizes = [
            1024 => '1.00 KB',
            1024 * 1024 => '1.00 MB',
            5 * 1024 * 1024 => '5.00 MB',
            1024 * 1024 * 1024 => '1.00 GB',
            1536 * 1024 * 1024 => '1.50 GB',
        ];

        foreach ($sizes as $bytes => $expected) {
            $result = \Common\Files\Telegram\TelegramMetadataHelper::formatFileSize($bytes);
            $this->assertEquals($expected, $result);
        }
    }
}
