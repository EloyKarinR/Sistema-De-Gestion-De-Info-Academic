# SIGA — Sistema de Gestión de Información Académica

## Sobre el proyecto
Proyecto universitario con visión comercial: venderlo a colegios/escuelas de primaria y secundaria después de la U. Ya está desplegado en producción (Lightsail, Docker + Caddy).

## Stack
- Laravel 13 + PHP 8.5.5
- Livewire 4 (componentes de página single-file en `resources/views/pages/**`) + Flux 2
- PostgreSQL
- Fortify (auth) + spatie/laravel-permission (roles y permisos)
- Teams (starter kit) — cada Team es una escuela (ver Multi-tenant)
- laravel/boost v2.4.3 (dev) — instalado y configurado para Claude Code
- laravel/mcp v0.6.5
- laravel/roster v0.5.1

## Estrategia
MVP bien definido para la U, arquitectura escalable para comercializar después. Priorizar calidad y completitud sobre cantidad de módulos. Código limpio y bien normalizado desde el inicio.

## Estado actual — módulos terminados
- **Institución:** datos del colegio
- **Académico:** niveles educativos, grados (con rango de edad), aulas/secciones, turnos, años escolares con 3 trimestres, horarios, promoción de año
- **Personas:** estudiantes (con foto), acudientes, docentes (con turno y asignación de materias)
- **Matrículas (módulo estrella):** asistente de 3 pasos, checklist de documentos, recibo/constancia; estados activo/retirado/trasladado
- **Notas:** escala 1.0–5.0 por materia y trimestre (mínimo para aprobar: 3.0 en secundaria)
- **Asistencia:** solo se registran ausencias y tardanzas (presente = sin fila)
- **Hábitos y actitudes:** evaluación S / R / X por trimestre
- **Portal de padres:** rol acudiente
- **Reportes PDF:** boletín, constancia de matrícula, listado por aula
- **Roles:** admin, secretaria, docente, acudiente
- **Sesión única:** un usuario solo puede tener una sesión activa

## Multi-tenant (rama `feature/multi-tenant`)
- Cada `Team` tiene una `Institution` (`institutions.team_id`). La institución actual sale de `Auth::user()->currentInstitution()` a través de `App\Support\TenantContext`.
- Modelos con `institution_id` propio usan el trait `BelongsToInstitution` (scope global + autollenado al crear): AcademicYear, EducationLevel, Guardian, Habit, Student, Subject, Teacher.
- Modelos sin `institution_id` pero con un padre que sí lo tiene usan `ScopedThroughParent` (`$tenantParent`): Classroom y Enrollment → `academicYear`, Grade → `educationLevel`.
- El resto (GradeScore, Attendance, HabitScore, Period, SubjectAssignment, ClassSchedule…) NO tiene scope: siempre consultarlos a partir de un padre ya filtrado.
- Escuela nueva: al registrar la institución (`pages::institution.edit`) se corre `App\Actions\Institutions\ProvisionInstitutionCatalog`, que crea niveles, grados, materias y hábitos (es idempotente). Es la única fuente del catálogo base: InstitutionSeeder también la usa.
- Seeders: nunca `Institution::first()` / `Team::first()`. Usar `Database\Seeders\Concerns\InteractsWithTenant`: `currentInstitution()` en la cadena de demo y `forEachInstitution()` en backfills que se corren sueltos.
- Reglas al escribir código:
  - Nunca confiar en IDs que vienen de propiedades públicas de Livewire. Cargarlos con un modelo filtrado, o comprobar que estén dentro de una colección que el componente cargó él mismo.
  - `Rule::exists(...)` se salta los scopes de Eloquent: siempre anclarlo con `->where('academic_year_id', …)` o `->where('institution_id', …)`.
  - Sin usuario autenticado (jobs, comandos, seeders) el scope no filtra nada. Llamar a `TenantContext::set()` antes de consultar.
  - Un recurso de otra escuela debe responder 404, no 403.
- Roles por escuela: Spatie con `teams = true`. Los roles (admin, secretaria, docente, acudiente) son globales, pero su ASIGNACIÓN es por equipo (`model_has_roles.team_id`). `App\Support\PermissionsTeamResolver` evalúa permisos en el `current_team_id` del usuario autenticado (en vivo, sin middleware).
  - Asignar roles SIEMPRE con `$user->assignRoleInTeam('docente', $team)`, nunca `assignRole()`.
  - `TeamRole` (owner/admin/member) es otra cosa: solo controla la administración del equipo (invitar, renombrar).
- Tests de aislamiento: `tests/Feature/CrossTenantIsolationTest.php`. Al agregar una pantalla que escriba datos, agregar ahí el caso de ataque.

## Dominio — Proceso de matrícula
- Antes del sistema era presencial y tedioso: los padres hacían fila desde temprano
- Documentos requeridos: copia de cédula del acudiente, copia de cédula del alumno, teléfono, copia del boletín, dirección
- Al final se entrega un recibo con todos los datos y el grado asignado (número `MAT-<año>-<id>`)
- Un estudiante solo puede tener UNA matrícula activa por año escolar (un aula)
- Los periodos académicos son trimestres (3 por año)

## Convenciones
- Tests: PHPUnit con `RefreshDatabase` + `Tests\Concerns\SigaTestHelpers` (makeTeam, makeStaffUser, makeInstitution, makeActiveYear, makeGrade, makeClassroom)
- Formato: `vendor/bin/pint --dirty` antes de hacer commit
- Commits en español con prefijo convencional (`feat:`, `fix:`, `test:`, `docs:`, `chore:`)
- Seeders: nada de Faker (se pierde con `--no-dev` en producción) y deben ser idempotentes

## Pendientes / para escalar
- Invitaciones: aceptar una invitación da membresía (`TeamRole`) pero ningún rol de escuela → el invitado no ve nada. Falta elegir el rol (admin/secretaria) al invitar; hoy no hay UI para crear secretarias/admins (solo seeders)
- Cédula única: la BD ya es única por institución, pero las reglas `unique:students|guardians|teachers,cedula` siguen siendo globales → no se puede registrar en la escuela B a alguien que ya existe en la A
- Alta de escuelas: nadie puede crear equipos desde la app (`TeamPolicy::create()` = false), así que no hay forma de dar de alta un colegio nuevo salvo seeders. Propuesta: comando `siga:crear-escuela` (equipo + institución + catálogo + admin con su rol)
- Pagos reales (existe tabla `payments`, sin flujo completo)
- Entrevistas con colegios reales: pospuestas hasta después de presentar en la U

## Sobre el usuario
- Estudiante universitario, trabaja solo en el proyecto
- Conoce Laravel (starter kit ya configurado con Livewire + Flux)
- Prefiere respuestas directas y decisiones proactivas
