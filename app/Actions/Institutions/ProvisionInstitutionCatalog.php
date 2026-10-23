<?php

namespace App\Actions\Institutions;

use App\Models\Institution;
use Illuminate\Support\Facades\DB;

/**
 * Crea el catálogo base que toda escuela necesita para operar: niveles
 * educativos, grados, materias (con su asignación por grado) y hábitos y
 * actitudes, según el programa de MEDUCA.
 *
 * Trabaja siempre sobre la institución que recibe (nunca Institution::first()
 * ni el TenantContext), así que sirve tanto al registrar una escuela nueva
 * desde la app como en seeders y consola. Es idempotente: si algo ya existe
 * (por nombre) no lo duplica, y nunca quita asignaciones materia-grado que la
 * escuela haya agregado por su cuenta.
 */
class ProvisionInstitutionCatalog
{
    /**
     * Se quita el scope de institución en cada consulta: las relaciones ya
     * filtran por $institution, y si el TenantContext apunta a OTRA escuela
     * (p. ej. un admin creando una nueva) el scope dejaría todo vacío y
     * firstOrCreate duplicaría el catálogo.
     */
    private const SCOPE = 'institution';

    /**
     * Materias de Básica General. true = especializada (requiere un docente
     * específico); false = la da el maestro de grado.
     */
    private const SUBJECTS = [
        'Español' => false,
        'Matemática' => false,
        'Ciencias Naturales' => false,
        'Ciencias Sociales' => false,
        'Religión, Moral y Valores' => false,
        'Inglés' => true,
        'Salud y Educación Física' => true,
        'Expresión Artística' => true,
        'Tecnologías' => true,
    ];

    /**
     * Áreas de desarrollo de Preescolar (Pre-Kinder y Kinder): socioafectiva,
     * cognoscitiva/lingüística (dividida en lenguaje y lógico-matemática) y
     * psicomotora. No son las mismas asignaturas que las de Básica General.
     */
    private const PREESCOLAR_SUBJECTS = [
        'Desarrollo Personal y Social' => false,
        'Comunicación y Lenguaje' => false,
        'Pensamiento Lógico-Matemático' => false,
        'Expresión Corporal y Psicomotricidad' => false,
    ];

    private const HABITS = [
        'Responsabilidad',
        'Orden y Aseo',
        'Organización del Trabajo',
        'Autodisciplina y Confianza en sí Mismo',
        'Iniciativa',
        'Cooperación',
        'Respeto a la Propiedad Ajena',
    ];

    public function handle(Institution $institution): void
    {
        DB::transaction(function () use ($institution) {
            $this->levelsAndGrades($institution);
            $this->subjects($institution);
            $this->habits($institution);
        });
    }

    public function levelsAndGrades(Institution $institution): void
    {
        $levels = [
            ['name' => 'Pre-Escolar', 'institution_type' => 'escuela', 'grade_from' => 0, 'grade_to' => 0, 'order' => 1],
            ['name' => 'Básica General', 'institution_type' => 'escuela', 'grade_from' => 1, 'grade_to' => 6, 'order' => 2],
            ['name' => 'Pre-Media', 'institution_type' => 'colegio', 'grade_from' => 7, 'grade_to' => 9, 'order' => 3],
            ['name' => 'Media', 'institution_type' => 'colegio', 'grade_from' => 10, 'grade_to' => 12, 'order' => 4],
        ];

        $grades = [
            'Pre-Escolar' => [
                ['name' => 'Pre-Kinder', 'number' => null, 'min_age' => 4, 'max_age' => 5, 'order' => 1],
                ['name' => 'Kinder', 'number' => null, 'min_age' => 5, 'max_age' => 6, 'order' => 2],
            ],
            'Básica General' => array_map(
                fn ($i) => ['name' => $i.'°', 'number' => $i, 'min_age' => 5 + $i, 'max_age' => 6 + $i, 'order' => $i],
                range(1, 6)
            ),
            'Pre-Media' => array_map(
                fn ($i) => ['name' => $i.'°', 'number' => $i, 'min_age' => null, 'max_age' => null, 'order' => $i - 6],
                range(7, 9)
            ),
            'Media' => array_map(
                fn ($i) => ['name' => $i.'°', 'number' => $i, 'min_age' => null, 'max_age' => null, 'order' => $i - 9],
                range(10, 12)
            ),
        ];

        foreach ($levels as $level) {
            $educationLevel = $institution->educationLevels()->withoutGlobalScope(self::SCOPE)->firstOrCreate(['name' => $level['name']], $level);

            foreach ($grades[$level['name']] as $grade) {
                $educationLevel->grades()->withoutGlobalScope(self::SCOPE)->firstOrCreate(['name' => $grade['name']], $grade);
            }
        }
    }

    /**
     * Requiere que los grados ya existan (levelsAndGrades) para asignarles
     * sus materias.
     */
    public function subjects(Institution $institution): void
    {
        foreach ([...self::SUBJECTS, ...self::PREESCOLAR_SUBJECTS] as $name => $isSpecialized) {
            $institution->subjects()->withoutGlobalScope(self::SCOPE)->firstOrCreate(['name' => $name], ['is_specialized' => $isSpecialized]);
        }

        $allSubjects = $institution->subjects()->withoutGlobalScope(self::SCOPE)->get();
        $levels = $institution->educationLevels()
            ->withoutGlobalScope(self::SCOPE)
            ->with(['grades' => fn ($q) => $q->withoutGlobalScope(self::SCOPE)])
            ->get()->keyBy('name');

        // Básica General (1°-6°): las 9 materias académicas.
        $basicaSubjectIds = $allSubjects->whereIn('name', array_keys(self::SUBJECTS))->pluck('id');

        foreach ($levels->get('Básica General')?->grades ?? [] as $grade) {
            $grade->subjects()->syncWithoutDetaching($basicaSubjectIds);
        }

        // Preescolar: sus propias áreas + Expresión Artística. Ni Inglés ni
        // Educación Física de Básica General aplican a esta edad — el área
        // psicomotora ya cubre lo motriz — y Tecnologías tampoco.
        $preescolarSubjectIds = $allSubjects
            ->whereIn('name', [...array_keys(self::PREESCOLAR_SUBJECTS), 'Expresión Artística'])
            ->pluck('id');

        foreach ($levels->get('Pre-Escolar')?->grades ?? [] as $grade) {
            $grade->subjects()->syncWithoutDetaching($preescolarSubjectIds);
        }
    }

    public function habits(Institution $institution): void
    {
        foreach (self::HABITS as $index => $name) {
            $institution->habits()->withoutGlobalScope(self::SCOPE)->firstOrCreate(['name' => $name], ['order' => $index + 1]);
        }
    }
}
