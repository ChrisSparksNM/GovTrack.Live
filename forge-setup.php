<?php
/**
 * Laravel Forge Server Setup Script
 * Run this on your Forge server to verify everything is configured correctly
 */

class ForgeSetup
{
    public function checkEnvironment()
    {
        echo "🔍 Checking Environment Configuration...\n";
        echo str_repeat('-', 50) . "\n";
        
        // Check if .env exists
        if (!file_exists('.env')) {
            echo "❌ .env file not found!\n";
            echo "   Copy .env.example to .env and configure it\n";
            return false;
        }
        
        echo "✅ .env file exists\n";
        
        // Check database configuration
        $dbConnection = env('DB_CONNECTION');
        $dbHost = env('DB_HOST');
        $dbDatabase = env('DB_DATABASE');
        $dbUsername = env('DB_USERNAME');
        
        echo "Database Configuration:\n";
        echo "  Connection: {$dbConnection}\n";
        echo "  Host: {$dbHost}\n";
        echo "  Database: {$dbDatabase}\n";
        echo "  Username: {$dbUsername}\n";
        
        // Check API keys
        $congressKey = env('CONGRESS_API_KEY');
        $anthropicKey = env('ANTHROPIC_API_KEY');
        
        echo "\nAPI Keys:\n";
        echo "  Congress API: " . ($congressKey ? "✅ Set" : "❌ Missing") . "\n";
        echo "  Anthropic API: " . ($anthropicKey ? "✅ Set" : "❌ Missing") . "\n";
        
        return true;
    }
    
    public function testDatabaseConnection()
    {
        echo "\n🔌 Testing Database Connection...\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $pdo = new PDO(
                "mysql:host=" . env('DB_HOST') . ";dbname=" . env('DB_DATABASE'),
                env('DB_USERNAME'),
                env('DB_PASSWORD')
            );
            echo "✅ Database connection successful!\n";
            
            // Check if tables exist
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "📊 Found " . count($tables) . " tables in database\n";
            
            return true;
        } catch (Exception $e) {
            echo "❌ Database connection failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function runMigrations()
    {
        echo "\n🔄 Running Migrations...\n";
        echo str_repeat('-', 50) . "\n";
        
        $output = shell_exec('php artisan migrate --force 2>&1');
        echo $output;
        
        // Check migration status
        $output = shell_exec('php artisan migrate:status 2>&1');
        echo "\nMigration Status:\n";
        echo $output;
    }
    
    public function optimizeApplication()
    {
        echo "\n⚡ Optimizing Application...\n";
        echo str_repeat('-', 50) . "\n";
        
        $commands = [
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
            'php artisan optimize'
        ];
        
        foreach ($commands as $command) {
            echo "Running: {$command}\n";
            $output = shell_exec($command . ' 2>&1');
            echo $output . "\n";
        }
    }
    
    public function testScrapers()
    {
        echo "\n🧪 Testing Scrapers...\n";
        echo str_repeat('-', 50) . "\n";
        
        // Test database stats
        echo "Database Stats:\n";
        $output = shell_exec('php artisan db:stats 2>&1');
        echo $output . "\n";
        
        // Test member fetch (small batch)
        echo "Testing Member Fetch:\n";
        $output = shell_exec('php artisan members:fetch-all-current --limit=2 2>&1');
        echo $output . "\n";
        
        // Test bill scraper (small batch)
        echo "Testing Bill Scraper:\n";
        $output = shell_exec('php artisan scrape:congress-bills --limit=2 --congress=119 2>&1');
        echo $output . "\n";
    }
    
    public function showNextSteps()
    {
        echo "\n📝 Next Steps:\n";
        echo str_repeat('=', 50) . "\n";
        echo "1. If everything looks good, run full scrapers:\n";
        echo "   php artisan members:fetch-all-current\n";
        echo "   php artisan scrape:congress-bills --congress=119 --batch-size=50\n\n";
        echo "2. Generate embeddings for search:\n";
        echo "   php artisan embeddings:generate --type=bills\n\n";
        echo "3. Set up scheduled tasks in Forge:\n";
        echo "   - Daily: php artisan scrape:congress-updates\n";
        echo "   - Weekly: php artisan members:fetch-missing-profiles\n\n";
        echo "4. Monitor logs:\n";
        echo "   tail -f storage/logs/laravel.log\n";
    }
}

// Load Laravel environment
if (file_exists('bootstrap/app.php')) {
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
}

// Run setup
echo "🚀 Laravel Forge Setup Script\n";
echo str_repeat('=', 50) . "\n";

$setup = new ForgeSetup();

// Step 1: Check environment
if (!$setup->checkEnvironment()) {
    echo "\n❌ Environment check failed. Please fix configuration first.\n";
    exit(1);
}

// Step 2: Test database
if (!$setup->testDatabaseConnection()) {
    echo "\n❌ Database connection failed. Please check credentials.\n";
    exit(1);
}

// Step 3: Run migrations
$setup->runMigrations();

// Step 4: Optimize application
$setup->optimizeApplication();

// Step 5: Test scrapers
$setup->testScrapers();

// Step 6: Show next steps
$setup->showNextSteps();

echo "\n✅ Forge setup completed!\n";