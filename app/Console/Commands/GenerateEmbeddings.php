<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentEmbeddingService;
use Illuminate\Support\Facades\DB;

class GenerateEmbeddings extends Command
{
    protected $signature = 'embeddings:generate 
                            {--type=all : Type of embeddings to generate (all, bills, members, actions)}
                            {--force : Force regeneration of existing embeddings}
                            {--batch-size=50 : Number of items to process in each batch}';
                            
    protected $description = 'Generate embeddings for all database content';

    public function handle(DocumentEmbeddingService $embeddingService)
    {
        $type = $this->option('type');
        $force = $this->option('force');
        
        $this->info('Starting embedding generation...');
        
        if ($force) {
            $this->warn('Force mode: Existing embeddings will be overwritten');
        }
        
        $totalStats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        // Generate bill embeddings
        if ($type === 'all' || $type === 'bills') {
            $this->info("\n📄 Generating bill embeddings...");
            $this->processWithTimer(function() use ($embeddingService, &$totalStats) {
                $stats = $embeddingService->embedAllBills();
                $this->mergeStats($totalStats, $stats);
                return $stats;
            }, 'bills');
        }
        
        // Generate member embeddings
        if ($type === 'all' || $type === 'members') {
            $this->info("\n👥 Generating member embeddings...");
            $this->processWithTimer(function() use ($embeddingService, &$totalStats) {
                $stats = $embeddingService->embedAllMembers();
                $this->mergeStats($totalStats, $stats);
                return $stats;
            }, 'members');
        }
        
        // Generate action embeddings
        if ($type === 'all' || $type === 'actions') {
            $this->info("\n⚡ Generating action embeddings...");
            $this->processWithTimer(function() use ($embeddingService, &$totalStats) {
                $stats = $embeddingService->embedBillActions();
                $this->mergeStats($totalStats, $stats);
                return $stats;
            }, 'actions');
        }
        
        // Final summary
        $this->info("\n" . str_repeat('=', 60));
        $this->info('📊 EMBEDDING GENERATION COMPLETE');
        $this->info(str_repeat('=', 60));
        $this->info("✅ Total Processed: {$totalStats['processed']}");
        $this->info("✅ Successful: {$totalStats['success']}");
        $this->info("❌ Failed: {$totalStats['failed']}");
        
        $successRate = $totalStats['processed'] > 0 ? 
            round(($totalStats['success'] / $totalStats['processed']) * 100, 2) : 0;
        $this->info("📈 Success Rate: {$successRate}%");
        
        // Show storage stats
        $this->showStorageStats();
        
        return 0;
    }
    
    protected function processWithTimer(callable $callback, string $type): array
    {
        $startTime = microtime(true);
        $stats = $callback();
        $endTime = microtime(true);
        
        $duration = round($endTime - $startTime, 2);
        
        $this->info("\n✅ {$type} completed in {$duration}s");
        $this->info("   Processed: {$stats['processed']} | Success: {$stats['success']} | Failed: {$stats['failed']}");
        
        return $stats;
    }
    
    protected function mergeStats(array &$total, array $stats): void
    {
        $total['processed'] += $stats['processed'];
        $total['success'] += $stats['success'];
        $total['failed'] += $stats['failed'];
    }
    
    protected function showStorageStats(): void
    {
        $this->info("\n📊 STORAGE STATISTICS");
        $this->info(str_repeat('-', 40));
        
        $stats = DB::table('embeddings')
            ->select('entity_type', DB::raw('COUNT(*) as count'))
            ->groupBy('entity_type')
            ->get();
            
        foreach ($stats as $stat) {
            $this->info("   {$stat->entity_type}: {$stat->count} embeddings");
        }
        
        $total = DB::table('embeddings')->count();
        $this->info("   TOTAL: {$total} embeddings");
        
        // Estimate storage size
        $avgEmbeddingSize = 1536 * 4; // 1536 dimensions * 4 bytes per float
        $estimatedSize = ($total * $avgEmbeddingSize) / (1024 * 1024); // MB
        $this->info("   Estimated Size: " . round($estimatedSize, 2) . " MB");
    }
}