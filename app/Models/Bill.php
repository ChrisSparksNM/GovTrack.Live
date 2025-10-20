<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bill extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'congress_id',
        'congress',
        'number',
        'type',
        'origin_chamber',
        'origin_chamber_code',
        'title',
        'short_title',
        'policy_area',
        'introduced_date',
        'update_date',
        'update_date_including_text',
        'latest_action_date',
        'latest_action_time',
        'latest_action_text',
        'api_url',
        'legislation_url',
        'actions_count',
        'summaries_count',
        'subjects_count',
        'cosponsors_count',
        'text_versions_count',
        'committees_count',
        'bill_text',
        'bill_text_version_type',
        'bill_text_date',
        'bill_text_source_url',
        'ai_summary',
        'ai_summary_html',
        'ai_summary_generated_at',
        'ai_summary_metadata',
        'is_fully_scraped',
        'last_scraped_at',
        'scraping_errors',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'introduced_date' => 'date',
            'update_date' => 'datetime',
            'update_date_including_text' => 'datetime',
            'latest_action_date' => 'date',
            'latest_action_time' => 'datetime',
            'bill_text_date' => 'datetime',
            'ai_summary_generated_at' => 'datetime',
            'ai_summary_metadata' => 'array',
            'is_fully_scraped' => 'boolean',
            'last_scraped_at' => 'datetime',
            'scraping_errors' => 'array',
        ];
    }

    /**
     * Get the users tracking this bill.
     */
    public function trackedByUsers(): HasMany
    {
        return $this->hasMany(TrackedBill::class);
    }

    /**
     * Get the users tracking this bill (direct relationship).
     */
    public function trackers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tracked_bills')
                    ->withPivot(['notes', 'notification_preferences', 'tracked_at'])
                    ->withTimestamps();
    }

    /**
     * Check if a user is tracking this bill.
     */
    public function isTrackedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->trackedByUsers()->where('user_id', $user->id)->exists();
    }

    /**
     * Get the votes for this bill.
     */
    public function votes(): HasMany
    {
        return $this->hasMany(BillVote::class);
    }

    /**
     * Get upvotes count.
     */
    public function getUpvotesCountAttribute(): int
    {
        return $this->votes()->where('vote_type', 'up')->count();
    }

    /**
     * Get downvotes count.
     */
    public function getDownvotesCountAttribute(): int
    {
        return $this->votes()->where('vote_type', 'down')->count();
    }

    /**
     * Get user's vote for this bill.
     */
    public function getUserVote(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $vote = $this->votes()->where('user_id', $user->id)->first();
        return $vote ? $vote->vote_type : null;
    }

    /**
     * Get total vote activity (upvotes + downvotes).
     */
    public function getTotalVotesAttribute(): int
    {
        return $this->votes()->count();
    }

    /**
     * Scope to order by most voted (total vote activity).
     */
    public function scopeOrderByMostVoted($query, $direction = 'desc')
    {
        return $query->withCount('votes')
                    ->orderBy('votes_count', $direction);
    }

    /**
     * Scope to order by recent activity on older bills.
     * This prioritizes bills that were introduced earlier but have recent actions,
     * rather than newly introduced bills.
     */
    public function scopeOrderByRecentActivity($query, $direction = 'desc')
    {
        // Calculate a score that favors older bills with recent activity
        // Formula: Days since latest action (negative for recent) + (Days since introduction / 10)
        // This gives higher scores to older bills with recent activity
        return $query->selectRaw('
            bills.*,
            CASE 
                WHEN latest_action_date IS NOT NULL AND introduced_date IS NOT NULL THEN
                    (DATEDIFF(NOW(), latest_action_date) * -1) + (DATEDIFF(NOW(), introduced_date) / 10)
                WHEN latest_action_date IS NOT NULL THEN
                    (DATEDIFF(NOW(), latest_action_date) * -1)
                ELSE
                    -999999
            END as activity_score
        ')->orderBy('activity_score', $direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * Get the bill sponsors.
     */
    public function sponsors(): HasMany
    {
        return $this->hasMany(BillSponsor::class);
    }

    /**
     * Get the bill actions.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(BillAction::class);
    }

    /**
     * Get the bill summaries.
     */
    public function summaries(): HasMany
    {
        return $this->hasMany(BillSummary::class);
    }

    /**
     * Get the bill subjects.
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(BillSubject::class);
    }

    /**
     * Get the bill cosponsors.
     */
    public function cosponsors(): HasMany
    {
        return $this->hasMany(BillCosponsor::class);
    }

    /**
     * Get the bill text versions.
     */
    public function textVersions(): HasMany
    {
        return $this->hasMany(BillTextVersion::class);
    }

    /**
     * Scope a query to only include bills from a specific chamber.
     */
    public function scopeChamber($query, $chamber)
    {
        return $query->where('chamber', $chamber);
    }

    /**
     * Scope a query to search bills by title or number.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('number', 'like', "%{$search}%")
              ->orWhere('sponsor_name', 'like', "%{$search}%");
        });
    }

    /**
     * Get formatted bill text for display
     */
    public function getFormattedBillTextAttribute(): ?string
    {
        if (!$this->bill_text) {
            return null;
        }

        return $this->formatTextForDisplay($this->bill_text);
    }

    /**
     * Format bill text for better readability in web display
     */
    private function formatTextForDisplay(string $text): string
    {
        // Split text into sections for better organization
        $sections = $this->identifyTextSections($text);
        
        $formatted = '';
        foreach ($sections as $section) {
            $formatted .= $this->formatSection($section) . "\n\n";
        }
        
        return trim($formatted);
    }

    /**
     * Identify different sections in the bill text
     */
    private function identifyTextSections(string $text): array
    {
        $sections = [];
        
        // Split by major section markers
        $parts = preg_split('/(?=\n\n=== .+ ===\n)|(?=\n\nSEC\. \d+)|(?=\n\nSECTION \d+)/i', $text);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (!empty($part)) {
                $sections[] = [
                    'content' => $part,
                    'type' => $this->identifySectionType($part)
                ];
            }
        }
        
        return $sections;
    }

    /**
     * Identify the type of section (title, section, subsection, etc.)
     */
    private function identifySectionType(string $content): string
    {
        if (preg_match('/^=== .+ ===/m', $content)) {
            return 'title';
        } elseif (preg_match('/^(SEC\.|SECTION) \d+/i', $content)) {
            return 'section';
        } elseif (preg_match('/^\s*\([a-zA-Z0-9]+\)/', $content)) {
            return 'subsection';
        } else {
            return 'paragraph';
        }
    }

    /**
     * Format a specific section based on its type
     */
    private function formatSection(array $section): string
    {
        $content = $section['content'];
        $type = $section['type'];
        
        switch ($type) {
            case 'title':
                return '<div class="bill-title font-bold text-lg text-blue-900 border-b-2 border-blue-200 pb-2 mb-4">' . 
                       htmlspecialchars($content) . '</div>';
                       
            case 'section':
                return '<div class="bill-section mb-6">' .
                       '<div class="section-header font-semibold text-base text-gray-800 mb-2">' .
                       htmlspecialchars($this->extractSectionHeader($content)) . '</div>' .
                       '<div class="section-content text-gray-700 leading-relaxed">' .
                       nl2br(htmlspecialchars($this->extractSectionContent($content))) . '</div></div>';
                       
            case 'subsection':
                return '<div class="bill-subsection ml-4 mb-3">' .
                       '<div class="subsection-content text-gray-700 leading-relaxed">' .
                       nl2br(htmlspecialchars($content)) . '</div></div>';
                       
            default:
                return '<div class="bill-paragraph mb-3 text-gray-700 leading-relaxed">' .
                       nl2br(htmlspecialchars($content)) . '</div>';
        }
    }

    /**
     * Extract section header from content
     */
    private function extractSectionHeader(string $content): string
    {
        if (preg_match('/^(SEC\.|SECTION) \d+[^.]*\.?/i', $content, $matches)) {
            return $matches[0];
        }
        
        $lines = explode("\n", $content);
        return $lines[0] ?? '';
    }

    /**
     * Extract section content (everything after the header)
     */
    private function extractSectionContent(string $content): string
    {
        $lines = explode("\n", $content);
        array_shift($lines); // Remove the header line
        return implode("\n", $lines);
    }
}
