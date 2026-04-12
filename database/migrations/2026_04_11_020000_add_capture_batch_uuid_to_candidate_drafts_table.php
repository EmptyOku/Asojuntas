<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_drafts', function (Blueprint $table): void {
            $table->uuid('capture_batch_uuid')->nullable()->after('slate_block_id');
            $table->index(['capture_batch_uuid'], 'candidate_drafts_capture_batch_idx');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_drafts', function (Blueprint $table): void {
            $table->dropIndex('candidate_drafts_capture_batch_idx');
            $table->dropColumn('capture_batch_uuid');
        });
    }
};
