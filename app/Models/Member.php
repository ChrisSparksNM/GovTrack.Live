<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'bioguide_id',
        'first_name',
        'last_name',
        'full_name',
        'direct_order_name',
        'inverted_order_name',
        'honorific_name',
        'party_abbreviation',
        'party_name',
        'state',
        'district',
        'chamber',
        'birth_year',
        'current_member',
        'image_url',
        'image_attribution',
        'official_website_url',
        'office_address',
        'office_city',
        'office_phone',
        'office_zip_code',
        'sponsored_legislation_count',
        'cosponsored_legislation_count',
        'party_history',
        'previous_names',
        'last_updated_at',
    ];

    protected $casts = [
        'current_member' => 'boolean',
        'sponsored_legislation_count' => 'integer',
        'cosponsored_legislation_count' => 'integer',
        'party_history' => 'array',
        'previous_names' => 'array',
        'last_updated_at' => 'datetime',
    ];

    /**
     * Get the bills sponsored by this member.
     */
    public function sponsoredBills(): HasMany
    {
        return $this->hasMany(BillSponsor::class, 'bioguide_id', 'bioguide_id');
    }

    /**
     * Get the bills cosponsored by this member.
     */
    public function cosponsoredBills(): HasMany
    {
        return $this->hasMany(BillCosponsor::class, 'bioguide_id', 'bioguide_id');
    }

    /**
     * Get the member's current party.
     */
    public function getCurrentPartyAttribute(): string
    {
        return $this->party_abbreviation . ' - ' . $this->party_name;
    }

    /**
     * Get the member's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->honorific_name ? 
            $this->honorific_name . ' ' . $this->direct_order_name : 
            $this->direct_order_name;
    }

    /**
     * Get the member's state and district display.
     */
    public function getLocationDisplayAttribute(): string
    {
        if ($this->chamber === 'house' && $this->district) {
            return $this->state . '-' . $this->district;
        }
        return $this->state ?: 'Unknown';
    }

    /**
     * Get the member's chamber display name.
     */
    public function getChamberDisplayAttribute(): string
    {
        return match($this->chamber) {
            'house' => 'House of Representatives',
            'senate' => 'Senate',
            default => 'Unknown Chamber'
        };
    }

    /**
     * Get the member's title based on chamber.
     */
    public function getTitleAttribute(): string
    {
        return match($this->chamber) {
            'house' => 'Representative',
            'senate' => 'Senator',
            default => 'Member'
        };
    }

    /**
     * Scope to get current members only.
     */
    public function scopeCurrent($query)
    {
        return $query->where('current_member', true);
    }

    /**
     * Scope to filter by party.
     */
    public function scopeParty($query, $party)
    {
        return $query->where('party_abbreviation', $party);
    }

    /**
     * Scope to filter by state.
     */
    public function scopeState($query, $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope to filter by chamber.
     */
    public function scopeChamber($query, $chamber)
    {
        return $query->where('chamber', $chamber);
    }
}
