<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionBlock;
use App\Models\ElectionBlockPosition;
use App\Models\Neighborhood;
use App\Models\Person;
use App\Models\PollingTable;
use App\Models\ScrutinyBlockResult;
use App\Models\ScrutinyRecord;
use App\Models\Slate;
use App\Models\SlateBlock;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Datos de prueba para el mapa electoral: pone algunos barrios en verde
 * (acta consolidada con ganador), otros en amarillo (acta subida, sin
 * resultados aun) y otros explicitamente atrasados (eleccion vencida sin
 * acta), para poder ver la semaforizacion y las alertas funcionando.
 *
 * Idempotente: se puede correr varias veces sin duplicar datos. Solo toca
 * barrios de codigos fijos (comunas 1-4), nunca los seeders reales.
 */
class ElectionSeedTestData extends Command
{
    protected $signature = 'electoral:seed-test-data {--reset : Borra las actas/resultados de prueba antes de crearlas}';

    protected $description = 'Crea actas y resultados de prueba para ver la semaforizacion del mapa electoral';

    /** @var list<string> Verde: acta consolidada + candidato ganador. */
    private array $verdes = [
        'COM01-BLANCO',
        'COM01-CENTRO',
        'COM02-10-DE-MAYO',
        'COM03-BUENOS-AIRES',
        'COM04-CIUDAD-MONTES',
    ];

    /** @var list<string> Amarillo: acta subida, aun sin resultados escrutados. */
    private array $amarillos = [
        'COM01-GRANADA',
        'COM02-JVC-ACACIAS-II',
        'COM03-CAMBULOS-III',
        'COM04-EL-DIAMANTE',
    ];

    /** @var list<string> Atrasados: eleccion de ayer, sin ninguna acta. */
    private array $atrasados = [
        'COM01-SUCRE',
        'COM02-VILLA-DEL-RIO',
        'COM03-VIVISOL',
        'COM04-ZULIA',
    ];

    public function handle(): int
    {
        if ($this->option('reset')) {
            $this->resetTestData();
        }

        // "Hoy es el dia de elecciones": las comunas 1-4 (las que ya tienen
        // contorno y JAC ubicadas) quedan con eleccion de hoy, para que el
        // semaforo y las alertas de atraso tengan sentido en la demo.
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $touched = array_merge($this->verdes, $this->amarillos, $this->atrasados);

        $updated = Election::query()
            ->where('is_active', true)
            ->whereHas('neighborhood', fn ($q) => $q->whereHas('commune', fn ($c) => $c->whereIn('code', ['COM-01', 'COM-02', 'COM-03', 'COM-04'])))
            ->update(['election_date' => $today]);

        $this->info("Elecciones activas de comunas 1-4 puestas en hoy ({$today}): {$updated}.");

        foreach ($this->verdes as $code) {
            $this->seedVerde($code);
        }

        foreach ($this->amarillos as $code) {
            $this->seedAmarillo($code);
        }

        foreach ($this->atrasados as $code) {
            $this->seedAtrasado($code, $yesterday);
        }

        $this->newLine();
        $this->info('Listo. Verdes: '.count($this->verdes).' | Amarillos: '.count($this->amarillos).' | Atrasados: '.count($this->atrasados));

        return self::SUCCESS;
    }

    private function resetTestData(): void
    {
        $codes = array_merge($this->verdes, $this->amarillos, $this->atrasados);
        $electionIds = Election::query()
            ->whereHas('neighborhood', fn ($q) => $q->whereIn('code', $codes))
            ->pluck('id');

        ScrutinyBlockResult::whereIn('election_id', $electionIds)->delete();
        ScrutinyRecord::whereIn('election_id', $electionIds)->forceDelete();

        $this->warn('Actas y resultados de prueba anteriores eliminados.');
    }

    private function activeElectionFor(string $neighborhoodCode): ?Election
    {
        $neighborhood = Neighborhood::where('code', $neighborhoodCode)->first();

        if (! $neighborhood) {
            $this->warn("Barrio no encontrado: {$neighborhoodCode}");

            return null;
        }

        $election = $neighborhood->elections()->where('is_active', true)->latest('election_date')->first();

        if (! $election) {
            $this->warn("Sin eleccion activa: {$neighborhoodCode}");
        }

        return $election;
    }

    private function pollingTableFor(Election $election): PollingTable
    {
        return PollingTable::firstOrCreate(
            ['election_id' => $election->id, 'code' => 'MESA-001'],
            ['name' => 'Mesa Unica', 'location' => $election->neighborhood?->name, 'capacity' => 500, 'is_active' => true]
        );
    }

    private function actingUserId(): ?int
    {
        return User::where('username', 'superadmin')->value('id') ?? User::value('id');
    }

    /**
     * Bloque Directiva + dos planchas con presidente y vicepresidente, para
     * poder calcular un ganador. Reutiliza lo que ya exista.
     */
    private function ensureDirectivaSetup(Election $election): array
    {
        $blockId = DB::table('blocks')->where('code', 'DIR')->value('id');
        $posPresId = DB::table('positions')->where('code', 'DIR_PRES')->value('id');
        $posViceId = DB::table('positions')->where('code', 'DIR_VICE')->value('id');

        $electionBlock = ElectionBlock::firstOrCreate(
            ['election_id' => $election->id, 'block_id' => $blockId],
            ['is_active' => true]
        );

        $ebpPres = ElectionBlockPosition::firstOrCreate(
            ['election_block_id' => $electionBlock->id, 'position_id' => $posPresId],
            ['block_id' => $blockId, 'vacancies' => 1, 'is_active' => true]
        );
        $ebpVice = ElectionBlockPosition::firstOrCreate(
            ['election_block_id' => $electionBlock->id, 'position_id' => $posViceId],
            ['block_id' => $blockId, 'vacancies' => 1, 'is_active' => true]
        );

        $slateGanadora = Slate::firstOrCreate(
            ['election_id' => $election->id, 'code' => 'P1'],
            ['name' => 'Plancha Unidad Comunal', 'description' => 'Plancha de prueba', 'is_active' => true]
        );
        $slatePerdedora = Slate::firstOrCreate(
            ['election_id' => $election->id, 'code' => 'P2'],
            ['name' => 'Plancha Renovacion Barrial', 'description' => 'Plancha de prueba', 'is_active' => true]
        );

        $slateBlockGanadora = SlateBlock::firstOrCreate(
            ['slate_id' => $slateGanadora->id, 'election_block_id' => $electionBlock->id],
            ['election_id' => $election->id, 'is_active' => true]
        );
        $slateBlockPerdedora = SlateBlock::firstOrCreate(
            ['slate_id' => $slatePerdedora->id, 'election_block_id' => $electionBlock->id],
            ['election_id' => $election->id, 'is_active' => true]
        );

        $documentTypeId = DB::table('document_types')->where('code', 'CC')->value('id');
        $barrio = $election->neighborhood;
        $suffix = str_pad((string) $election->id, 6, '0', STR_PAD_LEFT);

        $personPres = Person::firstOrCreate(
            ['document_type_id' => $documentTypeId, 'document_number' => 'TP'.$suffix],
            ['neighborhood_id' => $barrio?->id, 'first_name' => 'Presidente', 'last_name' => 'Prueba '.$suffix, 'is_active' => true]
        );
        $personVice = Person::firstOrCreate(
            ['document_type_id' => $documentTypeId, 'document_number' => 'TV'.$suffix],
            ['neighborhood_id' => $barrio?->id, 'first_name' => 'Vicepresidente', 'last_name' => 'Prueba '.$suffix, 'is_active' => true]
        );

        Candidate::firstOrCreate(
            ['election_id' => $election->id, 'person_id' => $personPres->id],
            ['slate_block_id' => $slateBlockGanadora->id, 'election_block_position_id' => $ebpPres->id, 'ballot_number' => 'P1-01', 'is_active' => true]
        );
        Candidate::firstOrCreate(
            ['election_id' => $election->id, 'person_id' => $personVice->id],
            ['slate_block_id' => $slateBlockGanadora->id, 'election_block_position_id' => $ebpVice->id, 'ballot_number' => 'P1-02', 'is_active' => true]
        );

        return [$electionBlock->id, $slateBlockGanadora->id, $slateBlockPerdedora->id];
    }

    private function seedVerde(string $code): void
    {
        $election = $this->activeElectionFor($code);
        if (! $election) {
            return;
        }

        $pollingTable = $this->pollingTableFor($election);
        [$electionBlockId, $slateBlockGanadora, $slateBlockPerdedora] = $this->ensureDirectivaSetup($election);

        $record = ScrutinyRecord::where('election_id', $election->id)
            ->where('record_number', 'PRUEBA-VERDE')
            ->first();

        if (! $record) {
            $record = ScrutinyRecord::create([
                'election_id' => $election->id,
                'polling_table_id' => $pollingTable->id,
                'created_by_user_id' => $this->actingUserId(),
                'record_number' => 'PRUEBA-VERDE',
                'record_date' => now()->toDateString(),
                'record_time' => '16:00:00',
                'source_type' => 'manual',
                'status' => 'consolidated',
                'quorum_attendees' => 90,
                'total_attendees' => 120,
                'observations' => 'Acta de prueba (datos de demostracion).',
                'metadata' => ['demo_test' => true],
            ]);
        }

        if (! ScrutinyBlockResult::where('scrutiny_record_id', $record->id)->exists()) {
            ScrutinyBlockResult::create([
                'scrutiny_record_id' => $record->id,
                'election_id' => $election->id,
                'election_block_id' => $electionBlockId,
                'slate_block_id' => $slateBlockGanadora,
                'votes' => 82,
                'source_type' => 'manual',
                'status' => 'validated',
            ]);
            ScrutinyBlockResult::create([
                'scrutiny_record_id' => $record->id,
                'election_id' => $election->id,
                'election_block_id' => $electionBlockId,
                'slate_block_id' => $slateBlockPerdedora,
                'votes' => 38,
                'source_type' => 'manual',
                'status' => 'validated',
            ]);
        }

        $this->line("  verde     {$code}");
    }

    private function seedAmarillo(string $code): void
    {
        $election = $this->activeElectionFor($code);
        if (! $election) {
            return;
        }

        $pollingTable = $this->pollingTableFor($election);

        $exists = ScrutinyRecord::where('election_id', $election->id)
            ->where('record_number', 'PRUEBA-AMARILLO')
            ->exists();

        if (! $exists) {
            ScrutinyRecord::create([
                'election_id' => $election->id,
                'polling_table_id' => $pollingTable->id,
                'created_by_user_id' => $this->actingUserId(),
                'record_number' => 'PRUEBA-AMARILLO',
                'record_date' => now()->toDateString(),
                'record_time' => '15:10:00',
                'source_type' => 'manual',
                'status' => 'pending_review',
                'quorum_attendees' => 60,
                'total_attendees' => 95,
                'observations' => 'Acta de prueba, aun sin escrutar (datos de demostracion).',
                'metadata' => ['demo_test' => true],
            ]);
        }

        $this->line("  amarillo  {$code}");
    }

    private function seedAtrasado(string $code, string $yesterday): void
    {
        $election = $this->activeElectionFor($code);
        if (! $election) {
            return;
        }

        // Vencida desde ayer y sin ninguna acta: debe marcar "atrasada".
        $election->update(['election_date' => $yesterday]);

        $this->line("  atrasado  {$code}");
    }
}
