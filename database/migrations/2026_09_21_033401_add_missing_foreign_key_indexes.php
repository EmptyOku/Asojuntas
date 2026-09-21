<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * En Postgres (a diferencia de MySQL) una FOREIGN KEY no crea automáticamente
 * un índice en la columna que referencia. Este proyecto usa
 * foreignId()->constrained() en casi todas las migraciones, lo que crea la
 * restricción pero no el índice. Se auditaron las 78 FKs reales del esquema
 * contra pg_index y 41 no tenían ningún índice utilizable (ni siquiera como
 * columna izquierda de un índice compuesto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_draft_files', function (Blueprint $table) {
            $table->index('election_id');
            $table->index('uploaded_by_user_id');
        });

        Schema::table('candidate_drafts', function (Blueprint $table) {
            $table->index('block_id');
            $table->index('position_id');
            $table->index('slate_block_id');
            $table->index('slate_id');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->index('election_block_position_id');
            $table->index('person_id');
            $table->index('slate_block_id');
        });

        Schema::table('consolidated_block_results', function (Blueprint $table) {
            $table->index('election_block_id');
            $table->index('election_id');
            $table->index('slate_block_id');
        });

        Schema::table('consolidation_runs', function (Blueprint $table) {
            $table->index('created_by_user_id');
        });

        Schema::table('election_block_positions', function (Blueprint $table) {
            $table->index('block_id');
            $table->index('position_id');
        });

        Schema::table('election_blocks', function (Blueprint $table) {
            $table->index('block_id');
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->index('neighborhood_id');
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->index('neighborhood_id');
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->index('role_id');
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->index('assigned_by');
            $table->index('permission_id');
        });

        Schema::table('scrutiny_block_results', function (Blueprint $table) {
            $table->index('election_block_id');
            $table->index('election_id');
            $table->index('scrutiny_extraction_id');
            $table->index('slate_block_id');
        });

        Schema::table('scrutiny_elected_people', function (Blueprint $table) {
            $table->index('election_block_id');
            $table->index('election_block_position_id');
        });

        Schema::table('scrutiny_extractions', function (Blueprint $table) {
            $table->index('based_on_extraction_id');
            $table->index('created_by_user_id');
        });

        Schema::table('scrutiny_record_files', function (Blueprint $table) {
            $table->index('uploaded_by_user_id');
        });

        Schema::table('scrutiny_records', function (Blueprint $table) {
            $table->index('created_by_user_id');
        });

        Schema::table('scrutiny_reviews', function (Blueprint $table) {
            $table->index('reviewed_by_user_id');
        });

        Schema::table('seat_allocations', function (Blueprint $table) {
            $table->index('election_block_id');
            $table->index('election_block_position_id');
            $table->index('election_id');
            $table->index('slate_block_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('slate_blocks', function (Blueprint $table) {
            $table->index('election_block_id');
            $table->index('election_id');
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->index('assigned_by');
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_draft_files', function (Blueprint $table) {
            $table->dropIndex(['election_id']);
            $table->dropIndex(['uploaded_by_user_id']);
        });

        Schema::table('candidate_drafts', function (Blueprint $table) {
            $table->dropIndex(['block_id']);
            $table->dropIndex(['position_id']);
            $table->dropIndex(['slate_block_id']);
            $table->dropIndex(['slate_id']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['election_block_position_id']);
            $table->dropIndex(['person_id']);
            $table->dropIndex(['slate_block_id']);
        });

        Schema::table('consolidated_block_results', function (Blueprint $table) {
            $table->dropIndex(['election_block_id']);
            $table->dropIndex(['election_id']);
            $table->dropIndex(['slate_block_id']);
        });

        Schema::table('consolidation_runs', function (Blueprint $table) {
            $table->dropIndex(['created_by_user_id']);
        });

        Schema::table('election_block_positions', function (Blueprint $table) {
            $table->dropIndex(['block_id']);
            $table->dropIndex(['position_id']);
        });

        Schema::table('election_blocks', function (Blueprint $table) {
            $table->dropIndex(['block_id']);
        });

        Schema::table('elections', function (Blueprint $table) {
            $table->dropIndex(['neighborhood_id']);
        });

        Schema::table('persons', function (Blueprint $table) {
            $table->dropIndex(['neighborhood_id']);
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropIndex(['role_id']);
        });

        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropIndex(['assigned_by']);
            $table->dropIndex(['permission_id']);
        });

        Schema::table('scrutiny_block_results', function (Blueprint $table) {
            $table->dropIndex(['election_block_id']);
            $table->dropIndex(['election_id']);
            $table->dropIndex(['scrutiny_extraction_id']);
            $table->dropIndex(['slate_block_id']);
        });

        Schema::table('scrutiny_elected_people', function (Blueprint $table) {
            $table->dropIndex(['election_block_id']);
            $table->dropIndex(['election_block_position_id']);
        });

        Schema::table('scrutiny_extractions', function (Blueprint $table) {
            $table->dropIndex(['based_on_extraction_id']);
            $table->dropIndex(['created_by_user_id']);
        });

        Schema::table('scrutiny_record_files', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by_user_id']);
        });

        Schema::table('scrutiny_records', function (Blueprint $table) {
            $table->dropIndex(['created_by_user_id']);
        });

        Schema::table('scrutiny_reviews', function (Blueprint $table) {
            $table->dropIndex(['reviewed_by_user_id']);
        });

        Schema::table('seat_allocations', function (Blueprint $table) {
            $table->dropIndex(['election_block_id']);
            $table->dropIndex(['election_block_position_id']);
            $table->dropIndex(['election_id']);
            $table->dropIndex(['slate_block_id']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('slate_blocks', function (Blueprint $table) {
            $table->dropIndex(['election_block_id']);
            $table->dropIndex(['election_id']);
        });

        Schema::table('user_roles', function (Blueprint $table) {
            $table->dropIndex(['assigned_by']);
            $table->dropIndex(['role_id']);
        });
    }
};
