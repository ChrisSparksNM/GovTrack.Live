<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the bills tracked by this user.
     */
    public function trackedBills()
    {
        return $this->hasMany(TrackedBill::class);
    }

    /**
     * Get the bills this user is tracking (direct relationship).
     */
    public function bills()
    {
        return $this->belongsToMany(Bill::class, 'tracked_bills')
                    ->withPivot(['notes', 'notification_preferences', 'tracked_at'])
                    ->withTimestamps();
    }

    /**
     * Get the votes this user has cast.
     */
    public function billVotes(): HasMany
    {
        return $this->hasMany(BillVote::class);
    }
}
