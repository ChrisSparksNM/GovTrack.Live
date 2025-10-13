<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\BillAction;
use App\Models\BillCosponsor;
use App\Models\BillSummary;
use App\Models\BillTextVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ShowRecentUpdates extends Command
{
    protected $signature = 'show:recent-updates 
                            {--congress=119 : Congress number to show updates for}
                            {--days=1 : Number of days back to show updates}
                            {--limit=20 : Maximum number of items to show per category}';
    
    protected $description = 'Show recent updates to bills, actions, cosponsors, etc.';

    public function handle()
    {
        $congress = (int) $this->option('congress');
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        
        $cutoffDate = Carbon::now()->subDays($days);
        
        $this->info("Recent Updates for Congress {$congress} (Last {$days} day(s))");
        $this->info(str_repeat('=', 70));
        
        // Recently updated bills
        $this->showRecentlyUpdatedBills($congress, $cutoffDate, $limit);
        
        // Recent actions
        $this->showRecentActions($congress, $cutoffDate, $limit);
        
        // Recent cosponsors
        $this->showRecentCosponsors($congress, $cutoffDate, $limit);
        
        // Recent summaries
        $this->showRecentSummaries($congress, $cutoffDate, $limit);
        
        // Recent text versions
        $this->showRecentTextVersions($congress, $cutoffDate, $limit);
        
        $this->info("\n" . str_repeat('=', 70));
    }

    private function showRecentlyUpdatedBills(int $congress, Carbon $cutoffDate, int $limit): void
    {
        $recentBills = Bill::where('congress', $congress)
            ->where('last_scraped_at', '>=', $cutoffDate)
            ->orderBy('last_scraped_at', 'desc')
            ->limit($limit)
            ->get();

        $this->info("\n📋 Recently Updated Bills ({$recentBills->count()}):");
        
        if ($recentBills->isEmpty()) {
            $this->line("  No bills updated recently");
            return;
        }

        foreach ($recentBills as $bill) {
            $this->line("  • {$bill->congress_id}: " . Str::limit($bill->title, 50));
            $this->line("    Updated: {$bill->last_scraped_at->diffForHumans()}");
            $this->line("    Latest Action: " . Str::limit($bill->latest_action_text ?? 'None', 60));
        }
    }

    private function showRecentActions(int $congress, Carbon $cutoffDate, int $limit): void
    {
        $recentActions = BillAction::whereHas('bill', function($q) use ($congress) {
                $q->where('congress', $congress);
            })
            ->where('created_at', '>=', $cutoffDate)
            ->with('bill:id,congress_id,title')
            ->orderBy('action_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $this->info("\n⚡ Recent Actions ({$recentActions->count()}):");
        
        if ($recentActions->isEmpty()) {
            $this->line("  No new actions recently");
            return;
        }

        foreach ($recentActions as $action) {
            $this->line("  • {$action->bill->congress_id}: " . Str::limit($action->text, 50));
            $this->line("    Date: {$action->action_date->format('M j, Y')} | Added: {$action->created_at->diffForHumans()}");
            $this->line("    Type: {$action->type} | Source: {$action->source_system}");
        }
    }

    private function showRecentCosponsors(int $congress, Carbon $cutoffDate, int $limit): void
    {
        $recentCosponsors = BillCosponsor::whereHas('bill', function($q) use ($congress) {
                $q->where('congress', $congress);
            })
            ->where('created_at', '>=', $cutoffDate)
            ->with('bill:id,congress_id,title')
            ->orderBy('sponsorship_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $this->info("\n🤝 Recent Cosponsors ({$recentCosponsors->count()}):");
        
        if ($recentCosponsors->isEmpty()) {
            $this->line("  No new cosponsors recently");
            return;
        }

        foreach ($recentCosponsors as $cosponsor) {
            $this->line("  • {$cosponsor->full_name} ({$cosponsor->party}-{$cosponsor->state})");
            $this->line("    Bill: {$cosponsor->bill->congress_id} - " . Str::limit($cosponsor->bill->title, 40));
            $this->line("    Sponsored: {$cosponsor->sponsorship_date?->format('M j, Y')} | Added: {$cosponsor->created_at->diffForHumans()}");
        }
    }

    private function showRecentSummaries(int $congress, Carbon $cutoffDate, int $limit): void
    {
        $recentSummaries = BillSummary::whereHas('bill', function($q) use ($congress) {
                $q->where('congress', $congress);
            })
            ->where('created_at', '>=', $cutoffDate)
            ->with('bill:id,congress_id,title')
            ->orderBy('action_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $this->info("\n📄 Recent Summaries ({$recentSummaries->count()}):");
        
        if ($recentSummaries->isEmpty()) {
            $this->line("  No new summaries recently");
            return;
        }

        foreach ($recentSummaries as $summary) {
            $this->line("  • {$summary->bill->congress_id}: {$summary->action_desc}");
            $this->line("    Date: {$summary->action_date?->format('M j, Y')} | Added: {$summary->created_at->diffForHumans()}");
            $this->line("    Preview: " . Str::limit(strip_tags($summary->text ?? ''), 80));
        }
    }

    private function showRecentTextVersions(int $congress, Carbon $cutoffDate, int $limit): void
    {
        $recentTextVersions = BillTextVersion::whereHas('bill', function($q) use ($congress) {
                $q->where('congress', $congress);
            })
            ->where('created_at', '>=', $cutoffDate)
            ->with('bill:id,congress_id,title')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $this->info("\n📜 Recent Text Versions ({$recentTextVersions->count()}):");
        
        if ($recentTextVersions->isEmpty()) {
            $this->line("  No new text versions recently");
            return;
        }

        foreach ($recentTextVersions as $textVersion) {
            $this->line("  • {$textVersion->bill->congress_id}: {$textVersion->type}");
            $this->line("    Date: {$textVersion->date->format('M j, Y')} | Added: {$textVersion->created_at->diffForHumans()}");
            $this->line("    Text: " . ($textVersion->text_fetched ? 'Fetched ✓' : 'Not fetched'));
        }
    }
}