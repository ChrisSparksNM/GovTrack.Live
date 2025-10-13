<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SemanticSearchService;

class TestSemanticSearch extends Command
{
    protected $signature = 'test:semantic-search 
                            {query? : Search query to test}
                            {--type=all : Entity type to search (all, bills, members, actions)}
                            {--limit=10 : Number of results to show}
                            {--threshold=0.7 : Similarity threshold}';
                            
    protected $description = 'Test semantic search functionality';

    public function handle(SemanticSearchService $searchService)
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
        
        $this->info("🔍 Searching for: \"{$query}\"");
        $this->info("   Type: {$type} | Limit: {$limit} | Threshold: {$threshold}");
        $this->info(str_repeat('-', 60));
        
        $startTime = microtime(true);
        
        // Perform search based on type
        if ($type === 'bills') {
            $results = $searchService->searchBills($query, [
                'limit' => $limit,
                'threshold' => $threshold
            ]);
            $this->displayBillResults($results);
        } elseif ($type === 'members') {
            $results = $searchService->searchMembers($query, [
                'limit' => $limit,
                'threshold' => $threshold
            ]);
            $this->displayMemberResults($results);
        } else {
            $results = $searchService->search($query, [
                'limit' => $limit,
                'threshold' => $threshold
            ]);
            $this->displayMixedResults($results);
        }
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $this->info("\n⏱️  Search completed in {$duration}ms");
        
        // Test related content if we have results
        if (!empty($results['results']) || !empty($results['bills']) || !empty($results['members'])) {
            $this->testRelatedContent($searchService, $results);
        }
        
        return 0;
    }
    
    private function displayBillResults(array $results): void
    {
        if (!$results['success']) {
            $this->error("Search failed: {$results['error']}");
            return;
        }
        
        $bills = $results['bills'] ?? [];
        
        $this->info("📄 Found {$results['total_found']} bills:");
        $this->info(str_repeat('-', 60));
        
        foreach ($bills as $result) {
            $bill = $result['model'];
            $similarity = round($result['similarity'] * 100, 1);
            
            $this->info("🏛️  {$bill->congress_id} ({$similarity}% match)");
            $this->line("   Title: " . substr($bill->title, 0, 80) . "...");
            
            if ($bill->sponsors->isNotEmpty()) {
                $sponsor = $bill->sponsors->first();
                $this->line("   Sponsor: {$sponsor->full_name} ({$sponsor->party}-{$sponsor->state})");
            }
            
            if ($bill->policy_area) {
                $this->line("   Policy: {$bill->policy_area}");
            }
            
            $this->line("");
        }
    }
    
    private function displayMemberResults(array $results): void
    {
        if (!$results['success']) {
            $this->error("Search failed: {$results['error']}");
            return;
        }
        
        $members = $results['members'] ?? [];
        
        $this->info("👥 Found {$results['total_found']} members:");
        $this->info(str_repeat('-', 60));
        
        foreach ($members as $result) {
            $member = $result['model'];
            $similarity = round($result['similarity'] * 100, 1);
            
            $this->info("🏛️  {$member->display_name} ({$similarity}% match)");
            $this->line("   {$member->current_party} | {$member->location_display}");
            $this->line("   {$member->chamber_display}");
            $this->line("   Sponsored: {$member->sponsored_legislation_count} | Cosponsored: {$member->cosponsored_legislation_count}");
            $this->line("");
        }
    }
    
    private function displayMixedResults(array $results): void
    {
        if (!$results['success']) {
            $this->error("Search failed: {$results['error']}");
            return;
        }
        
        $this->info("🔍 Found {$results['total_found']} results:");
        $this->info(str_repeat('-', 60));
        
        foreach ($results['results'] as $result) {
            $similarity = round($result['similarity'] * 100, 1);
            $type = ucfirst(str_replace('_', ' ', $result['entity_type']));
            
            $this->info("📋 {$type} ({$similarity}% match)");
            
            if ($result['entity_type'] === 'bill' && isset($result['model'])) {
                $bill = $result['model'];
                $this->line("   {$bill->congress_id}: " . substr($bill->title, 0, 60) . "...");
            } elseif ($result['entity_type'] === 'member' && isset($result['model'])) {
                $member = $result['model'];
                $this->line("   {$member->display_name} ({$member->current_party}-{$member->state})");
            } else {
                $this->line("   " . substr($result['content'], 0, 80) . "...");
            }
            
            $this->line("");
        }
    }
    
    private function testRelatedContent(SemanticSearchService $searchService, array $results): void
    {
        if (!$this->confirm('Would you like to see related content for the first result?')) {
            return;
        }
        
        // Get the first result
        $firstResult = null;
        if (!empty($results['results'])) {
            $firstResult = $results['results'][0];
        } elseif (!empty($results['bills'])) {
            $firstResult = $results['bills'][0];
        } elseif (!empty($results['members'])) {
            $firstResult = $results['members'][0];
        }
        
        if (!$firstResult) {
            return;
        }
        
        $this->info("\n🔗 Finding related content...");
        
        $relatedResults = $searchService->findRelated(
            $firstResult['entity_type'],
            $firstResult['entity_id'],
            5
        );
        
        if ($relatedResults['success']) {
            $this->info("Found " . count($relatedResults['related']) . " related items:");
            $this->info(str_repeat('-', 40));
            
            foreach ($relatedResults['related'] as $related) {
                $similarity = round($related['similarity'] * 100, 1);
                $type = ucfirst(str_replace('_', ' ', $related['entity_type']));
                
                $this->info("🔗 {$type} ({$similarity}% similar)");
                
                if (isset($related['model'])) {
                    if ($related['entity_type'] === 'bill') {
                        $bill = $related['model'];
                        $this->line("   {$bill->congress_id}: " . substr($bill->title, 0, 50) . "...");
                    } elseif ($related['entity_type'] === 'member') {
                        $member = $related['model'];
                        $this->line("   {$member->display_name} ({$member->current_party}-{$member->state})");
                    }
                }
                
                $this->line("");
            }
        } else {
            $this->error("Failed to find related content: {$relatedResults['error']}");
        }
    }
}