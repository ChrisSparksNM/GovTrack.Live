<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            
            // Sponsor information
            $table->string('bioguide_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('full_name');
            $table->string('party')->nullable(); // D, R, I, etc.
            $table->string('state')->nullable(); // State abbreviation
            $table->string('district')->nullable(); // For House members
            $table->string('is_by_request')->nullable(); // Y/N
            
            $table->timestamps();
            
            // Indexes
            $table->index('bill_id');
            $table->index('bioguide_id');
            $table->index(['party', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_sponsors');
    }
};