<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\BillAction;
use App\Models\BillSponsor;
use App\Models\BillSummary;
use App\Models\BillSubject;
use App\Models\BillCosponsor;
use App\Models\BillTextVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ShowDatabaseStats extends Command
{
    protected $signature = 'db:stats {--congress=119 : Congress number to show stats for}';
    protected $description = 'Show database statistics for scraped bills';

    public function handle()
    {
        $congress = (int) $this->option('congress');
        
        $this->info("Database Statistics for Congress {$congress}");
        $this->info(str_repeat('=', 60));
        
        // Bills stats
        $totalBills = Bill::where('congress', $congress)->count();
        $fullyScrapedBills = Bill::where('congress', $congress)->where('is_fully_scraped', true)->count();
        $billsWithText = Bill::where('congress', $congress)->whereNotNull('bill_text')->count();
        
        $this->line("📋 Bills:");
        $this->line("  Total: " . number_format($totalBills));
        $this->line("  Fully scraped: " . number_format($fullyScrapedBills));
        $this->line("  With text: " . number_format($billsWithText));
        
        if ($totalBills > 0) {
            $this->line("  Completion: " . round(($fullyScrapedBills / $totalBills) * 100, 1) . "%");
            $this->line("  Text coverage: " . round(($billsWithText / $totalBills) * 100, 1) . "%");
        }
        
        // Related data stats
        $actions = BillAction::whereHas('bill', function($q) use ($congress) {
            $q->where('congress', $congress);
        })->count();
        
        $sponsors = BillSponsor::whereHas('bill', function($q) use ($congress) {
            $q->where('congress', $congress);
        })->count();
        
        $cosponsors = BillCosponsor::whereHas('bill', function($q) use ($congress) {
            $q->where('congress', $congress);
        })->count();
        
        $summaries = BillSummary::whereHas('bill', function($q) use ($congress) {
            $q->where('congress', $congress);
        })->count();
        
        $subjects = BillSubject::whereHas('bill', function($q) use ($congress) {
            $q->where('congress', $congress);
        })->count();
        
        $textVersions = BillTextVersion::whereHas('bill', function($q) use ($congress) {
            $q->where('congress', $congress);
        })->count();
        
        $this->line("\n📊 Related Data:");
        $this->line("  Actions: " . number_format($actions));
        $this->line("  Sponsors: " . number_format($sponsors));
        $this->line("  Cosponsors: " . number_format($cosponsors));
        $this->line("  Summaries: " . number_format($summaries));
        $this->line("  Subjects: " . number_format($subjects));
        $this->line("  Text Versions: " . number_format($textVersions));
        
        // Bill types breakdown
        $this->line("\n📈 Bill Types:");
        $billTypes = Bill::where('congress', $congress)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->orderBy('count', 'desc')
            ->get();
            
        foreach ($billTypes as $billType) {
            $this->line("  {$billType->type}: " . number_format($billType->count));
        }
        
        // Recent activity
        $this->line("\n🕒 Recent Activity:");
        $recentBills = Bill::where('congress', $congress)
            ->where('is_fully_scraped', true)
            ->orderBy('last_scraped_at', 'desc')
            ->limit(5)
            ->get(['congress_id', 'title', 'last_scraped_at']);
            
        foreach ($recentBills as $bill) {
            $this->line("  {$bill->congress_id}: " . Str::limit($bill->title, 40) . " ({$bill->last_scraped_at->diffForHumans()})");
        }
        
        // Storage estimates
        $this->line("\n💾 Storage Estimates:");
        $avgTextLength = Bill::where('congress', $congress)
            ->whereNotNull('bill_text')
            ->selectRaw('AVG(LENGTH(bill_text)) as avg_length')
            ->first()->avg_length ?? 0;
            
        if ($avgTextLength > 0) {
            $this->line("  Average bill text: " . number_format($avgTextLength) . " characters");
            $estimatedTotalSize = ($avgTextLength * $totalBills) / (1024 * 1024); // MB
            $this->line("  Estimated total text size: " . number_format($estimatedTotalSize, 1) . " MB");
        }
        
        $this->info("\n" . str_repeat('=', 60));
    }
}