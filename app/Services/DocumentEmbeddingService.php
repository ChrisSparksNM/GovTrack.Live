<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Member;
use App\Models\BillAction;
use App\Models\BillSummary;
use App\Models\BillSubject;
use App\Models\BillSponsor;
use App\Models\BillCosponsor;
use App\Models\BillTextVersion;
use Illuminate\Support\Facades\Log;

class DocumentEmbeddingService
{
    private EmbeddingService $embeddingService;
    
    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Generate embeddings for all bills with their full context
     */
    public function embedAllBills(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        // Get total count for progress tracking
        $totalBills = Bill::count();
        echo "📊 Starting bill embedding generation for {$totalBills} bills...\n";
        
        Bill::with(['sponsors', 'cosponsors', 'actions', 'summaries', 'subjects', 'textVersions'])
            ->chunk(50, function ($bills) use (&$stats, $totalBills) {
                foreach ($bills as $bill) {
                    $stats['processed']++;
                    
                    // Show progress for every bill
                    $progress = round(($stats['processed'] / $totalBills) * 100, 1);
                    echo "\r🔄 Processing bill {$stats['processed']}/{$totalBills} ({$progress}%) - {$bill->bill_id}";
                    
                    $content = $this->buildBillContent($bill);
                    $embedding = $this->embeddingService->generateEmbedding($content);
                    
                    if ($embedding) {
                        try {
                            $metadata = $this->buildBillMetadata($bill);
                            $success = $this->embeddingService->storeEmbedding(
                                'bill', 
                                $bill->id, 
                                $embedding, 
                                $content, 
                                $metadata
                            );
                            
                            if ($success) {
                                $stats['success']++;
                            } else {
                                echo "\n❌ Failed to store embedding for bill {$bill->bill_id}\n";
                                $stats['failed']++;
                            }
                        } catch (\Exception $e) {
                            echo "\n❌ Failed to process bill {$bill->bill_id}: {$e->getMessage()}\n";
                            $stats['failed']++;
                        }
                    } else {
                        echo "\n❌ Failed to generate embedding for bill {$bill->bill_id}\n";
                        $stats['failed']++;
                    }
                    
                    // Show milestone updates
                    if ($stats['processed'] % 50 === 0) {
                        echo "\n✅ Milestone: {$stats['processed']} bills processed ({$stats['success']} successful, {$stats['failed']} failed)\n";
                    }
                }
            });
            
        echo "\n🎉 Bill embedding generation complete!\n";
        echo "📊 Final Results: {$stats['processed']} processed, {$stats['success']} successful, {$stats['failed']} failed\n";
        
        return $stats;
    }

    /**
     * Generate embeddings for all members with their context
     */
    public function embedAllMembers(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        Member::with(['sponsoredBills', 'cosponsoredBills'])
            ->chunk(50, function ($members) use (&$stats) {
                foreach ($members as $member) {
                    $stats['processed']++;
                    
                    $content = $this->buildMemberContent($member);
                    $embedding = $this->embeddingService->generateEmbedding($content);
                    
                    if ($embedding) {
                        $metadata = $this->buildMemberMetadata($member);
                        $success = $this->embeddingService->storeEmbedding(
                            'member', 
                            $member->id, 
                            $embedding, 
                            $content, 
                            $metadata
                        );
                        
                        if ($success) {
                            $stats['success']++;
                        } else {
                            $stats['failed']++;
                        }
                    } else {
                        $stats['failed']++;
                    }
                }
            });
            
        return $stats;
    }

    /**
     * Generate embeddings for bill actions
     */
    public function embedBillActions(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        BillAction::with('bill')->chunk(100, function ($actions) use (&$stats) {
            foreach ($actions as $action) {
                $stats['processed']++;
                
                $content = $this->buildActionContent($action);
                $embedding = $this->embeddingService->generateEmbedding($content);
                
                if ($embedding) {
                    $metadata = $this->buildActionMetadata($action);
                    $success = $this->embeddingService->storeEmbedding(
                        'bill_action', 
                        $action->id, 
                        $embedding, 
                        $content, 
                        $metadata
                    );
                    
                    if ($success) {
                        $stats['success']++;
                    } else {
                        $stats['failed']++;
                    }
                } else {
                    $stats['failed']++;
                }
            }
        });
        
        return $stats;
    }

    /**
     * Build comprehensive content for bill embedding
     */
    private function buildBillContent(Bill $bill): string
    {
        $content = [];
        
        // Basic bill info
        $content[] = "Bill: {$bill->congress_id}";
        $content[] = "Title: {$bill->title}";
        if ($bill->short_title) {
            $content[] = "Short Title: {$bill->short_title}";
        }
        if ($bill->policy_area) {
            $content[] = "Policy Area: {$bill->policy_area}";
        }
        
        // Sponsors
        if ($bill->sponsors->isNotEmpty()) {
            $sponsors = $bill->sponsors->map(function ($sponsor) {
                return "{$sponsor->full_name} ({$sponsor->party}-{$sponsor->state})";
            })->join(', ');
            $content[] = "Sponsors: {$sponsors}";
        }
        
        // Cosponsors (limit to avoid too much text)
        if ($bill->cosponsors->isNotEmpty()) {
            $cosponsorCount = $bill->cosponsors->count();
            $content[] = "Cosponsors: {$cosponsorCount} members";
            
            $topCosponsors = $bill->cosponsors->take(5)->map(function ($cosponsor) {
                return "{$cosponsor->full_name} ({$cosponsor->party}-{$cosponsor->state})";
            })->join(', ');
            $content[] = "Key Cosponsors: {$topCosponsors}";
        }
        
        // Subjects
        if ($bill->subjects->isNotEmpty()) {
            $subjects = $bill->subjects->pluck('name')->join(', ');
            $content[] = "Subjects: {$subjects}";
        }
        
        // Latest action
        if ($bill->latest_action_text) {
            $content[] = "Latest Action: {$bill->latest_action_text}";
        }
        
        // AI Summary if available
        if ($bill->ai_summary) {
            $content[] = "Summary: {$bill->ai_summary}";
        }
        
        // Bill text (truncated to avoid token limits)
        if ($bill->bill_text) {
            $truncatedText = substr($bill->bill_text, 0, 2000);
            $content[] = "Bill Text: {$truncatedText}";
        }
        
        // Recent actions (last 3)
        $recentActions = $bill->actions()->orderBy('action_date', 'desc')->take(3)->get();
        if ($recentActions->isNotEmpty()) {
            $actions = $recentActions->map(function ($action) {
                return "{$action->action_date}: {$action->text}";
            })->join(' | ');
            $content[] = "Recent Actions: {$actions}";
        }
        
        return implode("\n", $content);
    }

    /**
     * Build metadata for bill embedding
     */
    private function buildBillMetadata(Bill $bill): array
    {
        return [
            'congress_id' => $bill->congress_id,
            'congress' => $bill->congress,
            'type' => $bill->type,
            'number' => $bill->number,
            'origin_chamber' => $bill->origin_chamber,
            'policy_area' => $bill->policy_area,
            'introduced_date' => $bill->introduced_date?->toDateString(),
            'sponsor_states' => $bill->sponsors->pluck('state')->unique()->values()->toArray(),
            'sponsor_parties' => $bill->sponsors->pluck('party')->unique()->values()->toArray(),
            'cosponsor_count' => $bill->cosponsors->count(),
            'subjects_count' => $bill->subjects->count(),
            'actions_count' => $bill->actions->count(),
        ];
    }

    /**
     * Build comprehensive content for member embedding
     */
    private function buildMemberContent(Member $member): string
    {
        $content = [];
        
        // Basic member info
        $content[] = "Member: {$member->display_name}";
        $content[] = "Party: {$member->current_party}";
        $content[] = "State: {$member->state}";
        $content[] = "Chamber: {$member->chamber_display}";
        
        if ($member->district) {
            $content[] = "District: {$member->district}";
        }
        
        // Legislative activity
        $content[] = "Sponsored Bills: {$member->sponsored_legislation_count}";
        $content[] = "Cosponsored Bills: {$member->cosponsored_legislation_count}";
        
        // Recent sponsored bills (top 5 by date)
        $recentSponsored = $member->sponsoredBills()
            ->with('bill')
            ->whereHas('bill')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
            
        if ($recentSponsored->isNotEmpty()) {
            $bills = $recentSponsored->map(function ($sponsor) {
                return $sponsor->bill ? "{$sponsor->bill->congress_id}: {$sponsor->bill->title}" : null;
            })->filter()->join(' | ');
            $content[] = "Recent Sponsored Bills: {$bills}";
        }
        
        // Policy areas of interest (from sponsored bills)
        $policyAreas = $member->sponsoredBills()
            ->with('bill')
            ->whereHas('bill', function ($query) {
                $query->whereNotNull('policy_area');
            })
            ->get()
            ->pluck('bill.policy_area')
            ->filter()
            ->unique()
            ->take(5)
            ->join(', ');
            
        if ($policyAreas) {
            $content[] = "Policy Focus Areas: {$policyAreas}";
        }
        
        return implode("\n", $content);
    }

    /**
     * Build metadata for member embedding
     */
    private function buildMemberMetadata(Member $member): array
    {
        return [
            'bioguide_id' => $member->bioguide_id,
            'full_name' => $member->full_name,
            'party_abbreviation' => $member->party_abbreviation,
            'state' => $member->state,
            'district' => $member->district,
            'chamber' => $member->chamber,
            'current_member' => $member->current_member,
            'sponsored_count' => $member->sponsored_legislation_count,
            'cosponsored_count' => $member->cosponsored_legislation_count,
        ];
    }

    /**
     * Build content for action embedding
     */
    private function buildActionContent(BillAction $action): string
    {
        $content = [];
        
        if ($action->bill) {
            $content[] = "Bill: {$action->bill->congress_id} - {$action->bill->title}";
        }
        
        $content[] = "Action Date: {$action->action_date}";
        $content[] = "Action: {$action->text}";
        
        if ($action->type) {
            $content[] = "Type: {$action->type}";
        }
        
        if ($action->committees) {
            $committees = is_array($action->committees) ? 
                implode(', ', $action->committees) : 
                $action->committees;
            $content[] = "Committees: {$committees}";
        }
        
        return implode("\n", $content);
    }

    /**
     * Build metadata for action embedding
     */
    private function buildActionMetadata(BillAction $action): array
    {
        return [
            'bill_id' => $action->bill_id,
            'bill_congress_id' => $action->bill?->congress_id,
            'action_date' => $action->action_date->toDateString(),
            'action_type' => $action->type,
            'source_system' => $action->source_system,
        ];
    }
}