<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackedBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bill_id',
        'notes',
        'notification_preferences',
        'tracked_at',
    ];

    protected $casts = [
        'notification_preferences' => 'array',
        'tracked_at' => 'datetime',
    ];

    /**
     * Get the user who is tracking this bill
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the bill being tracked
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}