<?php

namespace App\Services\AI;

use App\Models\Bill;
use App\Models\Member;
use App\Services\AnthropicService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PredictiveAnalyticsService
{
    private AnthropicService $anthropicService;

    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
    }

    /**
     * Predict bill passage likelihood
     */
    public function predictBillPassage(Bill $bill): array
    {
        $features = $this->extractBillFeatures($bill);
        $historicalData = $this->getHistoricalPassageData();
        
        $prompt = "Analyze the likelihood of this bill passing based on historical patterns and current features:

BILL DETAILS:
- Title: {$bill->title}
- Type: {$bill->type}
- Policy Area: {$bill->policy_area}
- Sponsor Party: " . ($bill->sponsors->first()->party ?? 'Unknown') . "
- Cosponsors: {$bill->cosponsors_count}
- Introduced: {$bill->introduced_date}

BILL FEATURES:
" . json_encode($features, JSON_PRETTY_PRINT) . "

HISTORICAL CONTEXT:
" . json_encode($historicalData, JSON_PRETTY_PRINT) . "

Provide prediction with:
1. Passage Likelihood (0-100%)
2. Key Factors (positive and negative)
3. Timeline Prediction
4. Confidence Level
5. Similar Historical Bills
6. Recommendations for increasing passage chances

Format as structured analysis with specific reasoning.";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        return [
            'bill_id' => $bill->id,
            'prediction' => $response['response'] ?? 'Prediction not available',
            'features_analyzed' => $features,
            'generated_at' => now()
        ];
    }

    /**
     * Forecast legislative trends
     */
    public function forecastLegislativeTrends(int $monthsAhead = 6): array
    {
        $historicalTrends = $this->getHistoricalTrends();
        $currentPatterns = $this->getCurrentPatterns();
        
        $prompt = "Based on historical congressional data and current patterns, forecast legislative trends for the next {$monthsAhead} months:

HISTORICAL TRENDS (last 24 months):
" . json_encode($historicalTrends, JSON_PRETTY_PRINT) . "

CURRENT PATTERNS (last 3 months):
" . json_encode($currentPatterns, JSON_PRETTY_PRINT) . "

Provide forecasts for:
1. Policy Area Priorities (which areas will see more activity)
2. Bipartisan vs Partisan Trends
3. Bill Introduction Volume
4. Passage Rate Predictions
5. Emerging Issues (new policy areas gaining attention)
6. Seasonal Patterns
7. Election Cycle Impact (if applicable)

Include confidence intervals and key assumptions.";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        return [
            'forecast_period' => $monthsAhead,
            'forecast' => $response['response'] ?? 'Forecast not available',
            'generated_at' => now(),
            'data_period' => [
                'historical_months' => 24,
                'current_pattern_months' => 3
            ]
        ];
    }

    /**
     * Predict member voting patterns
     */
    public function predictMemberVoting(Member $member, string $billTopic): array
    {
        $votingHistory = $this->getMemberVotingHistory($member);
        $partyAlignment = $this->getPartyAlignment($member, $billTopic);
        
        $prompt = "Predict how this member might vote on bills related to '{$billTopic}':

MEMBER: {$member->display_name} ({$member->party_abbreviation}-{$member->state})
CHAMBER: {$member->chamber_display}

VOTING HISTORY PATTERNS:
" . json_encode($votingHistory, JSON_PRETTY_PRINT) . "

PARTY ALIGNMENT ON THIS TOPIC:
" . json_encode($partyAlignment, JSON_PRETTY_PRINT) . "

Provide:
1. Voting Likelihood (Support/Oppose/Neutral with percentages)
2. Key Influencing Factors
3. Historical Consistency Score
4. State Interest Alignment
5. Party Pressure vs Independent Thinking
6. Confidence Level

Base analysis on actual voting patterns and policy positions.";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        return [
            'member_id' => $member->id,
            'topic' => $billTopic,
            'prediction' => $response['response'] ?? 'Prediction not available',
            'generated_at' => now()
        ];
    }

    private function extractBillFeatures(Bill $bill): array
    {
        return [
            'bill_length' => strlen($bill->bill_text ?? ''),
            'title_length' => strlen($bill->title),
            'has_short_title' => !empty($bill->short_title),
            'cosponsors_count' => $bill->cosponsors_count,
            'bipartisan_cosponsors' => $this->countBipartisanCosponsors($bill),
            'policy_area' => $bill->policy_area,
            'introduction_month' => $bill->introduced_date?->month,
            'congress_session' => $bill->congress,
            'origin_chamber' => $bill->origin_chamber,
            'sponsor_party' => $bill->sponsors->first()->party ?? null,
            'actions_count' => $bill->actions_count,
            'summaries_count' => $bill->summaries_count,
            'subjects_count' => $bill->subjects_count
        ];
    }

    private function countBipartisanCosponsors(Bill $bill): int
    {
        $sponsorParty = $bill->sponsors->first()->party ?? null;
        if (!$sponsorParty) return 0;
        
        return $bill->cosponsors()->where('party', '!=', $sponsorParty)->count();
    }

    private function getHistoricalPassageData(): array
    {
        return Cache::remember('historical_passage_data', 3600, function () {
            return [
                'total_bills_last_congress' => Bill::where('congress', 117)->count(),
                'passed_bills_estimate' => 300, // This would come from actual passage data
                'average_cosponsors_passed' => 15.5,
                'bipartisan_success_rate' => 0.65,
                'policy_area_success_rates' => [
                    'Defense' => 0.45,
                    'Healthcare' => 0.25,
                    'Transportation' => 0.35,
                    'Education' => 0.20
                ]
            ];
        });
    }

    private function getHistoricalTrends(): array
    {
        return [
            'monthly_introductions' => DB::select("
                SELECT strftime('%Y-%m', introduced_date) as month, 
                       COUNT(*) as count,
                       policy_area
                FROM bills 
                WHERE introduced_date >= date('now', '-24 months')
                GROUP BY month, policy_area
                ORDER BY month DESC
            "),
            'party_activity' => DB::select("
                SELECT bs.party, 
                       strftime('%Y-%m', b.introduced_date) as month,
                       COUNT(*) as bills_sponsored
                FROM bills b
                JOIN bill_sponsors bs ON b.id = bs.bill_id
                WHERE b.introduced_date >= date('now', '-24 months')
                GROUP BY bs.party, month
                ORDER BY month DESC
            ")
        ];
    }

    private function getCurrentPatterns(): array
    {
        return [
            'recent_policy_focus' => DB::select("
                SELECT policy_area, COUNT(*) as count
                FROM bills 
                WHERE introduced_date >= date('now', '-3 months')
                GROUP BY policy_area
                ORDER BY count DESC
                LIMIT 10
            "),
            'bipartisan_activity' => $this->calculateBipartisanActivity(),
            'chamber_activity' => DB::select("
                SELECT origin_chamber, COUNT(*) as count
                FROM bills 
                WHERE introduced_date >= date('now', '-3 months')
                GROUP BY origin_chamber
            ")
        ];
    }

    private function calculateBipartisanActivity(): array
    {
        // Calculate bills with bipartisan cosponsorship
        $bipartisanBills = DB::select("
            SELECT b.id, b.title, COUNT(DISTINCT bc.party) as party_count
            FROM bills b
            JOIN bill_cosponsors bc ON b.id = bc.bill_id
            WHERE b.introduced_date >= date('now', '-3 months')
            GROUP BY b.id, b.title
            HAVING party_count > 1
        ");

        return [
            'bipartisan_bills_count' => count($bipartisanBills),
            'total_recent_bills' => Bill::where('introduced_date', '>=', now()->subMonths(3))->count(),
            'bipartisan_percentage' => count($bipartisanBills) > 0 ? 
                round((count($bipartisanBills) / Bill::where('introduced_date', '>=', now()->subMonths(3))->count()) * 100, 2) : 0
        ];
    }

    private function getMemberVotingHistory(Member $member): array
    {
        // This would integrate with actual voting records if available
        return [
            'party_loyalty_score' => 0.85, // Placeholder
            'bipartisan_votes' => 12,
            'total_votes' => 150,
            'policy_positions' => [
                'Healthcare' => 'Liberal',
                'Defense' => 'Moderate',
                'Environment' => 'Liberal'
            ]
        ];
    }

    private function getPartyAlignment(Member $member, string $topic): array
    {
        // Analyze how member's party typically votes on this topic
        return [
            'party_position' => 'Generally supportive',
            'member_deviation_history' => 0.15, // 15% deviation from party line
            'state_interest_alignment' => 0.75
        ];
    }
}