<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('executive_orders', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('order_number')->nullable();
            $table->date('signed_date');
            $table->string('url');
            $table->longText('content')->nullable();
            $table->text('summary')->nullable();
            $table->json('topics')->nullable();
            $table->string('status')->default('active'); // active, revoked, superseded
            $table->text('ai_summary')->nullable();
            $table->longText('ai_summary_html')->nullable();
            $table->timestamp('ai_summary_generated_at')->nullable();
            $table->json('ai_summary_metadata')->nullable();
            $table->boolean('is_fully_scraped')->default(false);
            $table->timestamp('last_scraped_at')->nullable();
            $table->json('scraping_errors')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('signed_date');
            $table->index('status');
            $table->index('is_fully_scraped');
            $table->index('last_scraped_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executive_orders');
    }
};