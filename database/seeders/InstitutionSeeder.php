<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        Institution::create([
            'name'          => 'Escuela Bilingüe Berta A. López',
            'type'          => 'escuela',
            'ruc'           => '4-76-2305',
            'address'       => 'Una Milla, Almirante, Bocas del Toro',
            'phone'         => '721-7979',
            'email'         => 'escuela.berta@meduca.gob.pa',
            'director_name' => 'Magister Silvana Ng',
        ]);
    }
}
