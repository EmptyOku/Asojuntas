<?php

namespace Tests\Support;

use App\Models\City;
use App\Models\Commune;
use App\Models\DocumentType;
use App\Models\Election;
use App\Models\Neighborhood;
use App\Models\Permission;
use App\Models\Person;
use App\Models\PollingTable;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Construye la cadena geografía → elección → mesa que necesitan las pruebas,
 * sin depender de los seeders completos.
 */
trait ElectoralScenario
{
    private int $scenarioSequence = 0;

    protected function makeNeighborhood(string $name): Neighborhood
    {
        $suffix = ++$this->scenarioSequence;

        $state = State::firstOrCreate(['code' => 'ANT'], ['name' => 'Antioquia']);
        $city = City::firstOrCreate(
            ['state_id' => $state->id, 'code' => 'MED'],
            ['name' => 'Medellín']
        );
        $commune = Commune::firstOrCreate(
            ['city_id' => $city->id, 'code' => 'C14'],
            ['name' => 'Comuna 14']
        );

        return Neighborhood::create([
            'commune_id' => $commune->id,
            'name' => $name,
            'code' => 'B'.$suffix,
        ]);
    }

    protected function makeElection(Neighborhood $neighborhood): Election
    {
        $suffix = ++$this->scenarioSequence;

        return Election::create([
            'neighborhood_id' => $neighborhood->id,
            'name' => 'Elección '.$neighborhood->name,
            'code' => 'EL-'.$suffix,
            'election_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    protected function makePollingTable(Election $election): PollingTable
    {
        $suffix = ++$this->scenarioSequence;

        return PollingTable::create([
            'election_id' => $election->id,
            'name' => 'Mesa '.$suffix,
            'code' => 'M-'.$suffix,
            'is_active' => true,
        ]);
    }

    /**
     * Crea un usuario con los permisos indicados y, opcionalmente, barrio asignado.
     *
     * @param  list<string>  $permissions
     */
    protected function makeUser(array $permissions = [], ?Neighborhood $neighborhood = null): User
    {
        $suffix = ++$this->scenarioSequence;

        $documentType = DocumentType::firstOrCreate(
            ['code' => 'CC'],
            ['name' => 'Cédula de ciudadanía']
        );

        $person = Person::create([
            'document_type_id' => $documentType->id,
            'document_number' => '100'.$suffix,
            'first_name' => 'Persona',
            'last_name' => 'Prueba'.$suffix,
            'neighborhood_id' => $neighborhood?->id,
        ]);

        $user = User::create([
            'person_id' => $person->id,
            'username' => 'user'.$suffix,
            'email' => 'user'.$suffix.'@test.local',
            'password' => Hash::make('secret-password'),
            'is_active' => true,
        ]);

        if ($permissions !== []) {
            $role = Role::create([
                'name' => 'role_'.$suffix,
                'display_name' => 'Rol '.$suffix,
                'is_active' => true,
            ]);

            $permissionIds = collect($permissions)
                ->map(fn (string $name) => Permission::firstOrCreate(
                    ['name' => $name],
                    ['display_name' => $name, 'is_active' => true]
                )->id);

            $role->permissions()->sync($permissionIds->all());
            $user->roles()->attach($role->id, ['assigned_at' => now()]);
        }

        return $user->fresh(['roles.permissions', 'person']);
    }
}
