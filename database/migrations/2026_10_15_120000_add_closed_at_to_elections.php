<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hora en que se cerró cada elección (antes solo se sabía que estaba cerrada).
 *
 * Para las que ya estaban cerradas se toma la hora de la bitácora, si quedó
 * anotada; si no (el cierre masivo no deja fila por elección), la última
 * modificación de la elección, que es cuando se cerró.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('is_active');
        });

        $closed = DB::table('elections')->where('is_active', false)->get(['id', 'updated_at']);
        if ($closed->isEmpty()) {
            return;
        }

        $fromAudit = DB::table('audit_logs')
            ->where('auditable_type', 'App\\Models\\Election')
            ->where('action', 'updated')
            ->whereIn('auditable_id', $closed->pluck('id'))
            ->orderBy('id')
            ->get(['auditable_id', 'old_values', 'new_values', 'created_at'])
            ->filter(function ($row): bool {
                $old = json_decode((string) $row->old_values, true) ?: [];
                $new = json_decode((string) $row->new_values, true) ?: [];

                return array_key_exists('is_active', $new) && ! $new['is_active'] && ($old['is_active'] ?? true);
            })
            // El último cierre de cada elección.
            ->keyBy('auditable_id')
            ->map(fn ($row) => $row->created_at);

        foreach ($closed as $election) {
            DB::table('elections')->where('id', $election->id)->update([
                'closed_at' => $fromAudit[$election->id] ?? $election->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table): void {
            $table->dropColumn('closed_at');
        });
    }
};
