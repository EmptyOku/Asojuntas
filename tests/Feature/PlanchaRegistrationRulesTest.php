<?php

namespace Tests\Feature;

use App\Models\CandidateDraft;
use App\Models\DocumentType;
use App\Models\Person;
use App\Models\Role;
use App\Models\ScrutinyRecord;
use App\Support\PersonData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ElectoralScenario;
use Tests\TestCase;

/**
 * Reglas del registro de planchas:
 *  - con un acta aprobada ya no entran planchas nuevas;
 *  - se avisa cuando un candidato ya está registrado;
 *  - nombres y documentos se guardan en un formato único.
 */
class PlanchaRegistrationRulesTest extends TestCase
{
    use RefreshDatabase;
    use ElectoralScenario;

    private function capture($user, array $payload)
    {
        return $this->actingAs($user)->postJson('/api/secretary/planchas/drafts', $payload);
    }

    private function cargos(array $rows): array
    {
        return ['bloques' => [['titulo' => 'Directiva', 'cargos' => array_map(
            fn ($row) => ['puesto' => $row[0], 'nombre' => $row[1], 'identificacion' => $row[2]],
            $rows,
        )]]];
    }

    private function inboxCandidates($user): \Illuminate\Support\Collection
    {
        $data = $this->actingAs($user)->getJson('/api/secretary/planchas/drafts/grouped')->assertOk()->json('data');

        return collect($data)->flatMap(fn ($n) => $n['batches'])->flatMap(fn ($b) => $b['blocks'])->flatMap(fn ($b) => $b['candidates']);
    }

    #[Test]
    public function con_un_acta_aprobada_no_se_registran_planchas_nuevas_pero_si_se_corrigen_las_existentes(): void
    {
        $neighborhood = $this->makeNeighborhood('Barrio Cerrado');
        $election = $this->makeElection($neighborhood);
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve']);

        $batch = $this->capture($secretary, [
            'election_id' => $election->id,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'Ana Prueba', '5550001']]),
        ])->assertCreated()->json('data.capture_batch_uuid');

        ScrutinyRecord::create([
            'election_id' => $election->id,
            'polling_table_id' => $this->makePollingTable($election)->id,
            'record_number' => 'ACTA-1',
            'status' => 'approved',
        ]);

        // Plancha nueva: rechazada.
        $this->capture($secretary, [
            'election_id' => $election->id,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'Otra Persona', '5550002']]),
        ])->assertUnprocessable()->assertJsonValidationErrors('election_id');
        $this->assertSame(1, CandidateDraft::count());

        // Corregir la que ya existía: permitido.
        $this->capture($secretary, [
            'capture_batch_uuid' => $batch,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'Ana Corregida', '5550001']]),
        ])->assertCreated();
        $this->assertSame('Corregida', CandidateDraft::first()->last_name);

        // El buscador de la captura lo avisa para no dejar ni empezar.
        $this->actingAs($secretary)->getJson('/api/secretary/neighborhoods/search?q=Cerrado')
            ->assertOk()->assertJsonPath('data.0.active_election.has_approved_acta', true);
    }

    #[Test]
    public function avisa_cuando_un_candidato_ya_esta_registrado(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Repetidos'));
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'candidate_drafts.promote']);

        // Plancha 1: se aprueba y se oficializa.
        $first = $this->capture($secretary, [
            'election_id' => $election->id,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'Ana Prueba', '5550001']]),
        ])->assertCreated()->json('data.capture_batch_uuid');
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $first])->assertOk();
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $first])->assertOk();

        // Plancha 2: repite a Ana (ya oficial), repite a Beto dentro de la misma plancha.
        $this->capture($secretary, [
            'election_id' => $election->id,
            'review_page_data' => $this->cargos([
                ['PRESIDENTE', 'Ana Prueba', '5.550.001'], // con puntos: es el mismo documento
                ['VICEPRESIDENTE', 'Beto Prueba', '5550002'],
                ['TESORERO', 'Beto Prueba', '5550002'],
                ['SECRETARIO', 'Carla Limpia', '5550003'],
            ]),
        ])->assertCreated();

        $candidates = $this->inboxCandidates($secretary)->where('is_processed', false)->keyBy('cargo');

        $this->assertStringContainsString('Ya es candidato oficial en la Plancha 1', $candidates['PRESIDENTE']['warnings'][0]);
        $this->assertSame(['El mismo documento aparece dos veces en esta plancha.'], $candidates['VICEPRESIDENTE']['warnings']);
        $this->assertSame(['El mismo documento aparece dos veces en esta plancha.'], $candidates['TESORERO']['warnings']);
        $this->assertSame([], $candidates['SECRETARIO']['warnings']);

        // Si aun así se aprueba y oficializa, Ana NO se mueve de la Plancha 1:
        // antes pasaba a la Plancha 2 y su cargo original quedaba vacío.
        $second = CandidateDraft::where('is_processed', false)->value('capture_batch_uuid');
        $anaBefore = \App\Models\Candidate::whereHas('person', fn ($q) => $q->where('document_number', '5550001'))->firstOrFail();

        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $second])->assertOk();
        $promotion = $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $second])->assertOk();

        $this->assertStringContainsString('ya es candidato oficial en la Plancha 1', collect($promotion->json('data.issues'))->pluck('reason')->implode(' '));
        $this->assertSame($anaBefore->slate_block_id, $anaBefore->fresh()->slate_block_id, 'Ana sigue en su plancha original.');
        $this->assertSame(1, \App\Models\Candidate::where('person_id', $anaBefore->person_id)->count());
    }

    #[Test]
    public function cada_plancha_registrada_queda_con_su_propio_numero(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Tres Planchas'));
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'candidate_drafts.promote', 'slates.view', 'candidates.view']);
        $register = fn (array $extra) => $this->capture($secretary, ['election_id' => $election->id, ...$extra]);

        // Sin número: toma el primero libre. Con número: se respeta.
        $first = $register(['review_page_data' => $this->cargos([['PRESIDENTE', 'Ana Uno', '7770001']])])->assertCreated();
        $third = $register(['slate_code' => 'P3', 'review_page_data' => $this->cargos([['PRESIDENTE', 'Ana Tres', '7770003']])])->assertCreated();
        $second = $register(['review_page_data' => $this->cargos([['PRESIDENTE', 'Ana Dos', '7770002']])])->assertCreated();

        $this->assertSame(['P1', 'P3', 'P2'], [$first->json('data.slate_code'), $third->json('data.slate_code'), $second->json('data.slate_code')]);

        // Un número ya ocupado se rechaza: antes todas caían en la Plancha 1 y se fusionaban.
        $register(['slate_code' => 'P1', 'review_page_data' => $this->cargos([['PRESIDENTE', 'Otra Persona', '7770009']])])
            ->assertUnprocessable()->assertJsonValidationErrors('slate_code');

        // El buscador de la captura informa los números ocupados.
        $this->actingAs($secretary)->getJson('/api/secretary/neighborhoods/search?q=Tres Planchas')
            ->assertOk()->assertJsonPath('data.0.active_election.occupied_slate_numbers', [1, 2, 3]);

        // Oficializadas, son tres planchas distintas con un cargo cada una.
        foreach ([$first, $second, $third] as $response) {
            $batch = $response->json('data.capture_batch_uuid');
            $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $batch])->assertOk();
            $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $batch])->assertOk();
        }

        $slates = collect($this->actingAs($secretary)->getJson('/api/secretary/planchas/by-neighborhood')->assertOk()->json('data.items.0.slates'));
        $this->assertSame(['Plancha 1', 'Plancha 2', 'Plancha 3'], $slates->pluck('label')->sort()->values()->all());
        $this->assertSame([1, 1, 1], $slates->map(fn ($slate) => count($slate['representatives']))->all());
    }

    #[Test]
    public function oficializar_exige_indicar_la_plancha(): void
    {
        $this->actingAs($this->makeUser(['candidate_drafts.promote']))
            ->postJson('/api/secretary/planchas/drafts/promote', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capture_batch_uuid');
    }

    #[Test]
    public function una_plancha_oficializada_sale_de_la_bandeja_y_ya_no_se_modifica(): void
    {
        $election = $this->makeElection($this->makeNeighborhood('Barrio Limpio'));
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update', 'candidate_drafts.approve', 'candidate_drafts.promote', 'slates.view', 'candidates.view']);
        $inbox = fn () => $this->actingAs($secretary)->getJson('/api/secretary/planchas/drafts/grouped')->assertOk()->json('data');

        $first = $this->capture($secretary, [
            'election_id' => $election->id,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'Ana Prueba', '5550001']]),
        ])->assertCreated()->json('data.capture_batch_uuid');
        $second = $this->capture($secretary, [
            'election_id' => $election->id,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'Beto Prueba', '5550002']]),
        ])->assertCreated()->json('data.capture_batch_uuid');

        $this->assertCount(2, $inbox()[0]['batches']);

        // Se aprueba y oficializa la primera: sale de la bandeja; la otra conserva su número.
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $first])->assertOk();
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $first])->assertOk();

        $batches = collect($inbox()[0]['batches'])->keyBy('capture_batch_uuid');
        $this->assertFalse($batches->has($first), 'La plancha oficializada ya no está en la bandeja.');
        $this->assertSame(2, $batches[$second]['number'], 'La plancha restante sigue siendo la Plancha 2.');

        // Editar la plancha ya oficial: el servidor no la cambia y lo dice.
        $response = $this->capture($secretary, [
            'capture_batch_uuid' => $first,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'Nombre Cambiado', '5550001']]),
        ])->assertCreated();
        $this->assertSame(['PRESIDENTE'], $response->json('data.locked_official'));
        $this->assertSame('Ana', CandidateDraft::where('capture_batch_uuid', $first)->value('first_name'));

        // Con la segunda también oficial, el barrio sale de la bandeja y está en las planchas oficiales.
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/decision/batch', ['decision' => 'approved', 'capture_batch_uuid' => $second])->assertOk();
        $this->actingAs($secretary)->postJson('/api/secretary/planchas/drafts/promote', ['capture_batch_uuid' => $second])->assertOk();

        $this->assertSame([], $inbox());
        $this->actingAs($secretary)->getJson('/api/secretary/planchas/by-neighborhood')
            ->assertOk()->assertJsonPath('data.items.0.name', 'Barrio Limpio')->assertJsonCount(2, 'data.items.0.slates');
    }

    #[Test]
    public function nombres_y_documentos_se_guardan_en_un_formato_unico(): void
    {
        $this->assertSame('Juan Pérez de la Cruz', PersonData::name('  JUAN   pérez DE LA cruz '));
        $this->assertSame('María José', PersonData::name('maría JOSÉ'));
        $this->assertSame('<DESCONOCIDO> SIN_APELLIDO', PersonData::name('<desconocido> sin_apellido'));
        $this->assertSame('1070622867', PersonData::document(' 1.070.622.867 '));

        // Plancha desde el OCR (mayúsculas, cédula con puntos).
        $election = $this->makeElection($this->makeNeighborhood('Barrio Formato'));
        $secretary = $this->makeUser(['candidate_drafts.view', 'candidate_drafts.create', 'candidate_drafts.update']);
        $this->capture($secretary, [
            'election_id' => $election->id,
            'review_page_data' => $this->cargos([['PRESIDENTE', 'MARÍA GÓMEZ', '1.070.622.867']]),
        ])->assertCreated();

        $draft = CandidateDraft::first();
        $this->assertSame(['María', 'Gómez', '1070622867'], [$draft->first_name, $draft->last_name, $draft->document_number]);

        // Persona desde el asistente: mismo formato, y el documento con puntos no se duplica.
        $admin = $this->makeUser(['users.view', 'persons.view', 'users.create', 'persons.create', 'roles.view', 'users.assign_role']);
        $payload = [
            'document_type_id' => DocumentType::firstOrCreate(['code' => 'CC'], ['name' => 'Cédula de ciudadanía'])->id,
            'document_number' => '1.070.111.222',
            'first_name' => 'pedro', 'last_name' => 'DE LA ROSA',
            'username' => 'pedro', 'email' => 'pedro@example.test',
            'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123',
            'roles' => [Role::create(['name' => 'consulta', 'display_name' => 'Consulta', 'is_active' => true])->id],
        ];
        $this->actingAs($admin)->postJson('/api/admin/users-complete', $payload)->assertCreated();

        $person = Person::where('document_number', '1070111222')->firstOrFail();
        $this->assertSame(['Pedro', 'De la Rosa'], [$person->first_name, $person->last_name]);

        $this->actingAs($admin)->postJson('/api/admin/users-complete', [
            ...$payload, 'document_number' => '1070111222', 'username' => 'otro', 'email' => 'otro@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors('document_number');
    }
}
