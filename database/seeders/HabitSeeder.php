<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class HabitSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();

        $habits = [
            'Responsabilidad',
            'Orden y Aseo',
            'Organización del Trabajo',
            'Autodisciplina y Confianza en sí Mismo',
            'Iniciativa',
            'Cooperación',
            'Respeto a la Propiedad Ajena',
        ];

        foreach ($habits as $index => $name) {
            $institution->habits()->create([
                'name'  => $name,
                'order' => $index + 1,
            ]);
        }
    }
}
