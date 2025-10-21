<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentEmbeddingService;
use App\Models\Bill;
use App\Models\Law;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateRecentEmbeddings extends Command
{
    protected $signature = 'embeddings:generate-recent 
                            {--days=1 : Number of days back to look for updated content}
                            {--type=all : Type of embeddings to generate (all, bills, laws)}
                            {--batch-size=50 : Number of items to process in each batch}';
                            
    protected $description = 'Generate embeddings for recently updated bills and laws';

    public function handle(DocumentEmbeddingService $embeddingService)
    {
        $days = (int) $this->option('days');
        $type = $this->option('type');
        $batchSize = (int) $this->option('batch-size');
        
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->info("Generating embeddings for content updated since: {$cutoffDate->format('Y-m-d H:i:s')}");
        
        $totalStats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        // Generate embeddings for recently updated bills
        if ($type === 'all' || $type === 'bills') {
            $this->info("\n📄 Processing recently updated bills...");
            
            $recentBills = Bill::where('updated_at', '>=', $cutoffDate)
                ->whereNotNull('title')
                ->get();
                
            $this->info("Found {$recentBills->count()} recently updated bills");
            
            if ($recentBills->count() > 0) {
                $stats = $this->processRecentBills($embeddingService, $recentBills, $batchSize);
                $this->mergeStats($totalStats, $stats);
            }
        }
        
        // Generate embeddings for recently updated laws
        if ($type === 'all' || $type === 'laws') {
            $this->info("\n⚖️ Processing recently updated laws...");
            
            $recentLaws = Law::where('updated_at', '>=', $cutoffDate)
                ->whereNotNull('title')
                ->get();
                
            $this->info("Found {$recentLaws->count()} recently updated laws");
            
            if ($recentLaws->count() > 0) {
                $stats = $this->processRecentLaws($embeddingService, $recentLaws, $batchSize);
                $this->mergeStats($totalStats, $stats);
            }
        }
        
        // Final summary
        $this->info("\n" . str_repeat('=', 60));
        $this->info('📊 RECENT EMBEDDING GENERATION COMPLETE');
        $this->info(str_repeat('=', 60));
        $this->info("✅ Total Processed: {$totalStats['processed']}");
        $this->info("✅ Successful: {$totalStats['success']}");
        $this->info("❌ Failed: {$totalStats['failed']}");
        
        if ($totalStats['processed'] === 0) {
            $this->info("🎉 No recent updates found - embeddings are up to date!");
        }
        
        return 0;
    }
    
    protected function processRecentBills(DocumentEmbeddingService $embeddingService, $bills, int $batchSize): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        foreach ($bills->chunk($batchSize) as $chunk) {
            foreach ($chunk as $bill) {
                try {
                    $stats['processed']++;
                    
                    // Check if embedding already exists and is recent
                    $existingEmbedding = DB::table('embeddings')
                        ->where('entity_type', 'bill')
                        ->where('entity_id', $bill->id)
                        ->where('updated_at', '>=', $bill->updated_at)
                        ->first();
                        
                    if ($existingEmbedding) {
                        $this->line("Skipping bill {$bill->congress_id} - embedding is up to date");
                        $stats['success']++;
                        continue;
                    }
                    
                    // Generate embedding for this bill
                    $result = $embeddingService->embedBill($bill);
                    
                    if ($result) {
                        $stats['success']++;
                        $this->line("✅ Generated embedding for bill: {$bill->congress_id}");
                    } else {
                        $stats['failed']++;
                        $this->error("❌ Failed to generate embedding for bill: {$bill->congress_id}");
                    }
                    
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $this->error("❌ Error processing bill {$bill->congress_id}: " . $e->getMessage());
                }
            }
            
            // Show progress
            $this->info("Processed {$stats['processed']} bills so far...");
        }
        
        return $stats;
    }
    
    protected function processRecentLaws(DocumentEmbeddingService $embeddingService, $laws, int $batchSize): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        foreach ($laws->chunk($batchSize) as $chunk) {
            foreach ($chunk as $law) {
                try {
                    $stats['processed']++;
                    
                    // Check if embedding already exists and is recent
                    $existingEmbedding = DB::table('embeddings')
                        ->where('entity_type', 'law')
                        ->where('entity_id', $law->id)
                        ->where('updated_at', '>=', $law->updated_at)
                        ->first();
                        
                    if ($existingEmbedding) {
                        $this->line("Skipping law {$law->congress_id} - embedding is up to date");
                        $stats['success']++;
                        continue;
                    }
                    
                    // Generate embedding for this law (treat as bill for embedding purposes)
                    $result = $embeddingService->embedLaw($law);
                    
                    if ($result) {
                        $stats['success']++;
                        $this->line("✅ Generated embedding for law: {$law->congress_id}");
                    } else {
                        $stats['failed']++;
                        $this->error("❌ Failed to generate embedding for law: {$law->congress_id}");
                    }
                    
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $this->error("❌ Error processing law {$law->congress_id}: " . $e->getMessage());
                }
            }
            
            // Show progress
            $this->info("Processed {$stats['processed']} laws so far...");
        }
        
        return $stats;
    }
    
    protected function mergeStats(array &$total, array $stats): void
    {
        $total['processed'] += $stats['processed'];
        $total['success'] += $stats['success'];
        $total['failed'] += $stats['failed'];
    }
}