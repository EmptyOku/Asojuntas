<?php

namespace Tests\Feature;

use App\Models\ScrutinyRecord;
use App\Services\AdminNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Campanita del administrador: avisos de actas recibidas y planchas
 * registradas por otros roles, con paginación y "visto".
 */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private function capturePlancha($secretary, int $electionId, ?string $batch = null)
    {
        return $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts', array_filter([
            'election_id' => $electionId,
            'capture_batch_uuid' => $batch,
            'review_page_data' => ['bloques' => [['titulo' => 'Directiva', 'cargos' => [
                ['puesto' => 'PRESIDENTE', 'nombre' => 'Ana Prueba', 'identificacion' => '5550001'],
                ['puesto' => 'VICEPRESIDENTE', 'nombre' => 'Beto Prueba', 'identificacion' => '5550002'],
            ]]]],
        ]))->assertCreated()->json('data.capture_batch_uuid');
    }

    private function actaNotification($jury, ScrutinyRecord $record): void
    {
        $this->actingAs($jury);
        app(AdminNotifications::class)->record(AdminNotifications::ACTA_RECEIVED, [
            'neighborhood' => 'Barrio Avisos',
            'polling_table' => 'Mesa 1',
        ], ScrutinyRecord::class, $record->id);
    }

    #[Test]
    public function registrar_una_plancha_avisa_al_administrador_y_no_a_quien_la_registro(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Avisos'));
        $secretary = $this->makeUser(['slates.capture', 'slates.view']);
        $admin = $this->makeUser(['records.review', 'slates.view']);

        $batch = $this->capturePlancha($secretary, $election->id);

        $this->actingAs($admin)->getJson('/api/admin/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unread', 1);

        $item = $this->actingAs($admin)->getJson('/api/admin/notifications')->assertOk()->json('data.items.0');
        $this->assertSame('plancha', $item['type']);
        $this->assertSame('Se registró una plancha', $item['title']);
        $this->assertStringContainsString('Barrio Avisos', $item['detail']);
        $this->assertSame($batch, $item['batch']);
        $this->assertTrue($item['unread']);

        // Quien la registró no recibe aviso de su propia acción.
        $this->actingAs($secretary)->getJson('/api/admin/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.unread', 0);
    }

    #[Test]
    public function corregir_una_plancha_existente_no_genera_otro_aviso(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Avisos'));
        $secretary = $this->makeUser(['slates.capture', 'slates.review']);
        $admin = $this->makeUser(['records.review', 'slates.view']);

        $batch = $this->capturePlancha($secretary, $election->id);
        $this->capturePlancha($secretary, $election->id, $batch);

        $this->actingAs($admin)->getJson('/api/admin/notifications/unread-count')
            ->assertJsonPath('data.unread', 1);
    }

    #[Test]
    public function cada_usuario_ve_solo_los_avisos_de_sus_permisos(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Avisos'));
        $record = ScrutinyRecord::create([
            'election_id' => $election->id,
            'polling_table_id' => $this->makePollingTable($election)->id,
            'record_number' => 'ACTA-1',
            'status' => 'draft',
        ]);
        $this->actaNotification($this->makeUser(['records.upload']), $record);

        $slatesOnly = $this->makeUser(['slates.view']);
        $this->actingAs($slatesOnly)->getJson('/api/admin/notifications/unread-count')
            ->assertJsonPath('data.unread', 0);

        $auditor = $this->makeUser(['records.review']);
        $item = $this->actingAs($auditor)->getJson('/api/admin/notifications')->json('data.items.0');
        $this->assertSame('acta', $item['type']);
        $this->assertSame($record->id, $item['record_id']);
        $this->assertSame('Barrio Avisos · Mesa 1', $item['detail']);

        // Sin ninguno de los dos permisos no hay campana.
        $this->actingAs($this->makeUser(['users.view']))
            ->getJson('/api/admin/notifications/unread-count')->assertForbidden();
    }

    #[Test]
    public function pagina_los_avisos_y_marcarlos_como_vistos_deja_el_contador_en_cero(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Avisos'));
        $table = $this->makePollingTable($election);
        $jury = $this->makeUser(['records.upload']);

        foreach (range(1, 7) as $n) {
            $record = ScrutinyRecord::create([
                'election_id' => $election->id, 'polling_table_id' => $table->id,
                'record_number' => "ACTA-{$n}", 'status' => 'draft',
            ]);
            $this->actaNotification($jury, $record);
        }

        $admin = $this->makeUser(['records.review']);
        $first = $this->actingAs($admin)->getJson('/api/admin/notifications?per_page=5')->assertOk();
        $this->assertCount(5, $first->json('data.items'));
        $this->assertTrue($first->json('data.has_more'));
        $this->assertSame('ACTA-7', ScrutinyRecord::find($first->json('data.items.0.record_id'))->record_number, 'El más reciente primero.');

        $second = $this->actingAs($admin)->getJson('/api/admin/notifications?per_page=5&page=2')->assertOk();
        $this->assertCount(2, $second->json('data.items'));
        $this->assertFalse($second->json('data.has_more'));

        $this->actingAs($admin)->getJson('/api/admin/notifications/unread-count')->assertJsonPath('data.unread', 7);
        $this->actingAs($admin)->postJson('/api/admin/notifications/seen')->assertOk();
        $this->actingAs($admin->fresh())->getJson('/api/admin/notifications/unread-count')->assertJsonPath('data.unread', 0);
        $this->assertFalse($this->actingAs($admin->fresh())->getJson('/api/admin/notifications')->json('data.items.0.unread'));
    }

    #[Test]
    public function un_acta_de_varias_paginas_genera_un_solo_aviso(): void
    {
        Storage::fake('local');
        $barrio = $this->makeNeighborhood('Barrio Mesa');
        $mesa = $this->makePollingTable($this->makeElection($barrio));
        $jurado = $this->makeUser(['records.upload'], $barrio);

        foreach ([1, 2] as $page) {
            $this->actingAs($jurado)->postJson('/api/jury/submit', [
                'polling_table_id' => $mesa->id,
                'document_file' => UploadedFile::fake()->image("acta-{$page}.jpg", 100 + $page, 100), // distinto contenido por página
                'page_number' => $page,
                'normalized_payload' => json_encode(['block_results' => []]),
            ])->assertCreated();
        }

        $admin = $this->makeUser(['records.review']);
        $items = $this->actingAs($admin)->getJson('/api/admin/notifications')->assertOk()->json('data.items');

        $this->assertCount(1, $items, 'La segunda página es la misma acta: no repite el aviso.');
        $this->assertSame('Llegó un acta nueva', $items[0]['title']);
        $this->assertStringContainsString('Barrio Mesa', $items[0]['detail']);
        $this->assertSame($jurado->username, $items[0]['actor']);
    }
}
