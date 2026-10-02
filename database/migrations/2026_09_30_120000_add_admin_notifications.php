<?php

use App\Services\AdminNotifications;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones del administrador (campanita): "llegó un acta", "se registró
 * una plancha".
 *
 * No hay tabla nueva: los avisos son filas de audit_logs con una acción propia
 * (ver App\Services\AdminNotifications). Aquí solo se agrega:
 *  - users.notifications_seen_id: hasta qué aviso vio cada usuario (un número
 *    por usuario en vez de una fila "leído" por aviso y por administrador).
 *  - un índice (action, id) para contar y paginar los avisos sin recorrer la bitácora.
 *  - los avisos de las actas y planchas que ya existían, marcados como vistos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('notifications_seen_id')->default(0);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['action', 'id'], 'audit_logs_action_id_idx');
        });

        // Actas y planchas juntas en orden de fecha: la campana ordena por id.
        $rows = collect([...$this->actaRows(), ...$this->planchaRows()])->sortBy('created_at')->values();
        foreach ($rows->chunk(200) as $chunk) {
            DB::table('audit_logs')->insert($chunk->values()->all());
        }

        // Lo anterior a esta migración cuenta como visto: nadie abre la campana con cientos de avisos viejos.
        DB::table('users')->update(['notifications_seen_id' => (int) DB::table('audit_logs')->max('id')]);
    }

    public function down(): void
    {
        DB::table('audit_logs')->whereIn('action', array_keys(AdminNotifications::EVENTS))->delete();

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_action_id_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifications_seen_id');
        });
    }

    private function actaRows(): array
    {
        $rows = DB::table('scrutiny_records as r')
            ->leftJoin('elections as e', 'e.id', '=', 'r.election_id')
            ->leftJoin('neighborhoods as n', 'n.id', '=', 'e.neighborhood_id')
            ->leftJoin('polling_tables as t', 't.id', '=', 'r.polling_table_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.created_by_user_id')
            ->whereNull('r.deleted_at')
            ->orderBy('r.created_at')
            ->get(['r.id', 'r.created_at', 'r.created_by_user_id', 'n.id as neighborhood_id', 'n.name as neighborhood', 't.name as table_name', 'u.username']);

        return $rows->map(fn ($row) => [
                'user_id' => $row->created_by_user_id,
                'action' => AdminNotifications::ACTA_RECEIVED,
                'auditable_type' => 'App\\Models\\ScrutinyRecord',
                'auditable_id' => $row->id,
                'metadata' => json_encode(array_filter([
                    'neighborhood_id' => $row->neighborhood_id,
                    'neighborhood' => $row->neighborhood,
                    'polling_table' => $row->table_name,
                    'actor' => $row->username,
                ])),
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ])->all();
    }

    private function planchaRows(): array
    {
        $batches = DB::table('candidate_drafts as d')
            ->leftJoin('elections as e', 'e.id', '=', 'd.election_id')
            ->leftJoin('neighborhoods as n', 'n.id', '=', 'e.neighborhood_id')
            ->whereNotNull('d.capture_batch_uuid')
            ->whereNull('d.deleted_at')
            ->groupBy('d.capture_batch_uuid', 'd.election_id', 'n.id', 'n.name')
            ->orderByRaw('MIN(d.created_at)')
            ->get([
                'd.capture_batch_uuid', 'd.election_id', 'n.id as neighborhood_id', 'n.name as neighborhood',
                DB::raw('MIN(d.id) AS first_id'), DB::raw('MIN(d.created_at) AS created_at'), DB::raw('COUNT(*) AS total'),
            ]);

        // Quién capturó: la bitácora guardó el "created" de cada borrador.
        $authors = DB::table('audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.action', 'created')
            ->where('a.auditable_type', 'App\\Models\\CandidateDraft')
            ->whereIn('a.auditable_id', $batches->pluck('first_id'))
            ->get(['a.auditable_id', 'a.user_id', 'u.username'])
            ->keyBy('auditable_id');

        return $batches->map(function ($batch) use ($authors) {
                $author = $authors->get($batch->first_id);

                return [
                    'user_id' => $author?->user_id,
                    'action' => AdminNotifications::PLANCHA_CAPTURED,
                    'auditable_type' => 'App\\Models\\CandidateDraft',
                    'auditable_id' => $batch->first_id,
                    'metadata' => json_encode(array_filter([
                        'neighborhood_id' => $batch->neighborhood_id,
                        'neighborhood' => $batch->neighborhood,
                        'election_id' => $batch->election_id,
                        'batch' => $batch->capture_batch_uuid,
                        'candidates' => (int) $batch->total,
                        'actor' => $author?->username,
                    ])),
                    'created_at' => $batch->created_at,
                    'updated_at' => $batch->created_at,
                ];
            })->all();
    }
};
