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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('bioguide_id')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name');
            $table->string('direct_order_name');
            $table->string('inverted_order_name');
            $table->string('honorific_name')->nullable();
            $table->string('party_abbreviation');
            $table->string('party_name');
            $table->string('state')->nullable();
            $table->string('district')->nullable();
            $table->string('birth_year')->nullable();
            $table->boolean('current_member')->default(true);
            $table->string('image_url')->nullable();
            $table->string('image_attribution')->nullable();
            $table->string('official_website_url')->nullable();
            $table->string('office_address')->nullable();
            $table->string('office_city')->nullable();
            $table->string('office_phone')->nullable();
            $table->string('office_zip_code')->nullable();
            $table->integer('sponsored_legislation_count')->default(0);
            $table->integer('cosponsored_legislation_count')->default(0);
            $table->json('party_history')->nullable();
            $table->json('previous_names')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            
            $table->index(['bioguide_id']);
            $table->index(['party_abbreviation']);
            $table->index(['state']);
            $table->index(['current_member']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
