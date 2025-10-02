<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Common\Files\Telegram\TelegramRetryService;

/**
 * Phase 8.4: Process Telegram Retry Queue
 * 
 * Command برای پردازش خودکار retry queue
 * 
 * Usage:
 *   php artisan telegram:process-retries
 *   php artisan telegram:process-retries --limit=20
 */
class ProcessTelegramRetries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:process-retries 
                            {--limit=10 : Maximum number of retries to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process pending Telegram upload retries';

    protected TelegramRetryService $retryService;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->retryService = new TelegramRetryService();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Processing Telegram retry queue (limit: {$limit})...");

        $results = $this->retryService->processRetryQueue($limit);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $results['processed']],
                ['Succeeded', $results['succeeded']],
                ['Scheduled', $results['scheduled']],
                ['Failed', $results['failed']],
            ]
        );

        if ($results['processed'] === 0) {
            $this->info('No retries to process.');
        } else {
            $this->info("Processed {$results['processed']} retry attempts.");
        }

        return self::SUCCESS;
    }
}
