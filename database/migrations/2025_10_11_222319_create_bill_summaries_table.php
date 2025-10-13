<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bill_summaries')) {
            Schema::create('bill_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            
            // Summary details
            $table->date('action_date')->nullable();
            $table->string('action_desc')->nullable();
            $table->longText('text')->nullable();
            $table->timestamp('update_date')->nullable();
            $table->string('version_code')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('bill_id');
            $table->index('action_date');
            $table->index('version_code');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_summaries');
    }
};