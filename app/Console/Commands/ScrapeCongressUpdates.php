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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ScrapeCongressUpdates extends Command
{
    protected $signature = 'scrape:congress-updates 
                            {--congress=119 : Congress number to check for updates}
                            {--days=1 : Number of days back to check for updates}
                            {--batch-size=50 : Number of bills to process in each batch}
                            {--api-key= : Congress API key}
                            {--force-all : Force check all bills regardless of last update}
                            {--dry-run : Show what would be updated without making changes}';
    
    protected $description = 'Check for updates to existing bills and add new actions, cosponsors, etc.';

    private string $apiKey;
    private string $baseUrl = 'https://api.congress.gov/v3';
    private int $updatedCount = 0;
    private int $newActionsCount = 0;
    private int $newCosponsorsCount = 0;
    private int $newSummariesCount = 0;
    private int $newTextVersionsCount = 0;
    private int $errorCount = 0;
    private bool $dryRun = false;

    public function handle()
    {
        $this->apiKey = $this->option('api-key') ?: config('services.congress.api_key');
        
        if (!$this->apiKey) {
            $this->error('Congress API key is required. Set CONGRESS_API_KEY in .env or use --api-key option');
            return 1;
        }

        $congress = (int) $this->option('congress');
        $days = (int) $this->option('days');
        $batchSize = (int) $this->option('batch-size');
        $forceAll = $this->option('force-all');
        $this->dryRun = $this->option('dry-run');

        $this->info("Starting Congress Bills Update Checker for Congress {$congress}");
        $this->info("Checking for updates in the last {$days} day(s)");
        $this->info("Batch size: {$batchSize}");
        $this->info("Force all: " . ($forceAll ? 'Yes' : 'No'));
        $this->info("Dry run: " . ($this->dryRun ? 'Yes' : 'No'));
        
        try {
            // Get bills that need checking
            $billsToCheck = $this->getBillsToCheck($congress, $days, $forceAll);
            $totalBills = $billsToCheck->count();
            
            if ($totalBills === 0) {
                $this->info("No bills need checking for updates.");
                return 0;
            }

            $this->info("Found {$totalBills} bills to check for updates");
            $this->info(str_repeat('=', 60));

            // Process bills in batches
            $billsToCheck->chunk($batchSize, function ($bills, $chunkIndex) use ($totalBills, $batchSize) {
                $startIndex = ($chunkIndex - 1) * $batchSize + 1;
                $endIndex = min($chunkIndex * $batchSize, $totalBills);
                
                $this->info("\nProcessing batch {$chunkIndex}: bills {$startIndex}-{$endIndex} of {$totalBills}");
                $this->info(str_repeat('-', 40));
                
                foreach ($bills as $index => $bill) {
                    $billNumber = $startIndex + $index;
                    $this->checkBillForUpdates($bill, $billNumber, $totalBills);
                    
                    // Small delay to be respectful to the API
                    if ($billNumber % 10 === 0) {
                        sleep(1);
                    }
                }
                
                // Pause between batches
                if ($endIndex < $totalBills) {
                    $this->info("Pausing 2 seconds before next batch...");
                    sleep(2);
                }
            });
            
            $this->info("\n" . str_repeat('=', 60));
            $this->info("Update check completed!");
            $this->info("Bills updated: {$this->updatedCount}");
            $this->info("New actions: {$this->newActionsCount}");
            $this->info("New cosponsors: {$this->newCosponsorsCount}");
            $this->info("New summaries: {$this->newSummariesCount}");
            $this->info("New text versions: {$this->newTextVersionsCount}");
            $this->info("Errors: {$this->errorCount}");
            
            if ($this->dryRun) {
                $this->warn("DRY RUN - No changes were actually made to the database");
            }
            
        } catch (\Exception $e) {
            $this->error("Error during update checking: " . $e->getMessage());
            Log::error("Congress update checker error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Get bills that need to be checked for updates
     */
    private function getBillsToCheck(int $congress, int $days, bool $forceAll)
    {
        $query = Bill::where('congress', $congress);
        
        if (!$forceAll) {
            // Only check bills that have been updated recently on Congress.gov
            // or haven't been checked in the specified number of days
            $cutoffDate = Carbon::now()->subDays($days);
            
            $query->where(function ($q) use ($cutoffDate) {
                $q->where('update_date', '>=', $cutoffDate)
                  ->orWhere('update_date_including_text', '>=', $cutoffDate)
                  ->orWhere('last_scraped_at', '<=', $cutoffDate)
                  ->orWhereNull('last_scraped_at');
            });
        }
        
        return $query->orderBy('update_date', 'desc');
    }

    /**
     * Check a single bill for updates
     */
    private function checkBillForUpdates(Bill $bill, int $billNumber, int $totalBills): void
    {
        try {
            $this->line("Checking {$billNumber}/{$totalBills}: {$bill->congress_id} - " . Str::limit($bill->title, 50));
            
            // Fetch current bill data from API
            $currentBillData = $this->fetchCurrentBillData($bill);
            
            if (!$currentBillData) {
                $this->error("  ✗ Failed to fetch current data");
                $this->errorCount++;
                return;
            }

            $hasUpdates = false;
            $updateSummary = [];

            // Check if basic bill info has changed
            if ($this->checkBasicBillUpdates($bill, $currentBillData)) {
                $hasUpdates = true;
                $updateSummary[] = 'basic info';
            }

            // Check for new actions
            $newActions = $this->checkForNewActions($bill, $currentBillData);
            if ($newActions > 0) {
                $hasUpdates = true;
                $this->newActionsCount += $newActions;
                $updateSummary[] = "{$newActions} new actions";
            }

            // Check for new cosponsors
            $newCosponsors = $this->checkForNewCosponsors($bill, $currentBillData);
            if ($newCosponsors > 0) {
                $hasUpdates = true;
                $this->newCosponsorsCount += $newCosponsors;
                $updateSummary[] = "{$newCosponsors} new cosponsors";
            }

            // Check for new summaries
            $newSummaries = $this->checkForNewSummaries($bill, $currentBillData);
            if ($newSummaries > 0) {
                $hasUpdates = true;
                $this->newSummariesCount += $newSummaries;
                $updateSummary[] = "{$newSummaries} new summaries";
            }

            // Check for new text versions
            $newTextVersions = $this->checkForNewTextVersions($bill, $currentBillData);
            if ($newTextVersions > 0) {
                $hasUpdates = true;
                $this->newTextVersionsCount += $newTextVersions;
                $updateSummary[] = "{$newTextVersions} new text versions";
            }

            if ($hasUpdates) {
                $this->updatedCount++;
                $this->info("  ✓ Updated: " . implode(', ', $updateSummary));
                
                // Update the last checked timestamp
                if (!$this->dryRun) {
                    $bill->update(['last_scraped_at' => now()]);
                }
            } else {
                $this->line("  → No updates");
                
                // Still update the last checked timestamp
                if (!$this->dryRun) {
                    $bill->update(['last_scraped_at' => now()]);
                }
            }
            
        } catch (\Exception $e) {
            $this->errorCount++;
            $this->error("  ✗ Error: " . $e->getMessage());
            Log::error("Error checking bill {$bill->congress_id}: " . $e->getMessage());
        }
    }

    /**
     * Fetch current bill data from Congress API
     */
    private function fetchCurrentBillData(Bill $bill): ?array
    {
        try {
            $response = Http::timeout(30)->get($bill->api_url, [
                'api_key' => $this->apiKey,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                return $response->json()['bill'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching current bill data for {$bill->congress_id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if basic bill information has been updated
     */
    private function checkBasicBillUpdates(Bill $bill, array $currentData): bool
    {
        $hasUpdates = false;
        $updates = [];

        // Check update dates
        $currentUpdateDate = isset($currentData['updateDate']) ? Carbon::parse($currentData['updateDate']) : null;
        $currentUpdateDateIncludingText = isset($currentData['updateDateIncludingText']) ? Carbon::parse($currentData['updateDateIncludingText']) : null;

        if ($currentUpdateDate && (!$bill->update_date || $currentUpdateDate->gt($bill->update_date))) {
            $updates['update_date'] = $currentUpdateDate;
            $hasUpdates = true;
        }

        if ($currentUpdateDateIncludingText && (!$bill->update_date_including_text || $currentUpdateDateIncludingText->gt($bill->update_date_including_text))) {
            $updates['update_date_including_text'] = $currentUpdateDateIncludingText;
            $hasUpdates = true;
        }

        // Check latest action
        if (isset($currentData['latestAction'])) {
            $latestAction = $currentData['latestAction'];
            $currentActionDate = isset($latestAction['actionDate']) ? Carbon::parse($latestAction['actionDate']) : null;
            $currentActionText = $latestAction['text'] ?? null;

            if ($currentActionDate && (!$bill->latest_action_date || $currentActionDate->gt($bill->latest_action_date))) {
                $updates['latest_action_date'] = $currentActionDate;
                $updates['latest_action_text'] = $currentActionText;
                $updates['latest_action_time'] = isset($latestAction['actionTime']) ? Carbon::parse($latestAction['actionTime']) : null;
                $hasUpdates = true;
            } elseif ($currentActionText && $currentActionText !== $bill->latest_action_text) {
                $updates['latest_action_text'] = $currentActionText;
                $hasUpdates = true;
            }
        }

        // Check counts
        $countsToCheck = [
            'actions' => 'actions_count',
            'summaries' => 'summaries_count',
            'subjects' => 'subjects_count',
            'cosponsors' => 'cosponsors_count',
            'textVersions' => 'text_versions_count',
            'committees' => 'committees_count'
        ];

        foreach ($countsToCheck as $apiField => $dbField) {
            if (isset($currentData[$apiField]['count'])) {
                $currentCount = $currentData[$apiField]['count'];
                if ($currentCount != $bill->$dbField) {
                    $updates[$dbField] = $currentCount;
                    $hasUpdates = true;
                }
            }
        }

        // Apply updates
        if ($hasUpdates && !$this->dryRun) {
            $bill->update($updates);
        }

        return $hasUpdates;
    }

    /**
     * Check for new actions
     */
    private function checkForNewActions(Bill $bill, array $currentData): int
    {
        if (!isset($currentData['actions']['url']) || $currentData['actions']['count'] <= $bill->actions_count) {
            return 0;
        }

        try {
            $actions = $this->fetchBillActions($currentData['actions']['url']);
            
            if (empty($actions)) {
                return 0;
            }

            // Get existing action dates to avoid duplicates
            $existingActionDates = $bill->actions()
                ->pluck(DB::raw("CONCAT(action_date, ' ', COALESCE(action_time, '00:00:00'), ' ', text)"))
                ->toArray();

            $newActionsCount = 0;

            foreach ($actions as $actionData) {
                $actionKey = ($actionData['action_date'] ?? '') . ' ' . 
                           ($actionData['action_time'] ?? '00:00:00') . ' ' . 
                           ($actionData['text'] ?? '');

                if (!in_array($actionKey, $existingActionDates)) {
                    if (!$this->dryRun) {
                        BillAction::create([
                            'bill_id' => $bill->id,
                            'action_date' => isset($actionData['action_date']) ? Carbon::parse($actionData['action_date']) : null,
                            'action_time' => isset($actionData['action_time']) ? Carbon::parse($actionData['action_time']) : null,
                            'text' => $actionData['text'] ?? null,
                            'type' => $actionData['type'] ?? null,
                            'action_code' => $actionData['action_code'] ?? null,
                            'source_system' => $actionData['source_system'] ?? null,
                            'committees' => isset($actionData['committees']) ? json_encode($actionData['committees']) : null,
                            'recorded_votes' => isset($actionData['recorded_votes']) ? json_encode($actionData['recorded_votes']) : null,
                        ]);
                    }
                    $newActionsCount++;
                }
            }

            return $newActionsCount;

        } catch (\Exception $e) {
            Log::error("Error checking new actions for {$bill->congress_id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check for new cosponsors
     */
    private function checkForNewCosponsors(Bill $bill, array $currentData): int
    {
        if (!isset($currentData['cosponsors']['url']) || $currentData['cosponsors']['count'] <= $bill->cosponsors_count) {
            return 0;
        }

        try {
            $cosponsors = $this->fetchBillCosponsors($currentData['cosponsors']['url']);
            
            if (empty($cosponsors)) {
                return 0;
            }

            // Get existing cosponsor bioguide IDs
            $existingCosponsorIds = $bill->cosponsors()->pluck('bioguide_id')->toArray();
            $newCosponsorsCount = 0;

            foreach ($cosponsors as $cosponsorData) {
                $bioguideId = $cosponsorData['bioguide_id'] ?? null;
                
                if ($bioguideId && !in_array($bioguideId, $existingCosponsorIds)) {
                    if (!$this->dryRun) {
                        BillCosponsor::create([
                            'bill_id' => $bill->id,
                            'bioguide_id' => $bioguideId,
                            'first_name' => $cosponsorData['first_name'] ?? null,
                            'last_name' => $cosponsorData['last_name'] ?? null,
                            'full_name' => $cosponsorData['full_name'] ?? null,
                            'party' => $cosponsorData['party'] ?? null,
                            'state' => $cosponsorData['state'] ?? null,
                            'district' => $cosponsorData['district'] ?? null,
                            'sponsorship_date' => isset($cosponsorData['sponsorship_date']) ? Carbon::parse($cosponsorData['sponsorship_date']) : null,
                            'is_original_cosponsor' => $cosponsorData['is_original_cosponsor'] ?? false,
                            'sponsorship_withdrawn_date' => isset($cosponsorData['sponsorship_withdrawn_date']) ? Carbon::parse($cosponsorData['sponsorship_withdrawn_date']) : null,
                        ]);
                    }
                    $newCosponsorsCount++;
                }
            }

            return $newCosponsorsCount;

        } catch (\Exception $e) {
            Log::error("Error checking new cosponsors for {$bill->congress_id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check for new summaries
     */
    private function checkForNewSummaries(Bill $bill, array $currentData): int
    {
        if (!isset($currentData['summaries']['url']) || $currentData['summaries']['count'] <= $bill->summaries_count) {
            return 0;
        }

        try {
            $summaries = $this->fetchBillSummaries($currentData['summaries']['url']);
            
            if (empty($summaries)) {
                return 0;
            }

            // Get existing summary version codes and action dates
            $existingSummaries = $bill->summaries()
                ->pluck(DB::raw("CONCAT(COALESCE(version_code, ''), '|', COALESCE(action_date, ''))"))
                ->toArray();

            $newSummariesCount = 0;

            foreach ($summaries as $summaryData) {
                $summaryKey = ($summaryData['version_code'] ?? '') . '|' . ($summaryData['action_date'] ?? '');
                
                if (!in_array($summaryKey, $existingSummaries)) {
                    if (!$this->dryRun) {
                        BillSummary::create([
                            'bill_id' => $bill->id,
                            'action_date' => isset($summaryData['action_date']) ? Carbon::parse($summaryData['action_date']) : null,
                            'action_desc' => $summaryData['action_desc'] ?? null,
                            'text' => $summaryData['text'] ?? null,
                            'update_date' => isset($summaryData['update_date']) ? Carbon::parse($summaryData['update_date']) : null,
                            'version_code' => $summaryData['version_code'] ?? null,
                        ]);
                    }
                    $newSummariesCount++;
                }
            }

            return $newSummariesCount;

        } catch (\Exception $e) {
            Log::error("Error checking new summaries for {$bill->congress_id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check for new text versions
     */
    private function checkForNewTextVersions(Bill $bill, array $currentData): int
    {
        if (!isset($currentData['textVersions']['url']) || $currentData['textVersions']['count'] <= $bill->text_versions_count) {
            return 0;
        }

        try {
            $textVersions = $this->fetchTextVersions($currentData['textVersions']['url']);
            
            if (empty($textVersions)) {
                return 0;
            }

            // Get existing text version types and dates
            $existingVersions = $bill->textVersions()
                ->pluck(DB::raw("CONCAT(type, '|', date)"))
                ->toArray();

            $newVersionsCount = 0;

            foreach ($textVersions as $versionData) {
                $versionKey = ($versionData['type'] ?? '') . '|' . ($versionData['date'] ?? '');
                
                if (!in_array($versionKey, $existingVersions)) {
                    $formattedTextUrl = null;
                    $pdfUrl = null;
                    $xmlUrl = null;

                    // Extract URLs by format type
                    foreach ($versionData['formats'] as $format) {
                        switch ($format['type']) {
                            case 'Formatted Text':
                                $formattedTextUrl = $format['url'];
                                break;
                            case 'PDF':
                                $pdfUrl = $format['url'];
                                break;
                            case 'Formatted XML':
                                $xmlUrl = $format['url'];
                                break;
                        }
                    }

                    if (!$this->dryRun) {
                        $textVersion = BillTextVersion::updateOrCreate([
                            'bill_id' => $bill->id,
                            'date' => Carbon::parse($versionData['date']),
                            'type' => $versionData['type'],
                        ], [
                            'formatted_text_url' => $formattedTextUrl,
                            'pdf_url' => $pdfUrl,
                            'xml_url' => $xmlUrl,
                        ]);

                        // Try to fetch text for the newest version
                        if ($formattedTextUrl && $newVersionsCount === 0) {
                            $this->fetchAndStoreTextContent($bill, $textVersion, $formattedTextUrl);
                        }
                    }
                    $newVersionsCount++;
                }
            }

            return $newVersionsCount;

        } catch (\Exception $e) {
            Log::error("Error checking new text versions for {$bill->congress_id}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch and store actual bill text content
     */
    private function fetchAndStoreTextContent(Bill $bill, BillTextVersion $textVersion, string $url): void
    {
        try {
            $response = Http::timeout(30)->get($url);
            
            if ($response->successful()) {
                $html = $response->body();
                $text = $this->extractTextFromHtml($html);
                
                if (!empty($text)) {
                    $textVersion->update([
                        'text_content' => $text,
                        'text_fetched' => true,
                        'text_fetched_at' => now(),
                    ]);

                    // Update main bill record if this is the latest version
                    if (!$bill->bill_text || $textVersion->date->gt($bill->bill_text_date ?? '1900-01-01')) {
                        $bill->update([
                            'bill_text' => $text,
                            'bill_text_version_type' => $textVersion->type,
                            'bill_text_date' => $textVersion->date,
                            'bill_text_source_url' => $url,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error fetching text content for {$bill->congress_id}: " . $e->getMessage());
        }
    }

    // Include the existing helper methods from the main scraper
    private function fetchBillActions(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $actions = $data['actions'] ?? [];

        return array_map(function($action) {
            return [
                'action_date' => $action['actionDate'] ?? null,
                'action_time' => $action['actionTime'] ?? null,
                'text' => $action['text'] ?? null,
                'type' => $action['type'] ?? null,
                'action_code' => $action['actionCode'] ?? null,
                'source_system' => $action['sourceSystem']['name'] ?? null
            ];
        }, $actions);
    }

    private function fetchBillCosponsors(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $cosponsors = $data['cosponsors'] ?? [];

        return array_map(function($cosponsor) {
            return [
                'bioguide_id' => $cosponsor['bioguideId'] ?? null,
                'first_name' => $cosponsor['firstName'] ?? null,
                'last_name' => $cosponsor['lastName'] ?? null,
                'full_name' => $cosponsor['fullName'] ?? null,
                'party' => $cosponsor['party'] ?? null,
                'state' => $cosponsor['state'] ?? null,
                'sponsorship_date' => $cosponsor['sponsorshipDate'] ?? null,
                'is_original_cosponsor' => $cosponsor['isOriginalCosponsor'] ?? null
            ];
        }, $cosponsors);
    }

    private function fetchBillSummaries(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $summaries = $data['summaries'] ?? [];

        return array_map(function($summary) {
            return [
                'action_date' => $summary['actionDate'] ?? null,
                'action_desc' => $summary['actionDesc'] ?? null,
                'text' => $summary['text'] ?? null,
                'update_date' => $summary['updateDate'] ?? null,
                'version_code' => $summary['versionCode'] ?? null
            ];
        }, $summaries);
    }

    private function fetchTextVersions(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $textVersions = $data['textVersions'] ?? [];

        return array_map(function($version) {
            return [
                'date' => $version['date'] ?? null,
                'type' => $version['type'] ?? null,
                'formats' => array_map(function($format) {
                    return [
                        'type' => $format['type'] ?? null,
                        'url' => $format['url'] ?? null
                    ];
                }, $version['formats'] ?? [])
            ];
        }, $textVersions);
    }

    private function extractTextFromHtml(string $html): string
    {
        try {
            // Remove script and style elements
            $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
            $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
            
            // Convert HTML to plain text
            $text = strip_tags($html);
            
            // Clean up whitespace
            $text = preg_replace('/\s+/', ' ', $text);
            $text = preg_replace('/\n\s*\n/', "\n\n", $text);
            
            // Decode HTML entities
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            return trim($text);
            
        } catch (\Exception $e) {
            return "Error extracting text: " . $e->getMessage();
        }
    }
}