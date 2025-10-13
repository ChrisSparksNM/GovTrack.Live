<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'action_date',
        'action_time',
        'text',
        'type',
        'action_code',
        'source_system',
        'committees',
        'recorded_votes',
    ];

    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'action_time' => 'datetime',
            'committees' => 'array',
            'recorded_votes' => 'array',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}