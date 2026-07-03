<?php

namespace Tests\Concerns;

use App\Enums\TeamRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\Team;
use App\Models\User;

trait SigaTestHelpers
{
    protected function makeTeam(string $name = 'Escuela Test'): Team
    {
        return Team::firstOrCreate(['name' => $name], ['is_personal' => false]);
    }

    /**
     * Creates a user with the given Spatie role, attached to the team.
     *
     * Uses User::factory()->create() directly rather than passing
     * current_team_id in the factory state, because UserFactory::configure()
     * always creates its own personal team and switches to it afterward —
     * so the team membership/current_team_id must be set after creation.
     */
    protected function makeStaffUser(string $role, Team $team): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $team->members()->attach($user->id, ['role' => TeamRole::Member->value]);
        $user->update(['current_team_id' => $team->id]);

        return $user->fresh();
    }

    protected function makeInstitution(string $name = 'Escuela SIGA'): Institution
    {
        return Institution::create(['name' => $name, 'type' => 'escuela', 'address' => 'Calle 1']);
    }

    protected function makeActiveYear(Institution $institution, int $year = 2026): AcademicYear
    {
        $academicYear = AcademicYear::create([
            'institution_id' => $institution->id,
            'year' => $year,
            'start_date' => "{$year}-02-01",
            'end_date' => "{$year}-11-30",
            'is_active' => true,
        ]);

        $academicYear->periods()->createMany([
            ['number' => 1, 'name' => 'I Trimestre', 'start_date' => "{$year}-02-01", 'end_date' => "{$year}-05-01"],
            ['number' => 2, 'name' => 'II Trimestre', 'start_date' => "{$year}-05-02", 'end_date' => "{$year}-08-01"],
            ['number' => 3, 'name' => 'III Trimestre', 'start_date' => "{$year}-08-02", 'end_date' => "{$year}-11-30"],
        ]);

        return $academicYear;
    }

    protected function makeGrade(Institution $institution, string $name = '3°', int $number = 3): Grade
    {
        $level = EducationLevel::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => 'Básica General'],
            ['institution_type' => 'escuela', 'grade_from' => 1, 'grade_to' => 6, 'order' => 1]
        );

        return Grade::create(['education_level_id' => $level->id, 'name' => $name, 'number' => $number, 'order' => $number]);
    }

    protected function makeClassroom(Grade $grade, AcademicYear $year, string $section = 'A'): Classroom
    {
        return Classroom::create([
            'grade_id' => $grade->id,
            'academic_year_id' => $year->id,
            'section' => $section,
            'shift' => 'matutino',
            'capacity' => 30,
        ]);
    }
}
