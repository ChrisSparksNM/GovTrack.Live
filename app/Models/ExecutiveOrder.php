<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ExecutiveOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'order_number',
        'signed_date',
        'url',
        'content',
        'summary',
        'topics',
        'status',
        'ai_summary',
        'ai_summary_html',
        'ai_summary_generated_at',
        'ai_summary_metadata',
        'is_fully_scraped',
        'last_scraped_at',
        'scraping_errors',
    ];

    protected $casts = [
        'signed_date' => 'date',
        'topics' => 'array',
        'ai_summary_generated_at' => 'datetime',
        'ai_summary_metadata' => 'array',
        'is_fully_scraped' => 'boolean',
        'last_scraped_at' => 'datetime',
        'scraping_errors' => 'array',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate slug from title
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => \Str::slug($value),
        );
    }

    /**
     * Get executive orders by year
     */
    public function scopeByYear($query, int $year)
    {
        return $query->whereYear('signed_date', $year);
    }

    /**
     * Get recent executive orders
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('signed_date', '>=', now()->subDays($days));
    }

    /**
     * Get active executive orders
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get fully scraped executive orders
     */
    public function scopeFullyScraped($query)
    {
        return $query->where('is_fully_scraped', true);
    }

    /**
     * Get executive orders that need scraping
     */
    public function scopeNeedsScraping($query)
    {
        return $query->where('is_fully_scraped', false)
                    ->orWhere('last_scraped_at', '<', now()->subDays(7));
    }

    /**
     * Get the display name for the executive order
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->order_number) {
            return "Executive Order {$this->order_number}";
        }
        
        return $this->title;
    }

    /**
     * Get the short title (first 100 characters)
     */
    public function getShortTitleAttribute(): string
    {
        return \Str::limit($this->title, 100);
    }

    /**
     * Get the year from signed date
     */
    public function getYearAttribute(): int
    {
        return $this->signed_date->year;
    }

    /**
     * Check if the executive order has content
     */
    public function hasContent(): bool
    {
        return !empty($this->content);
    }

    /**
     * Check if the executive order has AI summary
     */
    public function hasAiSummary(): bool
    {
        return !empty($this->ai_summary);
    }

    /**
     * Get word count of content
     */
    public function getWordCountAttribute(): int
    {
        if (!$this->content) {
            return 0;
        }
        
        return str_word_count(strip_tags($this->content));
    }

    /**
     * Get reading time estimate in minutes
     */
    public function getReadingTimeAttribute(): int
    {
        $wordCount = $this->word_count;
        return max(1, ceil($wordCount / 200)); // Average reading speed: 200 words per minute
    }
}