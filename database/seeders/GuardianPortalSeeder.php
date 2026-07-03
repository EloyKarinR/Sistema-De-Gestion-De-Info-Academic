<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Student;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GuardianPortalSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::first();

        $student = Student::where('last_name', 'Bowie Miller')->first();
        $guardian = $student->guardians()->wherePivot('is_primary', true)->first();

        $user = User::create([
            'name' => $guardian->full_name,
            'email' => 'acudiente@siga.pa',
            'password' => Hash::make('password'),
            'current_team_id' => $team->id,
        ]);
        $user->assignRole('acudiente');
        $team->members()->attach($user->id, ['role' => TeamRole::Member->value]);

        $guardian->update(['user_id' => $user->id]);
    }
}
