<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillSummary extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bill_id',
        'action_date',
        'action_desc',
        'text',
        'update_date',
        'version_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'update_date' => 'datetime',
        ];
    }

    /**
     * Get the bill that owns this summary.
     */
    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
