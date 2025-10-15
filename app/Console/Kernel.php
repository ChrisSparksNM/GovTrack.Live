<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily scraping schedule - runs at different times to avoid conflicts
        
        // 1. Executive Orders scraping - runs at 2:00 AM daily
        $schedule->command('executive-orders:scrape --pages=10')
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/executive-orders-scrape.log'));

        // 2. Congress Bills Updates - runs at 3:00 AM daily (checks for updates to existing bills)
        $schedule->command('scrape:congress-updates --days=7 --batch-size=100')
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/congress-updates.log'));

        // 3. New Congress Bills - runs at 4:00 AM daily (fetches new bills)
        $schedule->command('scrape:congress-bills --limit=500 --batch-size=50 --skip-existing')
                 ->dailyAt('04:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/congress-bills.log'));

        // 4. Full Congress Bills scrape - runs weekly on Sundays at 1:00 AM (comprehensive scrape)
        $schedule->command('scrape:congress-bills --limit=0 --batch-size=25')
                 ->weeklyOn(0, '01:00') // Sunday at 1:00 AM
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/congress-bills-full.log'));

        // 5. Database maintenance - runs at 5:00 AM daily
        $schedule->command('model:prune')
                 ->dailyAt('05:00')
                 ->runInBackground();

        // 6. Generate embeddings for new content - runs at 6:00 AM daily
        $schedule->command('generate:embeddings --limit=100')
                 ->dailyAt('06:00')
                 ->withoutOverlapping()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/embeddings.log'));

        // 7. Health check and statistics - runs every hour
        $schedule->command('show:database-stats')
                 ->hourly()
                 ->runInBackground()
                 ->appendOutputTo(storage_path('logs/health-check.log'));

        // 8. Clear old logs - runs weekly on Mondays at 6:00 AM
        $schedule->call(function () {
            $logFiles = [
                'executive-orders-scrape.log',
                'congress-updates.log',
                'congress-bills.log',
                'congress-bills-full.log',
                'embeddings.log',
                'health-check.log'
            ];
            
            foreach ($logFiles as $logFile) {
                $path = storage_path('logs/' . $logFile);
                if (file_exists($path) && filesize($path) > 50 * 1024 * 1024) { // 50MB
                    // Keep last 1000 lines
                    $lines = file($path);
                    if (count($lines) > 1000) {
                        $lastLines = array_slice($lines, -1000);
                        file_put_contents($path, implode('', $lastLines));
                    }
                }
            }
        })->weeklyOn(1, '06:00'); // Monday at 6:00 AM

        // 9. Emergency retry for failed scrapes - runs at 12:00 PM daily
        $schedule->call(function () {
            // Check if any scraping failed and retry with smaller batches
            $logPath = storage_path('logs/congress-bills.log');
            if (file_exists($logPath)) {
                $content = file_get_contents($logPath);
                if (strpos($content, 'ERROR') !== false || strpos($content, 'FAILED') !== false) {
                    \Artisan::call('scrape:congress-updates', ['--days' => 1, '--batch-size' => 10]);
                }
            }
        })->dailyAt('12:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}