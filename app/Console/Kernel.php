<?php

namespace App\Console;

use App\Console\Commands\CleanDemoSite;
use App\Console\Commands\CreateDemoAccounts;
use App\Console\Commands\DeleteExpiredLinks;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [DeleteExpiredLinks::class];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command(DeleteExpiredLinks::class)->everyMinute();

        // Phase 8.4: Process Telegram retry queue every 5 minutes
        // $schedule->command('telegram:process-retries')->everyFiveMinutes();

        // Phase 8.3: Cleanup stale sessions every hour
        $schedule->command('telegram:cleanup-sessions', ['--stale-only'])->hourly();

        // Phase 8.3: Cleanup old sessions daily
        $schedule->command('telegram:cleanup-sessions')->daily();

        if (config('common.site.demo')) {
            $schedule->command(CleanDemoSite::class)->daily();
        }
    }

    protected function commands()
    {
        if (config('common.site.demo')) {
            $this->registerCommand(app(CreateDemoAccounts::class));
            $this->registerCommand(app(CleanDemoSite::class));
        }

        require base_path('routes/console.php');
    }
}
