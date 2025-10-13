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
        Schema::create('tracked_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            $table->string('notes')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->timestamp('tracked_at')->useCurrent();
            $table->timestamps();
            
            // Ensure a user can't track the same bill twice
            $table->unique(['user_id', 'bill_id']);
            
            // Index for faster queries
            $table->index(['user_id', 'tracked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracked_bills');
    }
};