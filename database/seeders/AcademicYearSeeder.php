<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();

        $year = AcademicYear::create([
            'institution_id' => $institution->id,
            'year'           => 2026,
            'start_date'     => '2026-03-03',
            'end_date'       => '2026-12-12',
            'is_active'      => true,
        ]);

        $year->periods()->createMany([
            ['number' => 1, 'name' => 'I Trimestre',   'start_date' => '2026-03-03', 'end_date' => '2026-05-30'],
            ['number' => 2, 'name' => 'II Trimestre',  'start_date' => '2026-06-01', 'end_date' => '2026-09-11'],
            ['number' => 3, 'name' => 'III Trimestre', 'start_date' => '2026-09-14', 'end_date' => '2026-12-12'],
        ]);
    }
}
