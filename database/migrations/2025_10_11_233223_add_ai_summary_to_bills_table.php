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
        Schema::table('bills', function (Blueprint $table) {
            if (!Schema::hasColumn('bills', 'ai_summary')) {
                $table->text('ai_summary')->nullable()->after('bill_text_source_url');
            }
            if (!Schema::hasColumn('bills', 'ai_summary_generated_at')) {
                $table->timestamp('ai_summary_generated_at')->nullable()->after('ai_summary');
            }
            if (!Schema::hasColumn('bills', 'ai_summary_metadata')) {
                $table->json('ai_summary_metadata')->nullable()->after('ai_summary_generated_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['ai_summary', 'ai_summary_generated_at', 'ai_summary_metadata']);
        });
    }
};
