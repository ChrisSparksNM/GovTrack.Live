<?php
/**
 * Helper script to migrate from SQLite to MySQL on Laravel Forge
 * 
 * This script helps you transition your local SQLite data to MySQL on Forge
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseMigrator
{
    public function checkMySQLConnection()
    {
        try {
            DB::connection('mysql')->getPdo();
            echo "✅ MySQL connection successful!\n";
            return true;
        } catch (Exception $e) {
            echo "❌ MySQL connection failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function showTableCounts()
    {
        echo "\n📊 Current table counts:\n";
        echo str_repeat('-', 40) . "\n";
        
        $tables = [
            'bills', 'bill_actions', 'bill_summaries', 'bill_subjects',
            'bill_cosponsors', 'bill_sponsors', 'bill_text_versions',
            'members', 'tracked_bills', 'embeddings', 'users'
        ];

        foreach ($tables as $table) {
            try {
                if (Schema::hasTable($table)) {
                    $count = DB::table($table)->count();
                    echo sprintf("%-20s: %s\n", $table, number_format($count));
                } else {
                    echo sprintf("%-20s: Table not found\n", $table);
                }
            } catch (Exception $e) {
                echo sprintf("%-20s: Error - %s\n", $table, $e->getMessage());
            }
        }
    }

    public function runMigrations()
    {
        echo "\n🔄 Running migrations...\n";
        
        try {
            $output = shell_exec('php artisan migrate --force 2>&1');
            echo $output;
            echo "✅ Migrations completed!\n";
            return true;
        } catch (Exception $e) {
            echo "❌ Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }

    public function testScrapers()
    {
        echo "\n🧪 Testing scraper commands...\n";
        echo str_repeat('-', 40) . "\n";
        
        // Test database stats command
        echo "Testing database stats...\n";
        $output = shell_exec('php artisan db:stats 2>&1');
        echo $output . "\n";
        
        // Test member fetch (dry run)
        echo "Testing member fetch command...\n";
        $output = shell_exec('php artisan members:fetch-all-current --limit=1 2>&1');
        echo $output . "\n";
    }
}

// Run the migration helper
echo "🚀 Laravel Forge MySQL Migration Helper\n";
echo str_repeat('=', 50) . "\n";

$migrator = new DatabaseMigrator();

// Step 1: Check MySQL connection
if (!$migrator->checkMySQLConnection()) {
    echo "\n❌ Please check your database configuration in .env\n";
    exit(1);
}

// Step 2: Run migrations
if (!$migrator->runMigrations()) {
    echo "\n❌ Migration failed. Please check the errors above.\n";
    exit(1);
}

// Step 3: Show current state
$migrator->showTableCounts();

// Step 4: Test scrapers
$migrator->testScrapers();

echo "\n✅ Migration helper completed!\n";
echo "\n📝 Next steps:\n";
echo "1. Update your production .env file with the MySQL credentials\n";
echo "2. Run: php artisan migrate --force (on production)\n";
echo "3. Test scrapers: php artisan scrape:congress-bills --limit=1\n";
echo "4. Generate embeddings: php artisan embeddings:generate --type=bills\n";