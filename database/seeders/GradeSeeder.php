<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $preEscolar = EducationLevel::where('name', 'Pre-Escolar')->first();
        $basicaGeneral = EducationLevel::where('name', 'Básica General')->first();
        $preMedia = EducationLevel::where('name', 'Pre-Media')->first();
        $media = EducationLevel::where('name', 'Media')->first();

        $preEscolar->grades()->createMany([
            ['name' => 'Pre-Kinder', 'number' => null, 'min_age' => 4, 'max_age' => 5, 'order' => 1],
            ['name' => 'Kinder',     'number' => null, 'min_age' => 5, 'max_age' => 6, 'order' => 2],
        ]);

        foreach (range(1, 6) as $i) {
            $basicaGeneral->grades()->create([
                'name' => $i.'°',
                'number' => $i,
                'min_age' => 5 + $i,
                'max_age' => 6 + $i,
                'order' => $i,
            ]);
        }

        foreach (range(7, 9) as $i) {
            $preMedia->grades()->create([
                'name' => $i.'°',
                'number' => $i,
                'min_age' => null,
                'order' => $i - 6,
            ]);
        }

        foreach (range(10, 12) as $i) {
            $media->grades()->create([
                'name' => $i.'°',
                'number' => $i,
                'min_age' => null,
                'order' => $i - 9,
            ]);
        }
    }
}
