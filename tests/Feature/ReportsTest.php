<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_admin_puede_generar_los_tres_pdfs(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $boletin = $this->actingAs($admin)->get(route('reports.boletin', $student));
        $boletin->assertOk();
        $boletin->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $boletin->getContent());

        $constancia = $this->actingAs($admin)->get(route('reports.constancia', $enrollment));
        $constancia->assertOk();
        $constancia->assertHeader('content-type', 'application/pdf');

        $listado = $this->actingAs($admin)->get(route('reports.listado', $classroom));
        $listado->assertOk();
        $listado->assertHeader('content-type', 'application/pdf');
    }

    public function test_boletin_muestra_el_nombre_del_maestro_de_grado(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $espanol = $institution->subjects()->create(['name' => 'Español', 'is_specialized' => false]);
        $grade->subjects()->attach($espanol->id);

        $teacher = Teacher::create([
            'user_id' => $this->makeStaffUser('docente', $team)->id, 'cedula' => '8-999-9999',
            'first_name' => 'Rosa', 'last_name' => 'Jiménez', 'shift' => 'matutino',
        ]);
        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'subject_id' => $espanol->id, 'academic_year_id' => $year->id,
        ]);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $boletin = $this->actingAs($admin)->get(route('reports.boletin', $student));
        $boletin->assertOk();
        $this->assertStringContainsString('Rosa Jim', $this->extractPdfText($boletin->getContent()));
    }

    /**
     * DomPDF comprime los content streams (FlateDecode) — hay que
     * descomprimirlos para poder buscar texto plano en las pruebas.
     */
    private function extractPdfText(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches);

        $text = '';
        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress(rtrim($stream, "\r\n"));
            if ($decoded !== false) {
                $text .= $decoded;
            }
        }

        return $text;
    }

    public function test_docente_no_puede_descargar_reportes(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $docente = $this->makeStaffUser('docente', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);

        $this->actingAs($docente)->get(route('reports.boletin', $student))->assertForbidden();
        $this->actingAs($docente)->get(route('reports.listado', $classroom))->assertForbidden();
    }

    public function test_buscar_estudiante_y_abrir_vista_previa_actualiza_la_url_del_iframe(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::reports.index')
            ->set('studentSearch', 'Ana')
            ->assertSee('Ana Pérez')
            ->assertSee('Boletín')
            ->assertSee('Constancia');

        $component->assertSet('previewUrl', '');

        $component->call('preview', route('reports.boletin', $student), 'Boletín de Ana')
            ->assertSet('previewUrl', route('reports.boletin', $student))
            ->assertSet('previewTitle', 'Boletín de Ana')
            ->assertSee('<iframe', false);

        $component->call('preview', route('reports.constancia', $enrollment), 'Constancia de Ana')
            ->assertSet('previewUrl', route('reports.constancia', $enrollment));
    }
}
