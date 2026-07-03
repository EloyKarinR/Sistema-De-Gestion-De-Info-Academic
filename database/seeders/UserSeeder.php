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
            'shift' => 'matutino',
        ]);

        // Docentes especialistas itinerantes: dan la misma materia en varias aulas
        // y niveles, pero cada uno(a) solo dentro de un turno fijo (uno por turno).
        $this->createSpecialistTeacher($team, 'Karla', 'Sánchez', 'karla.sanchez@siga.pa', '8-234-5678', '6000-0002', 'Inglés', 'matutino');
        $this->createSpecialistTeacher($team, 'Diana', 'Fuentes', 'diana.fuentes@siga.pa', '8-234-5679', '6000-0003', 'Inglés', 'vespertino');
        $this->createSpecialistTeacher($team, 'Rogelio', 'Batista', 'rogelio.batista@siga.pa', '8-234-5680', '6000-0004', 'Educación Física', 'matutino');
        $this->createSpecialistTeacher($team, 'Yolanda', 'Prado', 'yolanda.prado@siga.pa', '8-234-5681', '6000-0005', 'Educación Física', 'vespertino');
        $this->createSpecialistTeacher($team, 'Marisol', 'Chen', 'marisol.chen@siga.pa', '8-234-5682', '6000-0006', 'Expresión Artística', 'matutino');
        $this->createSpecialistTeacher($team, 'Andrés', 'Quirós', 'andres.quiros@siga.pa', '8-234-5683', '6000-0007', 'Expresión Artística', 'vespertino');
        $this->createSpecialistTeacher($team, 'Iván', 'Cedeño', 'ivan.cedeno@siga.pa', '8-234-5684', '6000-0008', 'Tecnologías', 'matutino');
        $this->createSpecialistTeacher($team, 'Lucía', 'Ábrego', 'lucia.abrego@siga.pa', '8-234-5685', '6000-0009', 'Tecnologías', 'vespertino');
    }

    private function createSpecialistTeacher(Team $team, string $firstName, string $lastName, string $email, string $cedula, string $phone, string $specialization, string $shift): Teacher
    {
        $user = User::create([
            'name' => "{$firstName} {$lastName}",
            'email' => $email,
            'password' => Hash::make('password'),
            'current_team_id' => $team->id,
        ]);
        $user->assignRole('docente');
        $team->members()->attach($user->id, ['role' => TeamRole::Member->value]);

        return Teacher::create([
            'user_id' => $user->id,
            'cedula' => $cedula,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'specialization' => $specialization,
            'shift' => $shift,
        ]);
    }
}
