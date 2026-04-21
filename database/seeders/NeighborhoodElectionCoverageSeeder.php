<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\Neighborhood;
use App\Models\PollingTable;
use Illuminate\Database\Seeder;

class NeighborhoodElectionCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $year = (int) $now->format('Y');

        $createdElections = 0;
        $closedDuplicatedActiveElections = 0;
        $createdTables = 0;
        $disabledExtraTables = 0;

        Neighborhood::query()
            ->select(['id', 'name', 'code'])
            ->orderBy('id')
            ->chunkById(200, function ($neighborhoods) use (
                $now,
                $year,
                &$createdElections,
                &$closedDuplicatedActiveElections,
                &$createdTables,
                &$disabledExtraTables
            ): void {
                foreach ($neighborhoods as $neighborhood) {
                    $activeElections = Election::query()
                        ->where('neighborhood_id', $neighborhood->id)
                        ->where('is_active', true)
                        ->orderByDesc('election_date')
                        ->orderByDesc('id')
                        ->get();

                    if ($activeElections->isEmpty()) {
                        $election = Election::create([
                            'neighborhood_id' => $neighborhood->id,
                            'name' => 'Eleccion JAC '.$neighborhood->name.' '.$year,
                            'code' => 'JAC-'.$neighborhood->code.'-'.$year.'-ACTIVA',
                            'election_date' => $now->toDateString(),
                            'period_year' => $year,
                            'is_active' => true,
                            'description' => 'Eleccion activa base creada por cobertura de seeders.',
                        ]);

                        $createdElections++;
                    } else {
                        $election = $activeElections->first();
                        $duplicatedIds = $activeElections->skip(1)->pluck('id');

                        if ($duplicatedIds->isNotEmpty()) {
                            $closedDuplicatedActiveElections += Election::query()
                                ->whereIn('id', $duplicatedIds)
                                ->update([
                                    'is_active' => false,
                                    'updated_at' => $now,
                                ]);
                        }
                    }

                    $activeTables = PollingTable::query()
                        ->where('election_id', $election->id)
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->get();

                    if ($activeTables->isEmpty()) {
                        PollingTable::create([
                            'election_id' => $election->id,
                            'code' => 'MESA-001',
                            'name' => 'Mesa Unica',
                            'location' => $neighborhood->name,
                            'capacity' => 500,
                            'is_active' => true,
                        ]);

                        $createdTables++;
                    } else {
                        $mainTable = $activeTables->first();
                        $extraTableIds = $activeTables->skip(1)->pluck('id');

                        if ($extraTableIds->isNotEmpty()) {
                            $disabledExtraTables += PollingTable::query()
                                ->whereIn('id', $extraTableIds)
                                ->update([
                                    'is_active' => false,
                                    'updated_at' => $now,
                                ]);
                        }

                        $mainTable->update([
                            'location' => $neighborhood->name,
                            'is_active' => true,
                        ]);
                    }
                }
            });

        $this->command?->info('Cobertura electoral por barrio finalizada.');
        $this->command?->info('Elecciones creadas: '.$createdElections);
        $this->command?->info('Elecciones activas duplicadas cerradas: '.$closedDuplicatedActiveElections);
        $this->command?->info('Mesas creadas: '.$createdTables);
        $this->command?->info('Mesas activas extra deshabilitadas: '.$disabledExtraTables);
    }
}
