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
        // This migration is deprecated - the application now uses 'tracked_bills' table instead
        // Skip creation to avoid conflicts with existing tables
        
        // Note: If you need to create this table for legacy reasons, uncomment below:
        /*
        if (!Schema::hasTable('user_bills')) {
            Schema::create('user_bills', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('bill_id')->constrained()->onDelete('cascade');
                $table->timestamps();
                
                $table->unique(['user_id', 'bill_id']);
                $table->index('user_id');
                $table->index('bill_id');
            });
        }
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop if it exists and is not being used by other parts of the application
        Schema::dropIfExists('user_bills');
    }
};
