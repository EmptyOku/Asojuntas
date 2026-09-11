<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<array{legacy: string, partial: string, column: string}> */
    private array $indexes = [
        [
            'legacy' => 'neighborhoods_commune_id_code_unique',
            'partial' => 'neighborhoods_commune_code_active_unique',
            'column' => 'code',
        ],
        [
            'legacy' => 'neighborhoods_commune_id_name_unique',
            'partial' => 'neighborhoods_commune_name_active_unique',
            'column' => 'name',
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $index) {
            $this->dropIndexIfExists($index['legacy']);
        }

        if ($this->supportsPartialIndexes()) {
            foreach ($this->indexes as $index) {
                DB::statement(sprintf(
                    'CREATE UNIQUE INDEX %s ON neighborhoods (commune_id, %s) WHERE deleted_at IS NULL',
                    $index['partial'],
                    $index['column']
                ));
            }

            return;
        }

        Schema::table('neighborhoods', function (Blueprint $table): void {
            foreach ($this->indexes as $index) {
                $table->unique(['commune_id', $index['column']], $index['partial']);
            }
        });
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            $this->dropIndexIfExists($index['partial']);
        }

        Schema::table('neighborhoods', function (Blueprint $table): void {
            foreach ($this->indexes as $index) {
                $table->unique(['commune_id', $index['column']], $index['legacy']);
            }
        });
    }

    private function supportsPartialIndexes(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true);
    }

    private function dropIndexIfExists(string $name): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE neighborhoods DROP CONSTRAINT IF EXISTS '.$name);

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS '.$name);

            return;
        }

        try {
            Schema::table('neighborhoods', function (Blueprint $table) use ($name): void {
                $table->dropUnique($name);
            });
        } catch (Throwable) {
        }
    }
};