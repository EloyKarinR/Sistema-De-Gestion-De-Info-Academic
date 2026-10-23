<?php

namespace Database\Seeders;

use App\Actions\Institutions\ProvisionInstitutionCatalog;
use App\Models\Institution;
use App\Models\Team;
use App\Support\TenantContext;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::create(['name' => 'Escuela SIGA', 'is_personal' => false]);

        // team_id no es fillable a propósito (nunca se setea por asignación
        // masiva) — se asigna aparte antes de guardar.
        $institution = new Institution([
            'name' => 'Escuela Bilingüe Berta A. López',
            'type' => 'escuela',
            'ruc' => '4-76-2305',
            'address' => 'Una Milla, Almirante, Bocas del Toro',
            'phone' => '721-7979',
            'email' => 'escuela.berta@meduca.gob.pa',
            'director_name' => 'Magister Silvana Ng',
        ]);
        $institution->team_id = $team->id;
        $institution->save();

        // Deja el contexto fijado para el resto de la cadena de seeders — así
        // AcademicYear/Habit/Subject/etc. y los Student/Guardian/Teacher que
        // se creen después quedan scopeados/con institution_id sin tocarlos
        // uno por uno.
        app(TenantContext::class)->set($institution);

        // Mismo camino que una escuela registrada desde la app.
        app(ProvisionInstitutionCatalog::class)->handle($institution);
    }
}
