<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    /**
     * Matriz de acceso: qué código HTTP debe devolver cada ruta según el rol.
     *
     * Roles cubiertos: admin, secretaria, docente, acudiente.
     */
    public function test_cada_rol_solo_accede_a_las_rutas_que_le_corresponden(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $users = [
            'admin' => $this->makeStaffUser('admin', $team),
            'secretaria' => $this->makeStaffUser('secretaria', $team),
            'docente' => $this->makeStaffUser('docente', $team),
            'acudiente' => $this->makeStaffUser('acudiente', $team),
        ];

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id,
            'classroom_id' => $classroom->id,
            'academic_year_id' => $year->id,
            'registered_by' => $users['admin']->id,
            'enrollment_date' => '2026-02-01',
            'status' => 'activo',
            'enrollment_type' => 'nuevo_ingreso',
        ]);

        $routes = [
            'institution.edit' => [],
            'academic.index' => [],
            'students.index' => [],
            'students.show' => [$student],
            'teachers.index' => [],
            'guardians.index' => [],
            'scores.index' => [],
            'portal.index' => [],
            'enrollments.index' => [],
            'enrollments.create' => [],
            'reports.index' => [],
            'reports.boletin' => [$student],
            'reports.constancia' => [$enrollment],
            'reports.listado' => [$classroom],
        ];

        // 200 = puede entrar, 403 = bloqueado
        $expected = [
            'institution.edit' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'academic.index' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'students.index' => ['admin' => 200, 'secretaria' => 200, 'docente' => 200, 'acudiente' => 403],
            'students.show' => ['admin' => 200, 'secretaria' => 200, 'docente' => 200, 'acudiente' => 403],
            'teachers.index' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'guardians.index' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'scores.index' => ['admin' => 200, 'secretaria' => 200, 'docente' => 200, 'acudiente' => 403],
            'portal.index' => ['admin' => 403, 'secretaria' => 403, 'docente' => 403, 'acudiente' => 200],
            'enrollments.index' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'enrollments.create' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'reports.index' => ['admin' => 200, 'secretaria' => 200, 'docente' => 200, 'acudiente' => 403],
            'reports.boletin' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'reports.constancia' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
            'reports.listado' => ['admin' => 200, 'secretaria' => 200, 'docente' => 403, 'acudiente' => 403],
        ];

        foreach ($expected as $routeName => $perRole) {
            $url = route($routeName, $routes[$routeName]);

            foreach ($perRole as $role => $status) {
                $response = $this->actingAs($users[$role])->get($url);

                $this->assertSame(
                    $status,
                    $response->getStatusCode(),
                    "Se esperaba HTTP {$status} para el rol '{$role}' en la ruta '{$routeName}', pero se obtuvo {$response->getStatusCode()}."
                );
            }
        }
    }
}
