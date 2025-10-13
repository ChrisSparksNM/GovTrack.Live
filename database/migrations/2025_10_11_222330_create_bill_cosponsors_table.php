<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_cosponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            
            // Cosponsor information
            $table->string('bioguide_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name');
            $table->string('party')->nullable(); // D, R, I, etc.
            $table->string('state')->nullable(); // State abbreviation
            $table->string('district')->nullable(); // For House members
            $table->date('sponsorship_date')->nullable();
            $table->boolean('is_original_cosponsor')->default(false);
            $table->date('sponsorship_withdrawn_date')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('bill_id');
            $table->index('bioguide_id');
            $table->index(['party', 'state']);
            $table->index('sponsorship_date');
            $table->index('is_original_cosponsor');
            
            // Prevent duplicate cosponsors for the same bill
            $table->unique(['bill_id', 'bioguide_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_cosponsors');
    }
};