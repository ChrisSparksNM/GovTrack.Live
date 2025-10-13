<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ClaudeSemanticService;

class TestClaudeSearch extends Command
{
    protected $signature = 'test:claude-search 
                            {query? : Search query to test}
                            {--type=all : Entity type to search (all, bills, members, actions)}
                            {--limit=10 : Number of results to show}
                            {--threshold=0.3 : Similarity threshold}
                            {--hybrid : Use hybrid search with enhanced analysis}';
                            
    protected $description = 'Test Claude-based semantic search functionality';

    public function handle(ClaudeSemanticService $claudeService)
    {
        $query = $this->argument('query');
        
        if (!$query) {
            $query = $this->ask('What would you like to search for?');
        }
        
        if (!$query) {
            $this->error('No query provided');
            return 1;
        }
        
        $type = $this->option('type');
        $limit = (int) $this->option('limit');
        $threshold = (float) $this->option('threshold');
        $useHybrid = $this->option('hybrid');
        
        $this->info("🧠 Claude Semantic Search: \"{$query}\"");
        $this->info("   Type: {$type} | Limit: {$limit} | Threshold: {$threshold} | Hybrid: " . ($useHybrid ? 'Yes' : 'No'));
        $this->info(str_repeat('-', 70));
        
        $startTime = microtime(true);
        
        // Perform search
        if ($useHybrid) {
            $results = $claudeService->hybridSearch($query, [
                'entity_types' => $type === 'all' ? ['bill', 'member', 'bill_action'] : [$type],
                'limit' => $limit,
                'threshold' => $threshold
            ]);
        } else {
            $results = $claudeService->semanticSearch($query, [
                'entity_types' => $type === 'all' ? ['bill', 'member', 'bill_action'] : [$type],
                'limit' => $limit,
                'threshold' => $threshold
            ]);
        }
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        if (!$results['success']) {
            $this->error("❌ Search failed: {$results['error']}");
            return 1;
        }
        
        // Show query analysis
        if (isset($results['query_fingerprint'])) {
            $this->info("🔍 Query Analysis:");
            $fingerprint = $results['query_fingerprint'];
            
            if (!empty($fingerprint['topics'])) {
                $this->line("   Topics: " . implode(', ', $fingerprint['topics']));
            }
            if (!empty($fingerprint['policy_areas'])) {
                $this->line("   Policy Areas: " . implode(', ', $fingerprint['policy_areas']));
            }
            if (!empty($fingerprint['entities'])) {
                $this->line("   Entities: " . implode(', ', $fingerprint['entities']));
            }
            if (isset($fingerprint['scope'])) {
                $this->line("   Scope: {$fingerprint['scope']}");
            }
            $this->line("");
        }
        
        // Show enhanced query analysis if hybrid
        if (isset($results['query_analysis'])) {
            $analysis = $results['query_analysis'];
            $this->info("🎯 Enhanced Query Analysis:");
            $this->line("   Intent: {$analysis['intent_type']}");
            if (!empty($analysis['focus_areas'])) {
                $this->line("   Focus: " . implode(', ', $analysis['focus_areas']));
            }
            if ($analysis['geographic_scope']) {
                $this->line("   Geographic: {$analysis['geographic_scope']}");
            }
            $this->line("");
        }
        
        // Display results
        $this->info("📊 Found {$results['total_found']} results:");
        $this->info(str_repeat('-', 70));
        
        foreach ($results['results'] as $result) {
            $similarity = round($result['similarity'] * 100, 1);
            $type = ucfirst(str_replace('_', ' ', $result['entity_type']));
            
            $this->info("🎯 {$type} ({$similarity}% match)");
            
            // Show fingerprint highlights
            if (isset($result['fingerprint'])) {
                $fp = $result['fingerprint'];
                $highlights = [];
                
                if (!empty($fp['topics'])) {
                    $highlights[] = "Topics: " . implode(', ', array_slice($fp['topics'], 0, 3));
                }
                if (!empty($fp['policy_areas'])) {
                    $highlights[] = "Policy: " . implode(', ', array_slice($fp['policy_areas'], 0, 2));
                }
                
                if (!empty($highlights)) {
                    $this->line("   " . implode(' | ', $highlights));
                }
            }
            
            // Show content preview
            $content = substr($result['content'], 0, 120) . '...';
            $this->line("   {$content}");
            $this->line("");
        }
        
        $this->info("⏱️  Search completed in {$duration}ms");
        
        // Test related content
        if (!empty($results['results']) && $this->confirm('Show related content for first result?')) {
            $this->showRelatedContent($claudeService, $results['results'][0]);
        }
        
        return 0;
    }
    
    private function showRelatedContent(ClaudeSemanticService $claudeService, array $result): void
    {
        $this->info("\n🔗 Finding related content...");
        
        if (!isset($result['fingerprint'])) {
            $this->warn("No fingerprint available for related search");
            return;
        }
        
        $relatedResults = $claudeService->searchSimilar(
            $result['fingerprint'],
            null, // Search all types
            5,
            0.2 // Lower threshold for related content
        );
        
        // Remove the original result
        $relatedResults = array_filter($relatedResults, function($related) use ($result) {
            return !($related['entity_type'] === $result['entity_type'] && 
                    $related['entity_id'] === $result['entity_id']);
        });
        
        if (empty($relatedResults)) {
            $this->info("No related content found");
            return;
        }
        
        $this->info("Found " . count($relatedResults) . " related items:");
        $this->info(str_repeat('-', 50));
        
        foreach (array_slice($relatedResults, 0, 3) as $related) {
            $similarity = round($related['similarity'] * 100, 1);
            $type = ucfirst(str_replace('_', ' ', $related['entity_type']));
            
            $this->info("🔗 {$type} ({$similarity}% similar)");
            $content = substr($related['content'], 0, 80) . '...';
            $this->line("   {$content}");
            $this->line("");
        }
    }
}