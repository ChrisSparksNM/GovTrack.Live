<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bill_actions')) {
            Schema::create('bill_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            
            // Action details
            $table->date('action_date');
            $table->time('action_time')->nullable();
            $table->text('text');
            $table->string('type')->nullable();
            $table->string('action_code')->nullable();
            $table->string('source_system')->nullable();
            
            // Committee information (if applicable)
            $table->json('committees')->nullable();
            
            // Recording information
            $table->json('recorded_votes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('bill_id');
            $table->index('action_date');
            $table->index('type');
            $table->index('source_system');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_actions');
    }
};