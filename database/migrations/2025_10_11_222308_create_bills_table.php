<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            
            // Basic bill identification
            $table->string('congress_id')->unique(); // e.g., "119-s254"
            $table->integer('congress'); // e.g., 119
            $table->string('number'); // e.g., "254"
            $table->string('type'); // e.g., "S", "HR", "HJRES", etc.
            $table->string('origin_chamber')->nullable(); // "House" or "Senate"
            $table->string('origin_chamber_code')->nullable(); // "H" or "S"
            
            // Bill content
            $table->text('title');
            $table->text('short_title')->nullable();
            $table->text('policy_area')->nullable();
            
            // Dates
            $table->date('introduced_date')->nullable();
            $table->timestamp('update_date')->nullable();
            $table->timestamp('update_date_including_text')->nullable();
            
            // Latest action
            $table->date('latest_action_date')->nullable();
            $table->time('latest_action_time')->nullable();
            $table->text('latest_action_text')->nullable();
            
            // URLs and references
            $table->string('api_url')->nullable();
            $table->string('legislation_url')->nullable();
            
            // Counts for related data
            $table->integer('actions_count')->default(0);
            $table->integer('summaries_count')->default(0);
            $table->integer('subjects_count')->default(0);
            $table->integer('cosponsors_count')->default(0);
            $table->integer('text_versions_count')->default(0);
            $table->integer('committees_count')->default(0);
            
            // Bill text (latest version)
            $table->longText('bill_text')->nullable();
            $table->string('bill_text_version_type')->nullable();
            $table->timestamp('bill_text_date')->nullable();
            $table->string('bill_text_source_url')->nullable();
            
            // Processing status
            $table->boolean('is_fully_scraped')->default(false);
            $table->timestamp('last_scraped_at')->nullable();
            $table->json('scraping_errors')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['congress', 'type']);
            $table->index('introduced_date');
            $table->index('update_date');
            $table->index('latest_action_date');
            $table->index('is_fully_scraped');
            $table->index('policy_area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};