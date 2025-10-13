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

class ScrapeCongressBills extends Command
{
    protected $signature = 'scrape:congress-bills 
                            {--congress=119 : Congress number to scrape}
                            {--limit=0 : Number of bills to scrape (0 = all)}
                            {--offset=0 : Starting offset}
                            {--batch-size=50 : Number of bills to process in each batch}
                            {--api-key= : Congress API key}
                            {--skip-existing : Skip bills that are already fully scraped}
                            {--text-only : Only scrape bill text for existing bills}';
    protected $description = 'Scrape detailed bill information from Congress.gov API and store in database';

    private string $apiKey;
    private string $baseUrl = 'https://api.congress.gov/v3';
    private int $processedCount = 0;
    private int $errorCount = 0;
    private int $skippedCount = 0;

    public function handle()
    {
        $this->apiKey = $this->option('api-key') ?: config('services.congress.api_key');
        
        if (!$this->apiKey) {
            $this->error('Congress API key is required. Set CONGRESS_API_KEY in .env or use --api-key option');
            return 1;
        }

        $congress = (int) $this->option('congress');
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $batchSize = (int) $this->option('batch-size');
        $skipExisting = $this->option('skip-existing');
        $textOnly = $this->option('text-only');

        $this->info("Starting Congress Bills scraper for Congress {$congress}");
        $this->info("Limit: " . ($limit > 0 ? $limit : 'ALL'));
        $this->info("Offset: {$offset}");
        $this->info("Batch size: {$batchSize}");
        $this->info("Skip existing: " . ($skipExisting ? 'Yes' : 'No'));
        $this->info("Text only: " . ($textOnly ? 'Yes' : 'No'));
        
        try {
            if ($textOnly) {
                return $this->scrapeTextOnly($congress);
            }

            // Get total count first
            $totalCount = $this->getTotalBillsCount($congress);
            $this->info("Total bills available: " . number_format($totalCount));
            
            if ($limit > 0) {
                $totalCount = min($totalCount, $limit);
                $this->info("Will process: " . number_format($totalCount) . " bills");
            }

            $currentOffset = $offset;
            $processedInBatch = 0;

            while ($currentOffset < $totalCount) {
                $currentBatchSize = min($batchSize, $totalCount - $currentOffset);
                
                $this->info("\n" . str_repeat('=', 80));
                $this->info("Processing batch: offset {$currentOffset}, size {$currentBatchSize}");
                $this->info("Progress: " . number_format($currentOffset) . "/" . number_format($totalCount) . " (" . round(($currentOffset / $totalCount) * 100, 1) . "%)");
                $this->info(str_repeat('=', 80));
                
                // Fetch bills for this batch
                $bills = $this->fetchBillsList($congress, $currentBatchSize, $currentOffset);
                
                if (empty($bills)) {
                    $this->warn("No bills returned for offset {$currentOffset}");
                    break;
                }

                // Process each bill in the batch
                foreach ($bills as $index => $billData) {
                    $billNumber = $index + 1 + $currentOffset;
                    $this->processBillToDB($billData, $billNumber, $totalCount, $skipExisting);
                    
                    $processedInBatch++;
                    
                    // Small delay to be respectful to the API
                    if ($processedInBatch % 10 === 0) {
                        sleep(1); // Pause every 10 bills
                    }
                }

                $currentOffset += $currentBatchSize;
                
                // Show progress
                $this->info("Batch completed. Processed: {$this->processedCount}, Errors: {$this->errorCount}, Skipped: {$this->skippedCount}");
                
                // Longer pause between batches
                if ($currentOffset < $totalCount) {
                    $this->info("Pausing 3 seconds before next batch...");
                    sleep(3);
                }
            }
            
            $this->info("\n" . str_repeat('=', 80));
            $this->info("Scraping completed!");
            $this->info("Total processed: {$this->processedCount}");
            $this->info("Total errors: {$this->errorCount}");
            $this->info("Total skipped: {$this->skippedCount}");
            $this->info(str_repeat('=', 80));
            
        } catch (\Exception $e) {
            $this->error("Error during scraping: " . $e->getMessage());
            Log::error("Congress scraper error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Get total count of bills for a congress
     */
    private function getTotalBillsCount(int $congress): int
    {
        $response = Http::timeout(30)->get("{$this->baseUrl}/bill/{$congress}", [
            'api_key' => $this->apiKey,
            'format' => 'json',
            'limit' => 1
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch bills count: HTTP " . $response->status());
        }

        $data = $response->json();
        return $data['pagination']['count'] ?? 0;
    }

    /**
     * Fetch the initial list of bills
     */
    private function fetchBillsList(int $congress, int $limit, int $offset = 0): array
    {
        $response = Http::timeout(30)->get("{$this->baseUrl}/bill/{$congress}", [
            'api_key' => $this->apiKey,
            'format' => 'json',
            'limit' => $limit,
            'offset' => $offset
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to fetch bills list: HTTP " . $response->status());
        }

        $data = $response->json();
        return $data['bills'] ?? [];
    }

    /**
     * Process a bill and save to database
     */
    private function processBillToDB(array $billData, int $billNumber, int $totalCount, bool $skipExisting): void
    {
        try {
            $congress = $billData['congress'];
            $type = strtolower($billData['type']);
            $number = $billData['number'];
            $congressId = "{$congress}-{$type}{$number}";
            
            $this->line("Processing {$billNumber}/{$totalCount}: {$congressId} - " . Str::limit($billData['title'], 60));
            
            // Check if bill already exists and is fully scraped
            $existingBill = Bill::where('congress_id', $congressId)->first();
            
            if ($existingBill && $skipExisting && $existingBill->is_fully_scraped) {
                $this->skippedCount++;
                $this->line("  → Skipped (already fully scraped)");
                return;
            }

            // Create or update the bill record
            $bill = $this->createOrUpdateBill($billData, $existingBill);
            
            // Fetch and store detailed information
            $this->fetchAndStoreBillDetails($bill, $billData);
            
            // Mark as fully scraped
            $bill->update([
                'is_fully_scraped' => true,
                'last_scraped_at' => now()
            ]);
            
            $this->processedCount++;
            $this->line("  ✓ Completed");
            
        } catch (\Exception $e) {
            $this->errorCount++;
            $this->error("  ✗ Error: " . $e->getMessage());
            Log::error("Error processing bill {$congressId}: " . $e->getMessage());
        }
    }

    /**
     * Create or update bill record
     */
    private function createOrUpdateBill(array $billData, ?Bill $existingBill = null): Bill
    {
        $congress = $billData['congress'];
        $type = strtolower($billData['type']);
        $number = $billData['number'];
        $congressId = "{$congress}-{$type}{$number}";

        $billAttributes = [
            'congress_id' => $congressId,
            'congress' => $congress,
            'number' => $number,
            'type' => $billData['type'],
            'origin_chamber' => $billData['originChamber'] ?? null,
            'origin_chamber_code' => $billData['originChamberCode'] ?? null,
            'title' => $billData['title'],
            'update_date' => isset($billData['updateDate']) ? \Carbon\Carbon::parse($billData['updateDate']) : null,
            'update_date_including_text' => isset($billData['updateDateIncludingText']) ? \Carbon\Carbon::parse($billData['updateDateIncludingText']) : null,
            'latest_action_date' => isset($billData['latestAction']['actionDate']) ? \Carbon\Carbon::parse($billData['latestAction']['actionDate']) : null,
            'latest_action_time' => isset($billData['latestAction']['actionTime']) ? \Carbon\Carbon::parse($billData['latestAction']['actionTime']) : null,
            'latest_action_text' => $billData['latestAction']['text'] ?? null,
            'api_url' => $billData['url'] ?? null,
        ];

        if ($existingBill) {
            $existingBill->update($billAttributes);
            return $existingBill;
        } else {
            return Bill::create($billAttributes);
        }
    }

    /**
     * Fetch and store all detailed bill information
     */
    private function fetchAndStoreBillDetails(Bill $bill, array $basicBillData): void
    {
        $this->line("  → Fetching detailed information...");
        
        // Get detailed bill information
        $billDetails = $this->fetchBillDetails($basicBillData['url']);
        
        if (!empty($billDetails)) {
            // Update bill with detailed information
            $bill->update([
                'introduced_date' => isset($billDetails['introduced_date']) ? \Carbon\Carbon::parse($billDetails['introduced_date']) : null,
                'policy_area' => $billDetails['policy_area'],
                'legislation_url' => $billDetails['legislation_url'],
                'actions_count' => $billDetails['actions']['count'],
                'summaries_count' => $billDetails['summaries']['count'],
                'subjects_count' => $billDetails['subjects']['count'],
                'cosponsors_count' => $billDetails['cosponsors']['count'],
                'text_versions_count' => $billDetails['text_versions']['count'],
                'committees_count' => $billDetails['committees']['count'],
            ]);

            // Store sponsors
            if (!empty($billDetails['sponsors'])) {
                $this->storeBillSponsors($bill, $billDetails['sponsors']);
            }

            // Store actions
            if ($billDetails['actions']['count'] > 0 && !empty($billDetails['actions']['url'])) {
                $this->storeBillActions($bill, $billDetails['actions']['url']);
            }

            // Store summaries
            if ($billDetails['summaries']['count'] > 0 && !empty($billDetails['summaries']['url'])) {
                $this->storeBillSummaries($bill, $billDetails['summaries']['url']);
            }

            // Store subjects
            if ($billDetails['subjects']['count'] > 0 && !empty($billDetails['subjects']['url'])) {
                $this->storeBillSubjects($bill, $billDetails['subjects']['url']);
            }

            // Store cosponsors
            if ($billDetails['cosponsors']['count'] > 0 && !empty($billDetails['cosponsors']['url'])) {
                $this->storeBillCosponsors($bill, $billDetails['cosponsors']['url']);
            }

            // Store text versions and fetch text
            if ($billDetails['text_versions']['count'] > 0 && !empty($billDetails['text_versions']['url'])) {
                $this->storeBillTextVersions($bill, $billDetails['text_versions']['url']);
            }
        }
    }

    /**
     * Store bill sponsors
     */
    private function storeBillSponsors(Bill $bill, array $sponsors): void
    {
        // Clear existing sponsors
        $bill->sponsors()->delete();

        foreach ($sponsors as $sponsorData) {
            BillSponsor::create([
                'bill_id' => $bill->id,
                'bioguide_id' => $sponsorData['bioguideId'] ?? null,
                'first_name' => $sponsorData['firstName'] ?? null,
                'last_name' => $sponsorData['lastName'] ?? null,
                'full_name' => $sponsorData['fullName'] ?? null,
                'party' => $sponsorData['party'] ?? null,
                'state' => $sponsorData['state'] ?? null,
                'district' => $sponsorData['district'] ?? null,
                'is_by_request' => $sponsorData['isByRequest'] ?? null,
            ]);
        }
    }

    /**
     * Store bill actions
     */
    private function storeBillActions(Bill $bill, string $actionsUrl): void
    {
        $actions = $this->fetchBillActions($actionsUrl);
        
        if (!empty($actions)) {
            // Clear existing actions
            $bill->actions()->delete();

            foreach ($actions as $actionData) {
                BillAction::create([
                    'bill_id' => $bill->id,
                    'action_date' => isset($actionData['action_date']) ? \Carbon\Carbon::parse($actionData['action_date']) : null,
                    'action_time' => isset($actionData['action_time']) ? \Carbon\Carbon::parse($actionData['action_time']) : null,
                    'text' => $actionData['text'] ?? null,
                    'type' => $actionData['type'] ?? null,
                    'action_code' => $actionData['action_code'] ?? null,
                    'source_system' => $actionData['source_system'] ?? null,
                    'committees' => isset($actionData['committees']) ? json_encode($actionData['committees']) : null,
                    'recorded_votes' => isset($actionData['recorded_votes']) ? json_encode($actionData['recorded_votes']) : null,
                ]);
            }
        }
    }

    /**
     * Store bill summaries
     */
    private function storeBillSummaries(Bill $bill, string $summariesUrl): void
    {
        $summaries = $this->fetchBillSummaries($summariesUrl);
        
        if (!empty($summaries)) {
            // Clear existing summaries
            $bill->summaries()->delete();

            foreach ($summaries as $summaryData) {
                BillSummary::create([
                    'bill_id' => $bill->id,
                    'action_date' => isset($summaryData['action_date']) ? \Carbon\Carbon::parse($summaryData['action_date']) : null,
                    'action_desc' => $summaryData['action_desc'] ?? null,
                    'text' => $summaryData['text'] ?? null,
                    'update_date' => isset($summaryData['update_date']) ? \Carbon\Carbon::parse($summaryData['update_date']) : null,
                    'version_code' => $summaryData['version_code'] ?? null,
                ]);
            }
        }
    }

    /**
     * Store bill subjects
     */
    private function storeBillSubjects(Bill $bill, string $subjectsUrl): void
    {
        $subjects = $this->fetchBillSubjects($subjectsUrl);
        
        if (!empty($subjects)) {
            // Clear existing subjects
            $bill->subjects()->delete();

            // Store legislative subjects
            if (!empty($subjects['legislative_subjects'])) {
                foreach ($subjects['legislative_subjects'] as $subject) {
                    BillSubject::updateOrCreate([
                        'bill_id' => $bill->id,
                        'name' => $subject['name'],
                        'type' => 'legislative',
                    ]);
                }
            }

            // Store policy area
            if (!empty($subjects['policy_area'])) {
                BillSubject::updateOrCreate([
                    'bill_id' => $bill->id,
                    'name' => $subjects['policy_area'],
                    'type' => 'policy_area',
                ]);
            }
        }
    }

    /**
     * Store bill cosponsors
     */
    private function storeBillCosponsors(Bill $bill, string $cosponsorsUrl): void
    {
        $cosponsors = $this->fetchBillCosponsors($cosponsorsUrl);
        
        if (!empty($cosponsors)) {
            // Clear existing cosponsors
            $bill->cosponsors()->delete();

            foreach ($cosponsors as $cosponsorData) {
                BillCosponsor::updateOrCreate([
                    'bill_id' => $bill->id,
                    'bioguide_id' => $cosponsorData['bioguide_id'] ?? null,
                ], [
                    'first_name' => $cosponsorData['first_name'] ?? null,
                    'last_name' => $cosponsorData['last_name'] ?? null,
                    'full_name' => $cosponsorData['full_name'] ?? null,
                    'party' => $cosponsorData['party'] ?? null,
                    'state' => $cosponsorData['state'] ?? null,
                    'district' => $cosponsorData['district'] ?? null,
                    'sponsorship_date' => isset($cosponsorData['sponsorship_date']) ? \Carbon\Carbon::parse($cosponsorData['sponsorship_date']) : null,
                    'is_original_cosponsor' => $cosponsorData['is_original_cosponsor'] ?? false,
                    'sponsorship_withdrawn_date' => isset($cosponsorData['sponsorship_withdrawn_date']) ? \Carbon\Carbon::parse($cosponsorData['sponsorship_withdrawn_date']) : null,
                ]);
            }
        }
    }

    /**
     * Store bill text versions
     */
    private function storeBillTextVersions(Bill $bill, string $textVersionsUrl): void
    {
        $textVersions = $this->fetchTextVersions($textVersionsUrl);
        
        if (!empty($textVersions)) {
            // Clear existing text versions
            $bill->textVersions()->delete();

            foreach ($textVersions as $versionData) {
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

                $textVersion = BillTextVersion::updateOrCreate([
                    'bill_id' => $bill->id,
                    'date' => \Carbon\Carbon::parse($versionData['date']),
                    'type' => $versionData['type'],
                ], [
                    'formatted_text_url' => $formattedTextUrl,
                    'pdf_url' => $pdfUrl,
                    'xml_url' => $xmlUrl,
                ]);

                // Try to fetch the actual text content for the latest version
                if ($formattedTextUrl && $textVersion->id === $bill->textVersions()->latest('date')->first()?->id) {
                    $this->fetchAndStoreTextContent($bill, $textVersion, $formattedTextUrl);
                }
            }
        }
    }

    /**
     * Fetch and store actual bill text content
     */
    private function fetchAndStoreTextContent(Bill $bill, BillTextVersion $textVersion, string $url): void
    {
        try {
            $this->line("    → Fetching bill text...");
            
            $response = Http::timeout(30)->get($url);
            
            if ($response->successful()) {
                $html = $response->body();
                $text = $this->extractTextFromHtml($html);
                
                if (!empty($text)) {
                    // Store in text version
                    $textVersion->update([
                        'text_content' => $text,
                        'text_fetched' => true,
                        'text_fetched_at' => now(),
                    ]);

                    // Also store in main bill record for quick access
                    $bill->update([
                        'bill_text' => $text,
                        'bill_text_version_type' => $textVersion->type,
                        'bill_text_date' => $textVersion->date,
                        'bill_text_source_url' => $url,
                    ]);

                    $this->line("    ✓ Text fetched (" . number_format(strlen($text)) . " characters)");
                }
            }
        } catch (\Exception $e) {
            $this->line("    ✗ Failed to fetch text: " . $e->getMessage());
        }
    }

    /**
     * Scrape text only for existing bills
     */
    private function scrapeTextOnly(int $congress): int
    {
        $this->info("Scraping text for existing bills in Congress {$congress}...");
        
        $bills = Bill::where('congress', $congress)
                    ->where('text_versions_count', '>', 0)
                    ->whereNull('bill_text')
                    ->get();

        $this->info("Found " . $bills->count() . " bills without text");

        foreach ($bills as $index => $bill) {
            $this->line("Processing " . ($index + 1) . "/" . $bills->count() . ": {$bill->congress_id}");
            
            $latestTextVersion = $bill->textVersions()->latest('date')->first();
            
            if ($latestTextVersion && $latestTextVersion->formatted_text_url) {
                $this->fetchAndStoreTextContent($bill, $latestTextVersion, $latestTextVersion->formatted_text_url);
            }

            if (($index + 1) % 10 === 0) {
                sleep(1); // Pause every 10 bills
            }
        }

        $this->info("Text scraping completed!");
        return 0;
    }

    /**
     * Process a single bill and fetch all detailed information (legacy method for display)
     */
    private function processBill(array $bill): array
    {
        $congress = $bill['congress'];
        $type = strtolower($bill['type']);
        $number = $bill['number'];
        
        $this->info("Processing: {$congress}-{$type}{$number} - {$bill['title']}");
        
        // Start with basic bill info
        $detailedBill = [
            'basic_info' => [
                'congress' => $congress,
                'number' => $number,
                'type' => $bill['type'],
                'title' => $bill['title'],
                'origin_chamber' => $bill['originChamber'],
                'origin_chamber_code' => $bill['originChamberCode'],
                'update_date' => $bill['updateDate'],
                'update_date_including_text' => $bill['updateDateIncludingText'],
                'api_url' => $bill['url'],
                'latest_action' => [
                    'date' => $bill['latestAction']['actionDate'],
                    'time' => $bill['latestAction']['actionTime'] ?? null,
                    'text' => $bill['latestAction']['text']
                ]
            ]
        ];

        // Step 1: Get detailed bill information
        $this->info("  → Fetching detailed bill information...");
        $billDetails = $this->fetchBillDetails($bill['url']);
        $detailedBill['details'] = $billDetails;

        // Step 2: Get actions
        if (isset($billDetails['actions']['url'])) {
            $this->info("  → Fetching bill actions...");
            $actions = $this->fetchBillActions($billDetails['actions']['url']);
            $detailedBill['actions'] = $actions;
        }

        // Step 3: Get summaries (if available)
        if (isset($billDetails['summaries']['url'])) {
            $this->info("  → Fetching bill summaries...");
            $summaries = $this->fetchBillSummaries($billDetails['summaries']['url']);
            $detailedBill['summaries'] = $summaries;
        }

        // Step 4: Get subjects (if available)
        if (isset($billDetails['subjects']['url'])) {
            $this->info("  → Fetching bill subjects...");
            $subjects = $this->fetchBillSubjects($billDetails['subjects']['url']);
            $detailedBill['subjects'] = $subjects;
        }

        // Step 5: Get cosponsors (if available)
        if (isset($billDetails['cosponsors']['url'])) {
            $this->info("  → Fetching bill cosponsors...");
            $cosponsors = $this->fetchBillCosponsors($billDetails['cosponsors']['url']);
            $detailedBill['cosponsors'] = $cosponsors;
        }

        // Step 6: Get text versions (if available)
        if (isset($billDetails['text_versions']['url']) && $billDetails['text_versions']['count'] > 0) {
            $this->info("  → Fetching text versions...");
            $textVersions = $this->fetchTextVersions($billDetails['text_versions']['url']);
            $detailedBill['text_versions'] = $textVersions;
            
            // Step 7: Get the actual bill text from the latest version
            if (!empty($textVersions)) {
                $this->info("  → Fetching actual bill text...");
                $billText = $this->fetchActualBillText($textVersions);
                $detailedBill['bill_text'] = $billText;
            }
        } else {
            $this->info("  → No text versions available for this bill");
        }

        return $detailedBill;
    }

    /**
     * Fetch detailed bill information
     */
    private function fetchBillDetails(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            $this->warn("Failed to fetch bill details from: {$url}");
            return [];
        }

        $data = $response->json();
        $bill = $data['bill'] ?? [];

        return [
            'introduced_date' => $bill['introducedDate'] ?? null,
            'legislation_url' => $bill['legislationUrl'] ?? null,
            'policy_area' => $bill['policyArea']['name'] ?? null,
            'sponsors' => $bill['sponsors'] ?? [],
            'actions' => [
                'count' => $bill['actions']['count'] ?? 0,
                'url' => $bill['actions']['url'] ?? null
            ],
            'summaries' => [
                'count' => $bill['summaries']['count'] ?? 0,
                'url' => $bill['summaries']['url'] ?? null
            ],
            'subjects' => [
                'count' => $bill['subjects']['count'] ?? 0,
                'url' => $bill['subjects']['url'] ?? null
            ],
            'cosponsors' => [
                'count' => $bill['cosponsors']['count'] ?? 0,
                'url' => $bill['cosponsors']['url'] ?? null
            ],
            'text_versions' => [
                'count' => $bill['textVersions']['count'] ?? 0,
                'url' => $bill['textVersions']['url'] ?? null
            ],
            'committees' => [
                'count' => $bill['committees']['count'] ?? 0,
                'url' => $bill['committees']['url'] ?? null
            ]
        ];
    }

    /**
     * Fetch bill actions
     */
    private function fetchBillActions(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            $this->warn("Failed to fetch actions from: {$url}");
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

    /**
     * Fetch bill summaries
     */
    private function fetchBillSummaries(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            $this->warn("Failed to fetch summaries from: {$url}");
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

    /**
     * Fetch bill subjects
     */
    private function fetchBillSubjects(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            $this->warn("Failed to fetch subjects from: {$url}");
            return [];
        }

        $data = $response->json();
        $subjects = $data['subjects'] ?? [];

        return [
            'legislative_subjects' => $subjects['legislativeSubjects'] ?? [],
            'policy_area' => $subjects['policyArea']['name'] ?? null
        ];
    }

    /**
     * Fetch bill cosponsors
     */
    private function fetchBillCosponsors(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            $this->warn("Failed to fetch cosponsors from: {$url}");
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

    /**
     * Fetch text versions
     */
    private function fetchTextVersions(string $url): array
    {
        $response = Http::timeout(30)->get($url, [
            'api_key' => $this->apiKey,
            'format' => 'json'
        ]);

        if (!$response->successful()) {
            $this->warn("Failed to fetch text versions from: {$url}");
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

    /**
     * Fetch actual bill text from the latest text version
     */
    private function fetchActualBillText(array $textVersions): array
    {
        if (empty($textVersions)) {
            return ['text' => null, 'source_url' => null, 'version_type' => null];
        }

        // Get the latest version (first in the array)
        $latestVersion = $textVersions[0];
        
        // Look for formatted text URL
        $formattedTextUrl = null;
        foreach ($latestVersion['formats'] as $format) {
            if ($format['type'] === 'Formatted Text') {
                $formattedTextUrl = $format['url'];
                break;
            }
        }

        if (!$formattedTextUrl) {
            return ['text' => null, 'source_url' => null, 'version_type' => $latestVersion['type']];
        }

        try {
            $this->info("    → Fetching text from: " . $formattedTextUrl);
            
            $response = Http::timeout(30)->get($formattedTextUrl);
            
            if (!$response->successful()) {
                $this->warn("    → Failed to fetch bill text: HTTP " . $response->status());
                return ['text' => null, 'source_url' => $formattedTextUrl, 'version_type' => $latestVersion['type']];
            }

            $html = $response->body();
            $text = $this->extractTextFromHtml($html);
            
            return [
                'text' => $text,
                'source_url' => $formattedTextUrl,
                'version_type' => $latestVersion['type'],
                'date' => $latestVersion['date']
            ];

        } catch (\Exception $e) {
            $this->warn("    → Error fetching bill text: " . $e->getMessage());
            return ['text' => null, 'source_url' => $formattedTextUrl, 'version_type' => $latestVersion['type']];
        }
    }

    /**
     * Extract and format readable text from HTML content
     */
    private function extractTextFromHtml(string $html): string
    {
        try {
            // Remove script and style elements
            $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
            $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
            
            // Convert specific HTML elements to formatted text
            $html = $this->formatLegislativeHtml($html);
            
            // Convert HTML to plain text
            $text = strip_tags($html);
            
            // Apply legislative text formatting
            $text = $this->formatLegislativeText($text);
            
            // Decode HTML entities
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            return trim($text);
            
        } catch (\Exception $e) {
            return "Error extracting text: " . $e->getMessage();
        }
    }

    /**
     * Format HTML elements commonly found in legislative text
     */
    private function formatLegislativeHtml(string $html): string
    {
        // Convert headers to formatted text with proper spacing
        $html = preg_replace('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i', "\n\n=== $2 ===\n\n", $html);
        
        // Convert paragraphs to double line breaks
        $html = preg_replace('/<p[^>]*>/i', "\n\n", $html);
        $html = preg_replace('/<\/p>/i', "", $html);
        
        // Convert line breaks
        $html = preg_replace('/<br[^>]*>/i', "\n", $html);
        
        // Convert list items
        $html = preg_replace('/<li[^>]*>/i', "\n  • ", $html);
        $html = preg_replace('/<\/li>/i', "", $html);
        
        // Convert ordered list items (try to detect numbering)
        $html = preg_replace_callback('/<ol[^>]*>(.*?)<\/ol>/is', function($matches) {
            $content = $matches[1];
            $items = preg_split('/<li[^>]*>/i', $content);
            $formatted = "";
            $counter = 1;
            foreach ($items as $item) {
                $item = preg_replace('/<\/li>/i', '', $item);
                $item = trim(strip_tags($item));
                if (!empty($item)) {
                    $formatted .= "\n  " . $counter . ". " . $item;
                    $counter++;
                }
            }
            return $formatted . "\n";
        }, $html);
        
        // Convert unordered lists
        $html = preg_replace('/<ul[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/ul>/i', "\n", $html);
        
        // Convert divs and sections to line breaks
        $html = preg_replace('/<div[^>]*>/i', "\n", $html);
        $html = preg_replace('/<\/div>/i', "", $html);
        $html = preg_replace('/<section[^>]*>/i', "\n\n", $html);
        $html = preg_replace('/<\/section>/i', "\n", $html);
        
        return $html;
    }

    /**
     * Format legislative text for better readability
     */
    private function formatLegislativeText(string $text): string
    {
        // Clean up excessive whitespace first
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $text);
        
        // Format section headers (SEC. 1., SECTION 1, etc.)
        $text = preg_replace('/\b(SEC\.|SECTION)\s*(\d+[A-Z]*\.?)/i', "\n\n$1 $2", $text);
        
        // Format subsections (a), (b), (1), (2), etc.
        $text = preg_replace('/\s*\(([a-zA-Z0-9]+)\)\s*/', "\n\n    ($1) ", $text);
        
        // Format bill titles and headers
        $text = preg_replace('/^(A BILL|AN ACT|RESOLUTION)/m', "\n=== $1 ===\n", $text);
        
        // Format "Be it enacted" clause
        $text = preg_replace('/(Be it enacted by .+?\.)/i', "\n$1\n", $text);
        
        // Format definitions sections
        $text = preg_replace('/\b(DEFINITIONS?\.?)\s*[-—]/i', "\n\n=== $1 ===\n", $text);
        
        // Format findings and purposes
        $text = preg_replace('/\b(FINDINGS?|PURPOSES?|POLICY)\.?\s*[-—]/i', "\n\n=== $1 ===\n", $text);
        
        // Add proper spacing around numbered items
        $text = preg_replace('/\s*\((\d+)\)\s*/', "\n\n    ($1) ", $text);
        
        // Format lettered subsections with proper indentation
        $text = preg_replace('/\s*\(([A-Z])\)\s*/', "\n        ($1) ", $text);
        
        // Format roman numeral subsections
        $text = preg_replace('/\s*\(([ivxlcdm]+)\)\s*/i', "\n            ($1) ", $text);
        
        // Clean up multiple consecutive line breaks
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        // Ensure proper spacing after periods in legislative context
        $text = preg_replace('/\.([A-Z][a-z])/', '. $1', $text);
        
        return $text;
    }

    /**
     * Display all the collected bill data
     */
    private function displayBillData(array $bill): void
    {
        $this->info("\n📋 BASIC INFORMATION:");
        $this->line("  Congress: " . $bill['basic_info']['congress']);
        $this->line("  Number: " . $bill['basic_info']['number']);
        $this->line("  Type: " . $bill['basic_info']['type']);
        $this->line("  Title: " . $bill['basic_info']['title']);
        $this->line("  Origin Chamber: " . $bill['basic_info']['origin_chamber']);
        $this->line("  Update Date: " . $bill['basic_info']['update_date']);
        $this->line("  API URL: " . $bill['basic_info']['api_url']);

        $this->info("\n🎬 LATEST ACTION:");
        $this->line("  Date: " . $bill['basic_info']['latest_action']['date']);
        if ($bill['basic_info']['latest_action']['time']) {
            $this->line("  Time: " . $bill['basic_info']['latest_action']['time']);
        }
        $this->line("  Text: " . $bill['basic_info']['latest_action']['text']);

        if (!empty($bill['details'])) {
            $this->info("\n📝 DETAILED INFORMATION:");
            $this->line("  Introduced Date: " . ($bill['details']['introduced_date'] ?? 'N/A'));
            $this->line("  Policy Area: " . ($bill['details']['policy_area'] ?? 'N/A'));
            $this->line("  Congress.gov URL: " . ($bill['details']['legislation_url'] ?? 'N/A'));

            if (!empty($bill['details']['sponsors'])) {
                $this->info("\n👥 SPONSORS:");
                foreach ($bill['details']['sponsors'] as $sponsor) {
                    $this->line("  • " . $sponsor['fullName'] . " (" . $sponsor['party'] . "-" . $sponsor['state'] . ")");
                    $this->line("    Bioguide ID: " . $sponsor['bioguideId']);
                    $this->line("    By Request: " . $sponsor['isByRequest']);
                }
            }
        }

        if (!empty($bill['actions'])) {
            $this->info("\n⚡ ACTIONS (" . count($bill['actions']) . " total):");
            foreach (array_slice($bill['actions'], 0, 5) as $action) { // Show first 5 actions
                $this->line("  • " . $action['action_date'] . 
                           ($action['action_time'] ? " " . $action['action_time'] : "") . 
                           " - " . $action['text']);
                $this->line("    Type: " . ($action['type'] ?? 'N/A') . 
                           " | Source: " . ($action['source_system'] ?? 'N/A'));
            }
            if (count($bill['actions']) > 5) {
                $this->line("  ... and " . (count($bill['actions']) - 5) . " more actions");
            }
        }

        if (!empty($bill['summaries'])) {
            $this->info("\n📄 SUMMARIES (" . count($bill['summaries']) . " total):");
            foreach ($bill['summaries'] as $summary) {
                $this->line("  • " . ($summary['action_date'] ?? 'N/A') . " - " . ($summary['action_desc'] ?? 'N/A'));
                if ($summary['text']) {
                    $this->line("    " . Str::limit($summary['text'], 100));
                }
            }
        }

        if (!empty($bill['subjects']['legislative_subjects'])) {
            $this->info("\n🏷️  SUBJECTS:");
            foreach (array_slice($bill['subjects']['legislative_subjects'], 0, 10) as $subject) {
                $this->line("  • " . $subject['name']);
            }
            if (count($bill['subjects']['legislative_subjects']) > 10) {
                $this->line("  ... and " . (count($bill['subjects']['legislative_subjects']) - 10) . " more subjects");
            }
        }

        if (!empty($bill['cosponsors'])) {
            $this->info("\n🤝 COSPONSORS (" . count($bill['cosponsors']) . " total):");
            foreach (array_slice($bill['cosponsors'], 0, 5) as $cosponsor) {
                $this->line("  • " . $cosponsor['full_name'] . " (" . $cosponsor['party'] . "-" . $cosponsor['state'] . ")");
                $this->line("    Sponsored: " . ($cosponsor['sponsorship_date'] ?? 'N/A') . 
                           " | Original: " . ($cosponsor['is_original_cosponsor'] ? 'Yes' : 'No'));
            }
            if (count($bill['cosponsors']) > 5) {
                $this->line("  ... and " . (count($bill['cosponsors']) - 5) . " more cosponsors");
            }
        }

        if (!empty($bill['text_versions'])) {
            $this->info("\n📜 TEXT VERSIONS (" . count($bill['text_versions']) . " total):");
            foreach ($bill['text_versions'] as $version) {
                $this->line("  • " . ($version['date'] ?? 'N/A') . " - " . ($version['type'] ?? 'N/A'));
                foreach ($version['formats'] as $format) {
                    $this->line("    Format: " . $format['type'] . " | URL: " . $format['url']);
                }
            }
        }

        if (!empty($bill['bill_text'])) {
            $this->info("\n📄 BILL TEXT:");
            $this->line("  Version: " . ($bill['bill_text']['version_type'] ?? 'N/A'));
            $this->line("  Date: " . ($bill['bill_text']['date'] ?? 'N/A'));
            $this->line("  Source URL: " . ($bill['bill_text']['source_url'] ?? 'N/A'));
            
            if ($bill['bill_text']['text']) {
                $this->info("\n📖 BILL TEXT CONTENT (truncated):");
                $this->line("  " . str_repeat('-', 76));
                
                // Display first 1000 characters of the bill text
                $truncatedText = Str::limit($bill['bill_text']['text'], 1000);
                $lines = explode("\n", $truncatedText);
                
                foreach (array_slice($lines, 0, 20) as $line) { // Show first 20 lines
                    $this->line("  " . trim($line));
                }
                
                if (strlen($bill['bill_text']['text']) > 1000) {
                    $this->line("  ...");
                    $this->line("  [Text truncated - Full text is " . number_format(strlen($bill['bill_text']['text'])) . " characters]");
                }
                
                $this->line("  " . str_repeat('-', 76));
            } else {
                $this->warn("  No text content available");
            }
        }

        $this->line("\n" . str_repeat('-', 80));
    }
}