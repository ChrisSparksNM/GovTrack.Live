<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\CongressApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAllCurrentMembers extends Command
{
    protected $signature = 'members:fetch-all-current 
                            {--limit=50 : Limit the number of members to process per batch}
                            {--offset=0 : Starting offset for pagination}
                            {--max-pages=0 : Maximum number of pages to process (0 = all)}
                            {--delay=1 : Delay in seconds between API requests}
                            {--current-only : Only fetch members marked as currentMember=true}';

    protected $description = 'Fetch all current members of Congress using the members API with pagination';

    public function __construct(
        private CongressApiService $congressApiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting comprehensive fetch of all current members of Congress...');

        $apiKey = config('services.congress.api_key');
        if (!$apiKey) {
            $this->error('Congress API key not configured');
            return 1;
        }

        $baseUrl = config('services.congress.base_url', 'https://api.congress.gov/v3');
        $limit = (int) $this->option('limit');
        $offset = (int) $this->option('offset');
        $maxPages = (int) $this->option('max-pages');
        $delay = (int) $this->option('delay');
        $currentOnly = $this->option('current-only');

        $totalProcessed = 0;
        $totalUpdated = 0;
        $totalErrors = 0;
        $currentPage = 1;

        do {
            $this->info("\n--- Processing Page {$currentPage} (offset: {$offset}) ---");

            try {
                // Fetch members list from API
                $response = Http::timeout(30)->get("{$baseUrl}/member", [
                    'api_key' => $apiKey,
                    'format' => 'json',
                    'limit' => $limit,
                    'offset' => $offset
                ]);

                if (!$response->successful()) {
                    $this->error("API request failed with status: {$response->status()}");
                    break;
                }

                $data = $response->json();
                $members = $data['members'] ?? [];
                $pagination = $data['pagination'] ?? [];

                if (empty($members)) {
                    $this->info('No more members found');
                    break;
                }

                $this->info("Found " . count($members) . " members on this page");
                $progressBar = $this->output->createProgressBar(count($members));
                $progressBar->start();

                $pageProcessed = 0;
                $pageUpdated = 0;
                $pageErrors = 0;

                foreach ($members as $memberSummary) {
                    try {
                        $bioguideId = $memberSummary['bioguideId'] ?? null;
                        
                        if (!$bioguideId) {
                            $this->warn("Skipping member without bioguideId");
                            $pageErrors++;
                            $progressBar->advance();
                            continue;
                        }

                        // Check if we should only process current members
                        if ($currentOnly) {
                            // Fetch detailed member info to check currentMember status
                            $detailedMember = $this->fetchMemberDetails($bioguideId, $apiKey, $baseUrl);
                            
                            if (!$detailedMember || !($detailedMember['currentMember'] ?? false)) {
                                $progressBar->advance();
                                $pageProcessed++;
                                continue; // Skip non-current members
                            }
                        }

                        // Check if member already exists
                        $existingMember = Member::where('bioguide_id', $bioguideId)->first();
                        
                        if ($existingMember) {
                            // Update existing member with latest data
                            $memberData = $this->congressApiService->getMemberDetails($bioguideId);
                            if ($memberData) {
                                $existingMember->update($memberData);
                                $pageUpdated++;
                            }
                        } else {
                            // Create new member
                            $memberData = $this->congressApiService->getMemberDetails($bioguideId);
                            if ($memberData) {
                                Member::create(array_merge($memberData, ['bioguide_id' => $bioguideId]));
                                $pageUpdated++;
                            } else {
                                $pageErrors++;
                            }
                        }

                        $pageProcessed++;
                        $progressBar->advance();

                        // Rate limiting
                        if ($delay > 0) {
                            usleep($delay * 1000000); // Convert to microseconds
                        }

                    } catch (\Exception $e) {
                        $this->error("\nError processing member {$bioguideId}: " . $e->getMessage());
                        Log::error("Error processing member {$bioguideId}: " . $e->getMessage());
                        $pageErrors++;
                        $progressBar->advance();
                    }
                }

                $progressBar->finish();
                $this->newLine();

                $totalProcessed += $pageProcessed;
                $totalUpdated += $pageUpdated;
                $totalErrors += $pageErrors;

                $this->info("Page {$currentPage} completed:");
                $this->info("  Processed: {$pageProcessed}");
                $this->info("  Updated: {$pageUpdated}");
                $this->info("  Errors: {$pageErrors}");

                // Check if there are more pages
                $hasNext = !empty($pagination['next']);
                $offset += $limit;
                $currentPage++;

                // Check if we've reached the maximum pages limit
                if ($maxPages > 0 && $currentPage > $maxPages) {
                    $this->info("Reached maximum pages limit ({$maxPages})");
                    break;
                }

                if (!$hasNext) {
                    $this->info('No more pages available');
                    break;
                }

            } catch (\Exception $e) {
                $this->error("Error fetching page {$currentPage}: " . $e->getMessage());
                Log::error("Error fetching members page {$currentPage}: " . $e->getMessage());
                break;
            }

        } while (true);

        $this->newLine();
        $this->info('All current members fetch completed!');
        $this->info("Total Pages Processed: " . ($currentPage - 1));
        $this->info("Total Members Processed: {$totalProcessed}");
        $this->info("Total Members Updated: {$totalUpdated}");
        $this->info("Total Errors: {$totalErrors}");

        return 0;
    }

    /**
     * Fetch detailed member information for currentMember check
     */
    private function fetchMemberDetails(string $bioguideId, string $apiKey, string $baseUrl): ?array
    {
        try {
            $response = Http::timeout(10)->get("{$baseUrl}/member/{$bioguideId}", [
                'api_key' => $apiKey,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['member'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching member details for {$bioguideId}: " . $e->getMessage());
            return null;
        }
    }
}