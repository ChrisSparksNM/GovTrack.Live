<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Services\CongressApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchAllMemberProfiles extends Command
{
    protected $signature = 'members:fetch-all-profiles 
                            {--batch-size=25 : Number of members to process in each batch}
                            {--delay=2 : Delay in seconds between batches}
                            {--force : Force update existing members}';

    protected $description = 'Fetch all missing member profiles from sponsors and cosponsors in batches';

    public function __construct(
        private CongressApiService $congressApiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting comprehensive member profile fetch...');

        // Get all unique bioguide IDs from sponsors and cosponsors
        $sponsorIds = DB::table('bill_sponsors')
            ->select('bioguide_id')
            ->distinct()
            ->pluck('bioguide_id')
            ->toArray();

        $cosponsorIds = DB::table('bill_cosponsors')
            ->select('bioguide_id')
            ->distinct()
            ->pluck('bioguide_id')
            ->toArray();

        $allMemberIds = array_unique(array_merge($sponsorIds, $cosponsorIds));
        $this->info('Found ' . count($allMemberIds) . ' unique members from bills');

        // Find which ones are missing from the members table
        $existingIds = Member::whereIn('bioguide_id', $allMemberIds)
            ->pluck('bioguide_id')
            ->toArray();

        if ($this->option('force')) {
            $processIds = $allMemberIds;
            $this->info('Force mode: Processing all ' . count($processIds) . ' members');
        } else {
            $missingIds = array_diff($allMemberIds, $existingIds);
            $processIds = $missingIds;
            $this->info('Found ' . count($processIds) . ' missing member profiles');
        }

        if (empty($processIds)) {
            $this->info('No members to process');
            return 0;
        }

        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');
        $batches = array_chunk($processIds, $batchSize);

        $this->info("Processing " . count($processIds) . " members in " . count($batches) . " batches of {$batchSize}");
        $this->info("Delay between batches: {$delay} seconds");

        $totalProcessed = 0;
        $totalUpdated = 0;
        $totalErrors = 0;

        foreach ($batches as $batchIndex => $batch) {
            $this->info("\nProcessing batch " . ($batchIndex + 1) . " of " . count($batches) . " (" . count($batch) . " members)");
            
            $progressBar = $this->output->createProgressBar(count($batch));
            $progressBar->start();

            $batchProcessed = 0;
            $batchUpdated = 0;
            $batchErrors = 0;

            foreach ($batch as $bioguideId) {
                try {
                    $memberData = $this->congressApiService->getMemberDetails($bioguideId);
                    
                    if ($memberData) {
                        // Create or update member record
                        Member::updateOrCreate(
                            ['bioguide_id' => $bioguideId],
                            [
                                'first_name' => $memberData['first_name'] ?? '',
                                'last_name' => $memberData['last_name'] ?? '',
                                'full_name' => $memberData['full_name'] ?? '',
                                'direct_order_name' => $memberData['direct_order_name'] ?? '',
                                'inverted_order_name' => $memberData['inverted_order_name'] ?? '',
                                'honorific_name' => $memberData['honorific_name'] ?? null,
                                'party_abbreviation' => $memberData['party_abbreviation'] ?? '',
                                'party_name' => $memberData['party_name'] ?? '',
                                'state' => $memberData['state'] ?? null,
                                'district' => $memberData['district'] ?? null,
                                'chamber' => $memberData['chamber'] ?? null,
                                'birth_year' => $memberData['birth_year'] ?? null,
                                'current_member' => $memberData['current_member'] ?? true,
                                'image_url' => $memberData['image_url'] ?? null,
                                'image_attribution' => $memberData['image_attribution'] ?? null,
                                'official_website_url' => $memberData['official_website_url'] ?? null,
                                'office_address' => $memberData['office_address'] ?? null,
                                'office_city' => $memberData['office_city'] ?? null,
                                'office_phone' => $memberData['office_phone'] ?? null,
                                'office_zip_code' => $memberData['office_zip_code'] ?? null,
                                'sponsored_legislation_count' => $memberData['sponsored_legislation_count'] ?? 0,
                                'cosponsored_legislation_count' => $memberData['cosponsored_legislation_count'] ?? 0,
                                'party_history' => $memberData['party_history'] ?? null,
                                'previous_names' => $memberData['previous_names'] ?? null,
                                'last_updated_at' => now(),
                            ]
                        );
                        
                        $batchUpdated++;
                    } else {
                        $batchErrors++;
                    }
                    
                    $batchProcessed++;
                    $progressBar->advance();
                    
                    // Small delay between individual requests
                    usleep(100000); // 100ms
                    
                } catch (\Exception $e) {
                    Log::error("Error fetching member profile for {$bioguideId}: " . $e->getMessage());
                    $batchErrors++;
                    $batchProcessed++;
                    $progressBar->advance();
                }
            }

            $progressBar->finish();
            
            $totalProcessed += $batchProcessed;
            $totalUpdated += $batchUpdated;
            $totalErrors += $batchErrors;

            $this->newLine();
            $this->info("Batch completed - Processed: {$batchProcessed}, Updated: {$batchUpdated}, Errors: {$batchErrors}");

            // Delay between batches (except for the last batch)
            if ($batchIndex < count($batches) - 1) {
                $this->info("Waiting {$delay} seconds before next batch...");
                sleep($delay);
            }
        }

        $this->newLine();
        $this->info('All member profiles fetch completed!');
        $this->info("Total Processed: {$totalProcessed}");
        $this->info("Total Updated: {$totalUpdated}");
        $this->info("Total Errors: {$totalErrors}");

        return 0;
    }
}