<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            InstitutionSeeder::class,
            AcademicYearSeeder::class,
            EducationLevelSeeder::class,
            GradeSeeder::class,
            SubjectSeeder::class,
            HabitSeeder::class,
            UserSeeder::class,
            StudentSeeder::class,
        ]);
    }
}
