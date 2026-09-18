<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep only the newest file per (scrutiny_record_id, page_number) before adding uniqueness.
        // `DELETE ... USING` es exclusivo de PostgreSQL; en el resto de motores
        // (sqlite en las pruebas) se resuelve con una subconsulta portable.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                DELETE FROM scrutiny_record_files t
                USING (
                    SELECT id
                    FROM (
                        SELECT
                            id,
                            ROW_NUMBER() OVER (
                                PARTITION BY scrutiny_record_id, page_number
                                ORDER BY updated_at DESC NULLS LAST, created_at DESC NULLS LAST, id DESC
                            ) AS rn
                        FROM scrutiny_record_files
                        WHERE page_number IS NOT NULL
                    ) ranked
                    WHERE ranked.rn > 1
                ) dup
                WHERE t.id = dup.id
            SQL);
        } else {
            DB::statement(<<<'SQL'
                DELETE FROM scrutiny_record_files
                WHERE page_number IS NOT NULL
                  AND id NOT IN (
                      SELECT MAX(id)
                      FROM scrutiny_record_files
                      WHERE page_number IS NOT NULL
                      GROUP BY scrutiny_record_id, page_number
                  )
            SQL);
        }

        Schema::table('scrutiny_record_files', function (Blueprint $table): void {
            $table->unique(['scrutiny_record_id', 'page_number'], 'scrutiny_record_files_record_page_unique');
        });
    }

    public function down(): void
    {
        Schema::table('scrutiny_record_files', function (Blueprint $table): void {
            $table->dropUnique('scrutiny_record_files_record_page_unique');
        });
    }
};
