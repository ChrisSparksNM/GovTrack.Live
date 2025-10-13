<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillTextVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id',
        'date',
        'type',
        'formatted_text_url',
        'pdf_url',
        'xml_url',
        'text_content',
        'text_fetched',
        'text_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'text_fetched' => 'boolean',
            'text_fetched_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}