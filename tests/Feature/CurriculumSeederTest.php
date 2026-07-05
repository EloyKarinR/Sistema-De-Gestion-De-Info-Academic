<?php

namespace Tests\Feature;

use App\Models\Grade;
use Database\Seeders\EducationLevelSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\InstitutionSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_preescolar_no_recibe_ingles_pero_basica_general_si(): void
    {
        $this->seed(InstitutionSeeder::class);
        $this->seed(EducationLevelSeeder::class);
        $this->seed(GradeSeeder::class);
        $this->seed(SubjectSeeder::class);

        $preKinder = Grade::where('name', 'Pre-Kinder')->firstOrFail();
        $kinder = Grade::where('name', 'Kinder')->firstOrFail();
        $primero = Grade::where('name', '1°')->firstOrFail();

        $this->assertFalse($preKinder->subjects->contains('name', 'Inglés'));
        $this->assertFalse($kinder->subjects->contains('name', 'Inglés'));
        $this->assertTrue($primero->subjects->contains('name', 'Inglés'));

        // Preescolar sí conserva Expresión Artística.
        $this->assertTrue($preKinder->subjects->contains('name', 'Expresión Artística'));
    }
}
