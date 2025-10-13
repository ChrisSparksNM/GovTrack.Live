<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillCosponsor extends Model
{
    protected $fillable = [
        'bill_id',
        'bioguide_id',
        'full_name',
        'first_name',
        'last_name',
        'party',
        'state',
        'district',
        'sponsorship_date',
        'sponsorship_withdrawn_date'
    ];

    protected $casts = [
        'sponsorship_date' => 'date',
        'sponsorship_withdrawn_date' => 'date'
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'bioguide_id', 'bioguide_id');
    }
}