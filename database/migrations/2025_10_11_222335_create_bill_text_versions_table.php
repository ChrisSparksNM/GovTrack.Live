<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_text_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            
            // Version information
            $table->timestamp('date');
            $table->string('type'); // e.g., "Introduced in Senate", "Engrossed in Senate"
            
            // Format URLs
            $table->string('formatted_text_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('xml_url')->nullable();
            
            // Actual text content (if fetched)
            $table->longText('text_content')->nullable();
            $table->boolean('text_fetched')->default(false);
            $table->timestamp('text_fetched_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('bill_id');
            $table->index('date');
            $table->index('type');
            $table->index('text_fetched');
            
            // Prevent duplicate versions for the same bill
            $table->unique(['bill_id', 'type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_text_versions');
    }
};