<?php

namespace Tests\Feature;

use App\Models\Student;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class StudentPhotoTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_se_puede_subir_la_foto_del_estudiante_al_crearlo_en_la_matricula(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $this->makeClassroom($grade, $year);

        Livewire::actingAs($secretaria)
            ->test('pages::enrollments.create')
            ->set('cedulaSearch', '8-999-1234')
            ->call('searchStudent')
            ->assertSet('studentMode', 'create')
            ->set('firstName', 'Ana')
            ->set('lastName', 'Pérez')
            ->set('birthDate', '2018-01-01')
            ->set('sex', 'F')
            ->set('address', 'Calle 2')
            ->set('photo', UploadedFile::fake()->image('ana.jpg'))
            ->call('createStudent')
            ->assertSet('step', 2)
            ->assertHasNoErrors();

        $student = Student::where('cedula', '8-999-1234')->firstOrFail();

        $this->assertNotNull($student->photo);
        Storage::disk('public')->assertExists($student->photo);
    }

    public function test_secretaria_puede_actualizar_la_foto_desde_la_ficha_del_estudiante(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $student = Student::create([
            'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2',
        ]);

        Livewire::actingAs($secretaria)
            ->test('pages::students.show', ['student' => $student])
            ->set('photo', UploadedFile::fake()->image('ana-nueva.jpg'))
            ->call('updatePhoto')
            ->assertHasNoErrors();

        $student->refresh();
        $this->assertNotNull($student->photo);
        Storage::disk('public')->assertExists($student->photo);
    }

    public function test_docente_no_puede_actualizar_la_foto_del_estudiante(): void
    {
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $docente = $this->makeStaffUser('docente', $team);

        $student = Student::create([
            'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2',
        ]);

        Livewire::actingAs($docente)
            ->test('pages::students.show', ['student' => $student])
            ->set('photo', UploadedFile::fake()->image('ana.jpg'))
            ->call('updatePhoto')
            ->assertForbidden();
    }
}
