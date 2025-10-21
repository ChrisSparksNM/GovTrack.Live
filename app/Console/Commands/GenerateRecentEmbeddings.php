<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DocumentEmbeddingService;
use App\Services\ExecutiveOrderEmbeddingService;
use App\Models\Bill;
use App\Models\Law;
use App\Models\ExecutiveOrder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateRecentEmbeddings extends Command
{
    protected $signature = 'embeddings:generate-recent 
                            {--days=1 : Number of days back to look for updated content}
                            {--type=all : Type of embeddings to generate (all, bills, laws, executive-orders)}
                            {--batch-size=50 : Number of items to process in each batch}';
                            
    protected $description = 'Generate embeddings for recently updated bills, laws, and executive orders';

    public function handle(DocumentEmbeddingService $embeddingService, ExecutiveOrderEmbeddingService $executiveOrderEmbeddingService)
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
        
        // Generate embeddings for recently updated executive orders
        if ($type === 'all' || $type === 'executive-orders') {
            $this->info("\n🏛️ Processing recently updated executive orders...");
            
            $recentOrders = ExecutiveOrder::where('updated_at', '>=', $cutoffDate)
                ->whereNotNull('title')
                ->fullyScraped()
                ->get();
                
            $this->info("Found {$recentOrders->count()} recently updated executive orders");
            
            if ($recentOrders->count() > 0) {
                $stats = $this->processRecentExecutiveOrders($executiveOrderEmbeddingService, $recentOrders, $batchSize);
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
    
    protected function processRecentExecutiveOrders(ExecutiveOrderEmbeddingService $embeddingService, $orders, int $batchSize): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        foreach ($orders->chunk($batchSize) as $chunk) {
            foreach ($chunk as $order) {
                try {
                    $stats['processed']++;
                    
                    // Check if embedding already exists and is recent
                    $existingEmbedding = DB::table('embeddings')
                        ->where('entity_type', 'executive_order')
                        ->where('entity_id', $order->id)
                        ->where('updated_at', '>=', $order->updated_at)
                        ->first();
                        
                    if ($existingEmbedding) {
                        $this->line("Skipping executive order {$order->display_name} - embedding is up to date");
                        $stats['success']++;
                        continue;
                    }
                    
                    // Generate embedding for this executive order
                    $content = $this->buildExecutiveOrderContent($order);
                    $embedding = $embeddingService->embeddingService->generateEmbedding($content);
                    
                    if ($embedding) {
                        $metadata = $this->buildExecutiveOrderMetadata($order);
                        $result = $embeddingService->embeddingService->storeEmbedding(
                            'executive_order', 
                            $order->id, 
                            $embedding, 
                            $content, 
                            $metadata
                        );
                        
                        if ($result) {
                            $stats['success']++;
                            $this->line("✅ Generated embedding for executive order: {$order->display_name}");
                        } else {
                            $stats['failed']++;
                            $this->error("❌ Failed to store embedding for executive order: {$order->display_name}");
                        }
                    } else {
                        $stats['failed']++;
                        $this->error("❌ Failed to generate embedding for executive order: {$order->display_name}");
                    }
                    
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $this->error("❌ Error processing executive order {$order->display_name}: " . $e->getMessage());
                }
            }
            
            // Show progress
            $this->info("Processed {$stats['processed']} executive orders so far...");
        }
        
        return $stats;
    }
    
    protected function buildExecutiveOrderContent(ExecutiveOrder $order): string
    {
        $content = [];
        
        // Emphasize recent orders
        $year = $order->signed_date->year;
        $isRecent = $year >= 2024;
        
        if ($isRecent) {
            $content[] = "RECENT {$year} EXECUTIVE ORDER: {$order->display_name}";
        } else {
            $content[] = "EXECUTIVE ORDER {$year}: {$order->display_name}";
        }
        
        // Add title
        $content[] = "Title: {$order->title}";
        
        // Add order number if available
        if ($order->order_number) {
            $content[] = "Order Number: {$order->order_number}";
        }
        
        // Add signed date with emphasis
        $content[] = "Signed: {$order->signed_date->format('F j, Y')} ({$year})";
        
        // Add status
        $content[] = "Status: {$order->status}";
        
        // Add summary if available
        if ($order->summary) {
            $content[] = "Summary: {$order->summary}";
        }
        
        // Add topics if available
        if ($order->topics && count($order->topics) > 0) {
            $topics = implode(', ', $order->topics);
            $content[] = "Topics: {$topics}";
        }
        
        // Add content (truncated to avoid token limits)
        if ($order->content) {
            $truncatedContent = substr($order->content, 0, 2000);
            $content[] = "Content: {$truncatedContent}";
        }
        
        // Add AI summary if available
        if ($order->ai_summary) {
            $content[] = "AI Summary: {$order->ai_summary}";
        }
        
        // Add temporal keywords for better search
        if ($isRecent) {
            $content[] = "Keywords: recent executive order, current administration, {$year} policy";
        } else {
            $content[] = "Keywords: executive order, presidential action, {$year} policy";
        }
        
        return implode("\n", $content);
    }
    
    protected function buildExecutiveOrderMetadata(ExecutiveOrder $order): array
    {
        return [
            'order_number' => $order->order_number,
            'title' => $order->title,
            'signed_date' => $order->signed_date->toDateString(),
            'year' => $order->signed_date->year,
            'is_recent' => $order->signed_date->year >= 2024,
            'status' => $order->status,
            'topics' => $order->topics ?? [],
            'word_count' => $order->word_count,
            'url' => $order->url,
        ];
    }
    
    protected function mergeStats(array &$total, array $stats): void
    {
        $total['processed'] += $stats['processed'];
        $total['success'] += $stats['success'];
        $total['failed'] += $stats['failed'];
    }
}