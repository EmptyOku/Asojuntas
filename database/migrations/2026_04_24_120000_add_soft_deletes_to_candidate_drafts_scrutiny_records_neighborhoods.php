<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_drafts', function (Blueprint $table): void {
            if (! Schema::hasColumn('candidate_drafts', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('scrutiny_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('scrutiny_records', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('neighborhoods', function (Blueprint $table): void {
            if (! Schema::hasColumn('neighborhoods', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidate_drafts', function (Blueprint $table): void {
            if (Schema::hasColumn('candidate_drafts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('scrutiny_records', function (Blueprint $table): void {
            if (Schema::hasColumn('scrutiny_records', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('neighborhoods', function (Blueprint $table): void {
            if (Schema::hasColumn('neighborhoods', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
