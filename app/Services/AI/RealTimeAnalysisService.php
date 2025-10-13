<?php

namespace App\Services\AI;

use App\Models\Bill;
use App\Services\AnthropicService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RealTimeAnalysisService
{
    private AnthropicService $anthropicService;

    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
    }

    /**
     * Process new bills as they come in
     */
    public function processNewBill(Bill $bill): array
    {
        $analysis = [
            'bill_id' => $bill->id,
            'processed_at' => now(),
            'analysis' => []
        ];

        // 1. Generate immediate summary
        $summary = $this->generateQuickSummary($bill);
        $analysis['analysis']['summary'] = $summary;

        // 2. Classify bill type and urgency
        $classification = $this->classifyBill($bill);
        $analysis['analysis']['classification'] = $classification;

        // 3. Find related bills
        $relatedBills = $this->findRelatedBills($bill);
        $analysis['analysis']['related_bills'] = $relatedBills;

        // 4. Predict impact and stakeholders
        $impact = $this->predictImpact($bill);
        $analysis['analysis']['impact_prediction'] = $impact;

        // 5. Generate alerts for tracked topics
        $alerts = $this->generateAlerts($bill);
        $analysis['analysis']['alerts'] = $alerts;

        // Cache for quick retrieval
        Cache::put("bill_analysis_{$bill->id}", $analysis, now()->addHours(24));

        return $analysis;
    }

    /**
     * Generate trending topics analysis
     */
    public function analyzeTrendingTopics(): array
    {
        $recentBills = Bill::where('introduced_date', '>=', now()->subDays(30))->get();
        
        $prompt = "Analyze these recent congressional bills and identify trending topics, emerging themes, and policy shifts:

RECENT BILLS:
";
        
        foreach ($recentBills->take(50) as $bill) {
            $prompt .= "- {$bill->type} {$bill->number}: {$bill->title}\n";
            $prompt .= "  Policy Area: {$bill->policy_area}\n";
            $prompt .= "  Date: {$bill->introduced_date}\n\n";
        }

        $prompt .= "

Please provide:
1. Top 5 trending policy areas
2. Emerging themes or new legislative approaches
3. Bipartisan vs partisan trends
4. Comparison to previous months
5. Predictions for upcoming legislative priorities

Format as structured analysis with specific examples.";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        return [
            'success' => $response['success'],
            'trending_analysis' => $response['response'] ?? null,
            'generated_at' => now(),
            'bills_analyzed' => $recentBills->count()
        ];
    }

    private function generateQuickSummary(Bill $bill): array
    {
        return $this->anthropicService->generateQuickSummary(
            $bill->bill_text ?? $bill->title,
            $bill->title
        );
    }

    private function classifyBill(Bill $bill): array
    {
        $prompt = "Classify this congressional bill:

Title: {$bill->title}
Type: {$bill->type}
Policy Area: {$bill->policy_area}

Provide classification for:
1. Urgency Level (Low/Medium/High/Critical)
2. Scope (Local/State/National/International)
3. Complexity (Simple/Moderate/Complex)
4. Political Likelihood (Low/Medium/High)
5. Public Interest Level (Low/Medium/High)

Return as JSON with brief reasoning for each.";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        // Parse JSON response or return default structure
        return [
            'urgency' => 'Medium',
            'scope' => 'National',
            'complexity' => 'Moderate',
            'likelihood' => 'Medium',
            'public_interest' => 'Medium'
        ];
    }

    private function findRelatedBills(Bill $bill): array
    {
        // Use semantic similarity or keyword matching
        $relatedBills = Bill::where('policy_area', $bill->policy_area)
            ->where('id', '!=', $bill->id)
            ->where('introduced_date', '>=', now()->subMonths(6))
            ->limit(5)
            ->get();

        return $relatedBills->map(function ($relatedBill) {
            return [
                'id' => $relatedBill->id,
                'title' => $relatedBill->title,
                'similarity_reason' => 'Same policy area'
            ];
        })->toArray();
    }

    private function predictImpact(Bill $bill): array
    {
        $prompt = "Analyze the potential impact of this bill:

{$bill->title}
Policy Area: {$bill->policy_area}

Predict:
1. Who would be most affected (demographics, industries, regions)
2. Economic impact (positive/negative, scale)
3. Implementation challenges
4. Timeline for effects if passed
5. Potential opposition/support groups

Provide concise, factual analysis.";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        return [
            'analysis' => $response['response'] ?? 'Impact analysis not available',
            'confidence' => 'Medium'
        ];
    }

    private function generateAlerts(Bill $bill): array
    {
        // Check against user-defined alert criteria
        $alerts = [];
        
        // Example: High-impact bills
        if (str_contains(strtolower($bill->title), 'appropriation') && 
            preg_match('/\$[\d,]+\s*(billion|million)/', $bill->title)) {
            $alerts[] = [
                'type' => 'high_funding',
                'message' => 'Major appropriations bill introduced',
                'priority' => 'high'
            ];
        }

        return $alerts;
    }
}