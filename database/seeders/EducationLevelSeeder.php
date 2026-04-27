<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();

        $institution->educationLevels()->createMany([
            ['name' => 'Pre-Escolar',      'institution_type' => 'escuela', 'grade_from' => 0, 'grade_to' => 0, 'order' => 1],
            ['name' => 'Básica General',   'institution_type' => 'escuela', 'grade_from' => 1, 'grade_to' => 6, 'order' => 2],
            ['name' => 'Pre-Media',        'institution_type' => 'colegio', 'grade_from' => 7, 'grade_to' => 9, 'order' => 3],
            ['name' => 'Media',            'institution_type' => 'colegio', 'grade_from' => 10, 'grade_to' => 12, 'order' => 4],
        ]);
    }
}
