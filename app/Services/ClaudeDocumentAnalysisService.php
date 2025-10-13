<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Member;
use App\Models\BillAction;
use Illuminate\Support\Facades\Log;

class ClaudeDocumentAnalysisService
{
    private ClaudeSemanticService $claudeSemanticService;
    
    public function __construct(ClaudeSemanticService $claudeSemanticService)
    {
        $this->claudeSemanticService = $claudeSemanticService;
    }

    /**
     * Generate semantic fingerprints for all bills
     */
    public function analyzeBills(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        Bill::with(['sponsors', 'cosponsors', 'actions', 'summaries', 'subjects'])
            ->chunk(25, function ($bills) use (&$stats) { // Smaller chunks for Claude API
                foreach ($bills as $bill) {
                    $stats['processed']++;
                    
                    $content = $this->buildBillContent($bill);
                    $fingerprint = $this->claudeSemanticService->generateSemanticFingerprint($content);
                    
                    if ($fingerprint) {
                        $metadata = $this->buildBillMetadata($bill);
                        $success = $this->claudeSemanticService->storeFingerprint(
                            'bill', 
                            $bill->id, 
                            $fingerprint, 
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
                    
                    if ($stats['processed'] % 5 === 0) {
                        Log::info("Bill analysis progress: {$stats['processed']} processed");
                        // Add delay to respect API limits
                        sleep(1);
                    }
                }
            });
            
        return $stats;
    }

    /**
     * Generate semantic fingerprints for all members
     */
    public function analyzeMembers(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        Member::with(['sponsoredBills', 'cosponsoredBills'])
            ->chunk(25, function ($members) use (&$stats) {
                foreach ($members as $member) {
                    $stats['processed']++;
                    
                    $content = $this->buildMemberContent($member);
                    $fingerprint = $this->claudeSemanticService->generateSemanticFingerprint($content);
                    
                    if ($fingerprint) {
                        $metadata = $this->buildMemberMetadata($member);
                        $success = $this->claudeSemanticService->storeFingerprint(
                            'member', 
                            $member->id, 
                            $fingerprint, 
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
                    
                    if ($stats['processed'] % 5 === 0) {
                        sleep(1); // Rate limiting
                    }
                }
            });
            
        return $stats;
    }

    /**
     * Generate semantic fingerprints for bill actions
     */
    public function analyzeActions(): array
    {
        $stats = ['processed' => 0, 'success' => 0, 'failed' => 0];
        
        BillAction::with('bill')->chunk(50, function ($actions) use (&$stats) {
            foreach ($actions as $action) {
                $stats['processed']++;
                
                $content = $this->buildActionContent($action);
                $fingerprint = $this->claudeSemanticService->generateSemanticFingerprint($content);
                
                if ($fingerprint) {
                    $metadata = $this->buildActionMetadata($action);
                    $success = $this->claudeSemanticService->storeFingerprint(
                        'bill_action', 
                        $action->id, 
                        $fingerprint, 
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
                
                if ($stats['processed'] % 10 === 0) {
                    sleep(1); // Rate limiting
                }
            }
        });
        
        return $stats;
    }

    /**
     * Build comprehensive content for bill analysis
     */
    private function buildBillContent(Bill $bill): string
    {
        $content = [];
        
        // Basic bill info
        $content[] = "Congressional Bill: {$bill->congress_id}";
        $content[] = "Title: {$bill->title}";
        if ($bill->short_title) {
            $content[] = "Short Title: {$bill->short_title}";
        }
        if ($bill->policy_area) {
            $content[] = "Policy Area: {$bill->policy_area}";
        }
        
        // Sponsors (limit for Claude analysis)
        if ($bill->sponsors->isNotEmpty()) {
            $sponsors = $bill->sponsors->take(3)->map(function ($sponsor) {
                return "{$sponsor->full_name} ({$sponsor->party}-{$sponsor->state})";
            })->join(', ');
            $content[] = "Primary Sponsors: {$sponsors}";
        }
        
        // Subjects
        if ($bill->subjects->isNotEmpty()) {
            $subjects = $bill->subjects->take(5)->pluck('name')->join(', ');
            $content[] = "Legislative Subjects: {$subjects}";
        }
        
        // Latest action
        if ($bill->latest_action_text) {
            $content[] = "Latest Legislative Action: {$bill->latest_action_text}";
        }
        
        // AI Summary if available (prioritize this for semantic analysis)
        if ($bill->ai_summary) {
            $content[] = "Bill Summary: {$bill->ai_summary}";
        }
        
        // Bill text (heavily truncated for Claude)
        if ($bill->bill_text) {
            $truncatedText = substr($bill->bill_text, 0, 1000);
            $content[] = "Bill Text Excerpt: {$truncatedText}";
        }
        
        return implode("\n", $content);
    }

    /**
     * Build metadata for bill fingerprint
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
        ];
    }

    /**
     * Build comprehensive content for member analysis
     */
    private function buildMemberContent(Member $member): string
    {
        $content = [];
        
        // Basic member info
        $content[] = "Congressional Member: {$member->display_name}";
        $content[] = "Political Affiliation: {$member->current_party}";
        $content[] = "Represents: {$member->state}";
        $content[] = "Legislative Chamber: {$member->chamber_display}";
        
        if ($member->district) {
            $content[] = "Congressional District: {$member->district}";
        }
        
        // Legislative activity summary
        $content[] = "Legislative Activity: Sponsored {$member->sponsored_legislation_count} bills, Cosponsored {$member->cosponsored_legislation_count} bills";
        
        // Recent sponsored bills (top 3 for analysis)
        $recentSponsored = $member->sponsoredBills()
            ->with('bill')
            ->whereHas('bill')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();
            
        if ($recentSponsored->isNotEmpty()) {
            $bills = $recentSponsored->map(function ($sponsor) {
                return $sponsor->bill ? "{$sponsor->bill->congress_id}: {$sponsor->bill->title}" : null;
            })->filter()->join(' | ');
            $content[] = "Recent Legislative Focus: {$bills}";
        }
        
        // Policy areas of interest
        $policyAreas = $member->sponsoredBills()
            ->with('bill')
            ->whereHas('bill', function ($query) {
                $query->whereNotNull('policy_area');
            })
            ->get()
            ->pluck('bill.policy_area')
            ->filter()
            ->unique()
            ->take(3)
            ->join(', ');
            
        if ($policyAreas) {
            $content[] = "Policy Focus Areas: {$policyAreas}";
        }
        
        return implode("\n", $content);
    }

    /**
     * Build metadata for member fingerprint
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
        ];
    }

    /**
     * Build content for action analysis
     */
    private function buildActionContent(BillAction $action): string
    {
        $content = [];
        
        if ($action->bill) {
            $content[] = "Legislative Action on Bill: {$action->bill->congress_id} - {$action->bill->title}";
        }
        
        $content[] = "Action Date: {$action->action_date}";
        $content[] = "Legislative Action: {$action->text}";
        
        if ($action->type) {
            $content[] = "Action Type: {$action->type}";
        }
        
        if ($action->committees) {
            $committees = is_array($action->committees) ? 
                implode(', ', $action->committees) : 
                $action->committees;
            $content[] = "Committee Involvement: {$committees}";
        }
        
        return implode("\n", $content);
    }

    /**
     * Build metadata for action fingerprint
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