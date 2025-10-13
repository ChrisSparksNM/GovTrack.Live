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
        if (!Schema::hasTable('bill_votes')) {
            Schema::create('bill_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            $table->enum('vote_type', ['up', 'down']);
            $table->timestamps();
            
            // Ensure one vote per user per bill
            $table->unique(['user_id', 'bill_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_votes');
    }
};
