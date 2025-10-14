<?php
/**
 * Script to clean up duplicate entries and test the scraper
 */

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DuplicateFixer
{
    public function cleanupDuplicates()
    {
        echo "🧹 Cleaning up duplicate entries...\n";
        
        // Clean up duplicate bill_text_versions
        $this->cleanupBillTextVersions();
        
        // Clean up duplicate bill_cosponsors
        $this->cleanupBillCosponsors();
        
        // Clean up duplicate bill_subjects
        $this->cleanupBillSubjects();
        
        echo "✅ Cleanup completed!\n";
    }
    
    private function cleanupBillTextVersions()
    {
        echo "Cleaning bill_text_versions duplicates...\n";
        
        $duplicates = DB::select("
            SELECT bill_id, type, date, COUNT(*) as count 
            FROM bill_text_versions 
            GROUP BY bill_id, type, date 
            HAVING COUNT(*) > 1
        ");
        
        foreach ($duplicates as $duplicate) {
            echo "  Found {$duplicate->count} duplicates for bill_id={$duplicate->bill_id}, type={$duplicate->type}\n";
            
            // Keep the first one, delete the rest
            $ids = DB::table('bill_text_versions')
                ->where('bill_id', $duplicate->bill_id)
                ->where('type', $duplicate->type)
                ->where('date', $duplicate->date)
                ->pluck('id')
                ->toArray();
            
            if (count($ids) > 1) {
                $keepId = array_shift($ids);
                DB::table('bill_text_versions')->whereIn('id', $ids)->delete();
                echo "    Kept ID {$keepId}, deleted " . count($ids) . " duplicates\n";
            }
        }
    }
    
    private function cleanupBillCosponsors()
    {
        echo "Cleaning bill_cosponsors duplicates...\n";
        
        $duplicates = DB::select("
            SELECT bill_id, bioguide_id, COUNT(*) as count 
            FROM bill_cosponsors 
            WHERE bioguide_id IS NOT NULL
            GROUP BY bill_id, bioguide_id 
            HAVING COUNT(*) > 1
        ");
        
        foreach ($duplicates as $duplicate) {
            echo "  Found {$duplicate->count} duplicates for bill_id={$duplicate->bill_id}, bioguide_id={$duplicate->bioguide_id}\n";
            
            $ids = DB::table('bill_cosponsors')
                ->where('bill_id', $duplicate->bill_id)
                ->where('bioguide_id', $duplicate->bioguide_id)
                ->pluck('id')
                ->toArray();
            
            if (count($ids) > 1) {
                $keepId = array_shift($ids);
                DB::table('bill_cosponsors')->whereIn('id', $ids)->delete();
                echo "    Kept ID {$keepId}, deleted " . count($ids) . " duplicates\n";
            }
        }
    }
    
    private function cleanupBillSubjects()
    {
        echo "Cleaning bill_subjects duplicates...\n";
        
        $duplicates = DB::select("
            SELECT bill_id, name, type, COUNT(*) as count 
            FROM bill_subjects 
            GROUP BY bill_id, name, type 
            HAVING COUNT(*) > 1
        ");
        
        foreach ($duplicates as $duplicate) {
            echo "  Found {$duplicate->count} duplicates for bill_id={$duplicate->bill_id}, name={$duplicate->name}\n";
            
            $ids = DB::table('bill_subjects')
                ->where('bill_id', $duplicate->bill_id)
                ->where('name', $duplicate->name)
                ->where('type', $duplicate->type)
                ->pluck('id')
                ->toArray();
            
            if (count($ids) > 1) {
                $keepId = array_shift($ids);
                DB::table('bill_subjects')->whereIn('id', $ids)->delete();
                echo "    Kept ID {$keepId}, deleted " . count($ids) . " duplicates\n";
            }
        }
    }
    
    public function testScraper()
    {
        echo "\n🧪 Testing scraper with a small batch...\n";
        
        $output = shell_exec('php artisan scrape:congress-bills --congress=119 --limit=2 --skip-existing 2>&1');
        echo $output;
        
        echo "\n📊 Current database stats:\n";
        $output = shell_exec('php artisan db:stats 2>&1');
        echo $output;
    }
}

// Run the duplicate fixer
echo "🔧 Duplicate Entry Fixer\n";
echo str_repeat('=', 40) . "\n";

$fixer = new DuplicateFixer();

// Clean up duplicates
$fixer->cleanupDuplicates();

// Test the scraper
$fixer->testScraper();

echo "\n✅ All done! You can now run the scraper safely.\n";