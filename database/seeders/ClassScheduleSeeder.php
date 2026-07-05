<?php

namespace Database\Seeders;

use App\Actions\Academic\GenerateClassSchedule;
use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first();

        DB::transaction(function () use ($year) {
            $generator = new GenerateClassSchedule;

            foreach (Classroom::where('academic_year_id', $year->id)->get() as $classroom) {
                $generator->handle($classroom);
            }
        });
    }
}
