<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Teacher;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::create(['name' => 'Escuela SIGA', 'is_personal' => false]);

        $admin = User::create([
            'name' => 'Administrador SIGA',
            'email' => 'admin@siga.pa',
            'password' => Hash::make('password'),
            'current_team_id' => $team->id,
        ]);
        $admin->assignRole('admin');
        $team->members()->attach($admin->id, ['role' => TeamRole::Owner->value]);

        $secretaria = User::create([
            'name' => 'Alexandra Smith',
            'email' => 'secretaria@siga.pa',
            'password' => Hash::make('password'),
            'current_team_id' => $team->id,
        ]);
        $secretaria->assignRole('secretaria');
        $team->members()->attach($secretaria->id, ['role' => TeamRole::Member->value]);

        $docenteUser = User::create([
            'name' => 'Barbara Wilson',
            'email' => 'docente@siga.pa',
            'password' => Hash::make('password'),
            'current_team_id' => $team->id,
        ]);
        $docenteUser->assignRole('docente');
        $team->members()->attach($docenteUser->id, ['role' => TeamRole::Member->value]);

        Teacher::create([
            'user_id' => $docenteUser->id,
            'cedula' => '8-123-4567',
            'first_name' => 'Barbara',
            'last_name' => 'Wilson',
            'phone' => '6000-0001',
            'specialization' => 'Educación Primaria',
        ]);

        // Docente itinerante: da la misma materia (Inglés) en varias aulas y niveles.
        $inglesUser = User::create([
            'name' => 'Karla Sánchez',
            'email' => 'karla.sanchez@siga.pa',
            'password' => Hash::make('password'),
            'current_team_id' => $team->id,
        ]);
        $inglesUser->assignRole('docente');
        $team->members()->attach($inglesUser->id, ['role' => TeamRole::Member->value]);

        Teacher::create([
            'user_id' => $inglesUser->id,
            'cedula' => '8-234-5678',
            'first_name' => 'Karla',
            'last_name' => 'Sánchez',
            'phone' => '6000-0002',
            'specialization' => 'Inglés',
        ]);
    }
}
