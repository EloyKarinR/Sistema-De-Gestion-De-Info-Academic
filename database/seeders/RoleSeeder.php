<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Institución
            'institution.view', 'institution.edit',

            // Académico
            'academic.view', 'academic.manage',

            // Matrículas
            'enrollment.view', 'enrollment.create', 'enrollment.edit', 'enrollment.delete',

            // Estudiantes y acudientes
            'student.view', 'student.create', 'student.edit',
            'guardian.view', 'guardian.create', 'guardian.edit',

            // Pagos
            'payment.view', 'payment.create',

            // Docentes
            'teacher.view', 'teacher.manage',

            // Notas
            'scores.view', 'scores.enter',

            // Asistencia
            'attendance.view', 'attendance.enter',

            // Reportes
            'reports.view', 'reports.print',

            // Usuarios
            'users.manage',

            // Portal del acudiente
            'portal.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Admin — acceso total a la administración del sistema.
        // portal.view queda excluido: es exclusivo del rol acudiente
        // (ver "Mi Portal" de un estudiante no es una función administrativa).
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::where('name', '!=', 'portal.view')->get());

        // Secretaria
        $secretaria = Role::firstOrCreate(['name' => 'secretaria']);
        $secretaria->givePermissionTo([
            'institution.view',
            'academic.view',
            'enrollment.view', 'enrollment.create', 'enrollment.edit',
            'student.view', 'student.create', 'student.edit',
            'guardian.view', 'guardian.create', 'guardian.edit',
            'payment.view', 'payment.create',
            'teacher.view',
            'scores.view',
            'attendance.view',
            'reports.view', 'reports.print',
        ]);

        // Docente
        $docente = Role::firstOrCreate(['name' => 'docente']);
        $docente->givePermissionTo([
            'student.view',
            'scores.view', 'scores.enter',
            'attendance.view', 'attendance.enter',
            'reports.view',
        ]);

        // Acudiente — solo ve el portal de su(s) hijo(s)
        $acudiente = Role::firstOrCreate(['name' => 'acudiente']);
        $acudiente->givePermissionTo([
            'portal.view',
        ]);
    }
}
