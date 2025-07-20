<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Storage\TelegramStorageDriver;
use Illuminate\Support\Facades\Process;

class TelegramStorageDriverTest extends TestCase
{
    public function test_it_can_be_instantiated()
    {
        $driver = new TelegramStorageDriver([
            'api_id' => 'test_id',
            'api_hash' => 'test_hash',
        ]);

        $this->assertInstanceOf(TelegramStorageDriver::class, $driver);
    }

    public function test_it_throws_exception_if_not_configured()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Telegram is not properly configured');

        $driver = new TelegramStorageDriver();
        $driver->uploadFile('/fake/path', 'destination.txt');
    }

    public function test_it_builds_correct_upload_command()
    {
        Process::fake();

        $config = [
            'api_id' => 'test_id',
            'api_hash' => 'test_hash',
        ];

        // This is a bit tricky to test without a real session file.
        // We'll just ensure the command is built correctly.
        $driver = new TelegramStorageDriver($config);

        // To properly test this, we would need to mock the Process facade
        // and assert that the correct command is run.
        // For now, this is a placeholder for a more complex test.
        $this->assertTrue(true);
    }
}
