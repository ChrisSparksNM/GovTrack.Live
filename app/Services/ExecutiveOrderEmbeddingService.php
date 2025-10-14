<?php

namespace App\Services;

use App\Models\ExecutiveOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecutiveOrderEmbeddingService
{
    private EmbeddingService $embeddingService;
    
    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Generate embeddings for all executive orders
     */
    public function embedAllExecutiveOrders(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        $totalOrders = ExecutiveOrder::fullyScraped()->count();
        echo "📊 Starting executive order embedding generation for {$totalOrders} orders...\n";
        
        ExecutiveOrder::fullyScraped()
            ->chunk(25, function ($orders) use (&$stats, $totalOrders) {
                foreach ($orders as $order) {
                    $stats['processed']++;
                    
                    // Show progress
                    $progress = round(($stats['processed'] / $totalOrders) * 100, 1);
                    echo "\r🔄 Processing order {$stats['processed']}/{$totalOrders} ({$progress}%) - {$order->display_name}";
                    
                    $content = $this->buildExecutiveOrderContent($order);
                    $embedding = $this->embeddingService->generateEmbedding($content);
                    
                    if ($embedding) {
                        try {
                            $metadata = $this->buildExecutiveOrderMetadata($order);
                            $success = $this->embeddingService->storeEmbedding(
                                'executive_order', 
                                $order->id, 
                                $embedding, 
                                $content, 
                                $metadata
                            );
                            
                            if ($success) {
                                $stats['success']++;
                            } else {
                                echo "\n❌ Failed to store embedding for order {$order->id}\n";
                                $stats['failed']++;
                            }
                        } catch (\Exception $e) {
                            echo "\n❌ Failed to process order {$order->id}: {$e->getMessage()}\n";
                            $stats['failed']++;
                        }
                    } else {
                        echo "\n❌ Failed to generate embedding for order {$order->id}\n";
                        $stats['failed']++;
                    }
                    
                    // Show milestone updates
                    if ($stats['processed'] % 10 === 0) {
                        echo "\n✅ Milestone: {$stats['processed']} orders processed ({$stats['success']} successful, {$stats['failed']} failed)\n";
                    }
                }
            });
            
        echo "\n🎉 Executive order embedding generation complete!\n";
        echo "📊 Final Results: {$stats['processed']} processed, {$stats['success']} successful, {$stats['failed']} failed\n";
        
        return $stats;
    }

    /**
     * Build comprehensive content for executive order embedding
     */
    private function buildExecutiveOrderContent(ExecutiveOrder $order): string
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

    /**
     * Build metadata for executive order embedding
     */
    private function buildExecutiveOrderMetadata(ExecutiveOrder $order): array
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

    /**
     * Get embedding statistics for executive orders
     */
    public function getEmbeddingStats(): array
    {
        $totalOrders = ExecutiveOrder::count();
        $fullyScraped = ExecutiveOrder::fullyScraped()->count();
        $embeddingsCount = DB::table('embeddings')->where('entity_type', 'executive_order')->count();
        
        return [
            'total_orders' => $totalOrders,
            'fully_scraped' => $fullyScraped,
            'embeddings_generated' => $embeddingsCount,
            'missing_embeddings' => $fullyScraped - $embeddingsCount,
            'recent_orders' => ExecutiveOrder::where('signed_date', '>=', now()->subDays(30))->count(),
            'current_year_orders' => ExecutiveOrder::whereYear('signed_date', now()->year)->count(),
        ];
    }

    /**
     * Generate embeddings for recent executive orders only
     */
    public function embedRecentExecutiveOrders(int $days = 30): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        $recentOrders = ExecutiveOrder::fullyScraped()
            ->where('signed_date', '>=', now()->subDays($days))
            ->get();
        
        echo "📊 Generating embeddings for {$recentOrders->count()} recent executive orders (last {$days} days)...\n";
        
        foreach ($recentOrders as $order) {
            $stats['processed']++;
            
            echo "🔄 Processing: {$order->display_name}\n";
            
            $content = $this->buildExecutiveOrderContent($order);
            $embedding = $this->embeddingService->generateEmbedding($content);
            
            if ($embedding) {
                $metadata = $this->buildExecutiveOrderMetadata($order);
                $success = $this->embeddingService->storeEmbedding(
                    'executive_order', 
                    $order->id, 
                    $embedding, 
                    $content, 
                    $metadata
                );
                
                if ($success) {
                    $stats['success']++;
                    echo "   ✅ Success\n";
                } else {
                    $stats['failed']++;
                    echo "   ❌ Failed to store\n";
                }
            } else {
                $stats['failed']++;
                echo "   ❌ Failed to generate embedding\n";
            }
        }
        
        echo "🎉 Recent executive orders embedding complete!\n";
        echo "📊 Results: {$stats['processed']} processed, {$stats['success']} successful, {$stats['failed']} failed\n";
        
        return $stats;
    }
}