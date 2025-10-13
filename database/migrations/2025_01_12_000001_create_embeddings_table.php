<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // 'bill', 'member', 'bill_action', etc.
            $table->unsignedBigInteger('entity_id');
            $table->longText('embedding'); // JSON array of floats
            $table->longText('content'); // The text that was embedded
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();
            
            $table->index(['entity_type', 'entity_id']);
            $table->index('entity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embeddings');
    }
};