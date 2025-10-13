<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->onDelete('cascade');
            
            // Subject information
            $table->string('name');
            $table->string('type')->default('legislative'); // 'legislative' or 'policy_area'
            
            $table->timestamps();
            
            // Indexes
            $table->index('bill_id');
            $table->index('name');
            $table->index('type');
            
            // Prevent duplicate subjects for the same bill
            $table->unique(['bill_id', 'name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_subjects');
    }
};