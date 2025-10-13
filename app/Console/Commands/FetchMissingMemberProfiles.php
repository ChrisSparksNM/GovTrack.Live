<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Member;
use App\Services\CongressApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchMissingMemberProfiles extends Command
{
    protected $signature = 'members:fetch-missing-profiles 
                            {--limit=50 : Limit the number of members to process}
                            {--force : Force update existing members}';

    protected $description = 'Fetch member profiles for all sponsors and cosponsors that are missing from the database';

    public function __construct(
        private CongressApiService $congressApiService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Finding missing member profiles from sponsors and cosponsors...');

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

        $missingIds = array_diff($allMemberIds, $existingIds);

        if (empty($missingIds) && !$this->option('force')) {
            $this->info('No missing member profiles found!');
            return 0;
        }

        if ($this->option('force')) {
            $processIds = $allMemberIds;
            $this->info('Force mode: Processing all ' . count($processIds) . ' members');
        } else {
            $processIds = $missingIds;
            $this->info('Found ' . count($processIds) . ' missing member profiles');
        }

        // Apply limit if specified
        $limit = (int) $this->option('limit');
        if ($limit > 0 && count($processIds) > $limit) {
            $processIds = array_slice($processIds, 0, $limit);
            $this->info("Limited to {$limit} members");
        }

        if (empty($processIds)) {
            $this->info('No members to process');
            return 0;
        }

        $this->info('Processing ' . count($processIds) . ' members...');
        $progressBar = $this->output->createProgressBar(count($processIds));
        $progressBar->start();

        $processed = 0;
        $updated = 0;
        $errors = 0;

        foreach ($processIds as $bioguideId) {
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
                    
                    $updated++;
                } else {
                    $this->warn("\nFailed to fetch data for member: {$bioguideId}");
                    $errors++;
                }
                
                $processed++;
                $progressBar->advance();
                
                // Rate limiting - be respectful to the API
                usleep(100000); // 100ms delay between requests
                
            } catch (\Exception $e) {
                $this->error("\nError processing member {$bioguideId}: " . $e->getMessage());
                Log::error("Error fetching member profile for {$bioguideId}: " . $e->getMessage());
                $errors++;
                $processed++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('Member profile fetch completed!');
        $this->info("Processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Errors: {$errors}");

        return 0;
    }
}