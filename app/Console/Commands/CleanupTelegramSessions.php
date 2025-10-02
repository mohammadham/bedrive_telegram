<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TelegramUploadSession;

/**
 * Phase 8.3: Cleanup Telegram Upload Sessions
 * 
 * Command برای پاکسازی sessions قدیمی و stale
 * 
 * Usage:
 *   php artisan telegram:cleanup-sessions
 *   php artisan telegram:cleanup-sessions --stale-only
 */
class CleanupTelegramSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:cleanup-sessions 
                            {--stale-only : Only cleanup stale sessions (1+ hour inactive)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old and stale Telegram upload sessions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $staleOnly = $this->option('stale-only');

        if ($staleOnly) {
            $this->info('Cleaning up stale sessions (inactive for 1+ hour)...');
            $count = $this->cleanupStaleSessions();
        } else {
            $this->info('Cleaning up old sessions (24+ hours old)...');
            $count = TelegramUploadSession::cleanupOld();
        }

        $this->newLine();
        $this->info("Cleaned up {$count} session(s).");

        return self::SUCCESS;
    }

    /**
     * پاکسازی stale sessions
     */
    protected function cleanupStaleSessions(): int
    {
        $sessions = TelegramUploadSession::stale()->get();
        $count = 0;

        foreach ($sessions as $session) {
            // Mark as failed
            $session->markAsFailed('Session timeout - inactive for too long');
            $session->cleanupTempFile();
            $count++;
        }

        return $count;
    }
}
