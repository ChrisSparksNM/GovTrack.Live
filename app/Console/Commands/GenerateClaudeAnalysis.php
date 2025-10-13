<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ClaudeDocumentAnalysisService;
use Illuminate\Support\Facades\DB;

class GenerateClaudeAnalysis extends Command
{
    protected $signature = 'claude:analyze 
                            {--type=all : Type of analysis to generate (all, bills, members, actions)}
                            {--force : Force regeneration of existing analysis}
                            {--batch-size=25 : Number of items to process in each batch}';
                            
    protected $description = 'Generate Claude-based semantic analysis for all database content';

    public function handle(ClaudeDocumentAnalysisService $analysisService)
    {
        $type = $this->option('type');
        $force = $this->option('force');
        
        $this->info('🧠 Starting Claude semantic analysis...');
        
        if ($force) {
            $this->warn('Force mode: Existing analysis will be overwritten');
        }
        
        $totalStats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        // Analyze bills
        if ($type === 'all' || $type === 'bills') {
            $this->info("\n📄 Analyzing bills with Claude...");
            $this->processWithTimer(function() use ($analysisService, &$totalStats) {
                $stats = $analysisService->analyzeBills();
                $this->mergeStats($totalStats, $stats);
                return $stats;
            }, 'bills');
        }
        
        // Analyze members
        if ($type === 'all' || $type === 'members') {
            $this->info("\n👥 Analyzing members with Claude...");
            $this->processWithTimer(function() use ($analysisService, &$totalStats) {
                $stats = $analysisService->analyzeMembers();
                $this->mergeStats($totalStats, $stats);
                return $stats;
            }, 'members');
        }
        
        // Analyze actions
        if ($type === 'all' || $type === 'actions') {
            $this->info("\n⚡ Analyzing actions with Claude...");
            $this->processWithTimer(function() use ($analysisService, &$totalStats) {
                $stats = $analysisService->analyzeActions();
                $this->mergeStats($totalStats, $stats);
                return $stats;
            }, 'actions');
        }
        
        // Final summary
        $this->info("\n" . str_repeat('=', 60));
        $this->info('🧠 CLAUDE ANALYSIS COMPLETE');
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
        
        $stats = DB::table('semantic_fingerprints')
            ->select('entity_type', DB::raw('COUNT(*) as count'))
            ->groupBy('entity_type')
            ->get();
            
        foreach ($stats as $stat) {
            $this->info("   {$stat->entity_type}: {$stat->count} fingerprints");
        }
        
        $total = DB::table('semantic_fingerprints')->count();
        $this->info("   TOTAL: {$total} semantic fingerprints");
        
        // Show sample fingerprint structure
        $sample = DB::table('semantic_fingerprints')->first();
        if ($sample) {
            $fingerprint = json_decode($sample->fingerprint, true);
            $this->info("\n📋 Sample Fingerprint Structure:");
            foreach ($fingerprint as $key => $value) {
                $valueStr = is_array($value) ? '[' . implode(', ', array_slice($value, 0, 3)) . '...]' : $value;
                $this->info("   {$key}: {$valueStr}");
            }
        }
    }
}