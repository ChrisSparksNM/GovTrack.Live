<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\BillSponsor;
use App\Models\BillCosponsor;
use App\Services\CongressApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchMemberProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'members:fetch-profiles {--limit=50 : Limit number of members to process} {--force : Force update existing members}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch detailed member profiles from Congress API for all sponsors and cosponsors';

    public function __construct(private CongressApiService $congressApi)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting member profile fetch...');
        
        $limit = $this->option('limit');
        $force = $this->option('force');
        
        // Get unique bioguide IDs from sponsors and cosponsors
        $sponsorIds = BillSponsor::distinct()->pluck('bioguide_id')->filter();
        $cosponsorIds = BillCosponsor::distinct()->pluck('bioguide_id')->filter();
        
        $allBioguideIds = $sponsorIds->merge($cosponsorIds)->unique()->values();
        
        $this->info("Found {$allBioguideIds->count()} unique members to process");
        
        if ($limit) {
            $allBioguideIds = $allBioguideIds->take($limit);
            $this->info("Limited to {$limit} members");
        }
        
        $processed = 0;
        $updated = 0;
        $errors = 0;
        
        $progressBar = $this->output->createProgressBar($allBioguideIds->count());
        $progressBar->start();
        
        foreach ($allBioguideIds as $bioguideId) {
            try {
                // Check if member already exists and skip if not forcing update
                $existingMember = Member::where('bioguide_id', $bioguideId)->first();
                if ($existingMember && !$force && $existingMember->last_updated_at && $existingMember->last_updated_at->gt(now()->subDays(7))) {
                    $progressBar->advance();
                    $processed++;
                    continue;
                }
                
                // Fetch member data from API
                $memberData = $this->congressApi->getMemberDetails($bioguideId);
                
                if ($memberData && isset($memberData['member'])) {
                    $this->storeMemberData($memberData['member']);
                    $updated++;
                } else {
                    $this->warn("No data found for member: {$bioguideId}");
                    $errors++;
                }
                
                $processed++;
                $progressBar->advance();
                
                // Rate limiting - small delay between requests
                usleep(100000); // 0.1 second delay
                
            } catch (\Exception $e) {
                $this->error("Error processing member {$bioguideId}: " . $e->getMessage());
                Log::error("Member fetch error for {$bioguideId}: " . $e->getMessage());
                $errors++;
                $progressBar->advance();
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("Member profile fetch completed!");
        $this->info("Processed: {$processed}");
        $this->info("Updated: {$updated}");
        $this->info("Errors: {$errors}");
        
        return Command::SUCCESS;
    }
    
    private function storeMemberData(array $memberData): void
    {
        $data = [
            'bioguide_id' => $memberData['bioguideId'],
            'first_name' => $memberData['firstName'] ?? '',
            'last_name' => $memberData['lastName'] ?? '',
            'full_name' => $memberData['directOrderName'] ?? '',
            'direct_order_name' => $memberData['directOrderName'] ?? '',
            'inverted_order_name' => $memberData['invertedOrderName'] ?? '',
            'honorific_name' => $memberData['honorificName'] ?? null,
            'birth_year' => $memberData['birthYear'] ?? null,
            'current_member' => $memberData['currentMember'] ?? true,
            'image_url' => $memberData['depiction']['imageUrl'] ?? null,
            'image_attribution' => $memberData['depiction']['attribution'] ?? null,
            'official_website_url' => $memberData['officialWebsiteUrl'] ?? null,
            'sponsored_legislation_count' => $memberData['sponsoredLegislation']['count'] ?? 0,
            'cosponsored_legislation_count' => $memberData['cosponsoredLegislation']['count'] ?? 0,
            'party_history' => $memberData['partyHistory'] ?? null,
            'previous_names' => $memberData['previousNames'] ?? null,
            'last_updated_at' => now(),
        ];
        
        // Get current party info
        if (isset($memberData['partyHistory']) && is_array($memberData['partyHistory']) && count($memberData['partyHistory']) > 0) {
            $currentParty = collect($memberData['partyHistory'])->sortByDesc('startYear')->first();
            $data['party_abbreviation'] = $currentParty['partyAbbreviation'] ?? '';
            $data['party_name'] = $currentParty['partyName'] ?? '';
        }
        
        // Get address information
        if (isset($memberData['addressInformation'])) {
            $address = $memberData['addressInformation'];
            $data['office_address'] = $address['officeAddress'] ?? null;
            $data['office_city'] = $address['city'] ?? null;
            $data['office_phone'] = $address['phoneNumber'] ?? null;
            $data['office_zip_code'] = $address['zipCode'] ?? null;
        }
        
        Member::updateOrCreate(
            ['bioguide_id' => $data['bioguide_id']],
            $data
        );
    }
}
