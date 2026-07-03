<?php

use App\Enums\Shift;
use App\Enums\TeamRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Docentes')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'turno')]
    public string $shiftFilter = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $cedula = '';

    public string $email = '';

    public string $password = '';

    public string $phone = '';

    public string $specialization = '';

    public string $shift = 'matutino';

    public ?int $assignTeacherId = null;

    public string $assignMode = 'homeroom';

    /** @var array<int, string> */
    public array $assignClassroomIds = [];

    /** @var array<int, string> */
    public array $assignSubjectIds = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShiftFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function teachers()
    {
        return Teacher::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhere('cedula', 'LIKE', '%'.$this->search.'%');
                });
            })
            ->when($this->shiftFilter, fn ($q) => $q->where('shift', $this->shiftFilter))
            ->with('user')
            ->orderBy('last_name')
            ->paginate(15);
    }

    public function createTeacher(): void
    {
        $this->authorize('teacher.manage');

        $this->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'cedula' => 'required|string|max:20|unique:teachers,cedula',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'specialization' => 'nullable|string|max:255',
            'shift' => 'required|in:matutino,vespertino',
        ]);

        $team = Auth::user()->currentTeam;

        DB::transaction(function () use ($team) {
            $user = User::create([
                'name' => "{$this->firstName} {$this->lastName}",
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'current_team_id' => $team->id,
            ]);
            $user->assignRole('docente');
            $team->members()->attach($user->id, ['role' => TeamRole::Member->value]);

            Teacher::create([
                'user_id' => $user->id,
                'cedula' => $this->cedula,
                'first_name' => $this->firstName,
                'last_name' => $this->lastName,
                'phone' => $this->phone ?: null,
                'specialization' => $this->specialization ?: null,
                'shift' => $this->shift,
            ]);
        });

        $this->reset(['firstName', 'lastName', 'cedula', 'email', 'password', 'phone', 'specialization']);
        $this->shift = 'matutino';

        Flux::modal('add-teacher')->close();
        Flux::toast(variant: 'success', text: 'Docente registrado correctamente.');

        unset($this->teachers);
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    #[Computed]
    public function assignTeacher(): ?Teacher
    {
        return $this->assignTeacherId ? Teacher::find($this->assignTeacherId) : null;
    }

    #[Computed]
    public function classroomsForAssignment()
    {
        if (! $this->activeYear || ! $this->assignTeacher) {
            return collect();
        }

        return Classroom::where('academic_year_id', $this->activeYear->id)
            ->where('shift', $this->assignTeacher->shift)
            ->with('grade.educationLevel')
            ->get()
            ->sortBy(fn ($c) => $c->grade->order);
    }

    #[Computed]
    public function subjectsForAssignment()
    {
        if (empty($this->assignClassroomIds)) {
            return collect();
        }

        $classrooms = Classroom::with('grade.subjects')->whereIn('id', $this->assignClassroomIds)->get();

        // Solo materias válidas para TODAS las aulas seleccionadas (pueden ser de niveles distintos).
        $subjectIdSets = $classrooms->map(fn ($c) => $c->grade->subjects->pluck('id')->all())->all();
        $commonIds = $subjectIdSets ? array_intersect(...$subjectIdSets) : [];

        // En modo "maestro de grado" las materias generales se asignan solas;
        // aquí solo se eligen manualmente las especializadas (Inglés, Ed. Física, etc.).
        return Subject::whereIn('id', $commonIds)->where('is_specialized', true)->get();
    }

    #[Computed]
    public function assignments()
    {
        if (! $this->assignTeacherId || ! $this->activeYear) {
            return collect();
        }

        return SubjectAssignment::where('teacher_id', $this->assignTeacherId)
            ->where('academic_year_id', $this->activeYear->id)
            ->with(['classroom.grade', 'subject'])
            ->get();
    }

    public function openAssignModal(int $teacherId): void
    {
        $this->authorize('teacher.manage');

        $this->assignTeacherId = $teacherId;
        $this->assignMode = 'homeroom';
        $this->assignClassroomIds = [];
        $this->assignSubjectIds = [];

        Flux::modal('assign-subject')->show();
    }

    public function updatedAssignMode(): void
    {
        $this->assignSubjectIds = [];
    }

    public function updatedAssignClassroomIds(): void
    {
        $this->assignSubjectIds = [];
    }

    public function addAssignment(): void
    {
        $this->authorize('teacher.manage');

        $this->validate([
            'assignClassroomIds' => 'required|array|min:1',
            'assignClassroomIds.*' => [
                'required',
                Rule::exists('classrooms', 'id')->where('shift', $this->assignTeacher?->shift),
            ],
        ], [
            'assignClassroomIds.*.exists' => 'Solo puedes asignar aulas del turno de este docente.',
        ]);

        if ($this->assignMode === 'specialist') {
            $this->validate([
                'assignSubjectIds' => 'required|array|min:1',
                'assignSubjectIds.*' => 'exists:subjects,id',
            ]);
        }

        // Construye la lista de pares (aula, materia) a crear según el modo.
        $pairs = [];

        foreach ($this->assignClassroomIds as $classroomId) {
            if ($this->assignMode === 'homeroom') {
                // Maestro de grado: todas las materias generales del grado de esa aula.
                $classroom = Classroom::with('grade.subjects')->find($classroomId);
                $subjectIds = $classroom->grade->subjects->where('is_specialized', false)->pluck('id');
            } else {
                $subjectIds = collect($this->assignSubjectIds);
            }

            foreach ($subjectIds as $subjectId) {
                $pairs[] = ['classroom_id' => $classroomId, 'subject_id' => $subjectId];
            }
        }

        $alreadyAssigned = SubjectAssignment::where('academic_year_id', $this->activeYear->id)
            ->whereIn('classroom_id', $this->assignClassroomIds)
            ->get(['classroom_id', 'subject_id']);

        $created = 0;
        $skipped = 0;

        foreach ($pairs as $pair) {
            $taken = $alreadyAssigned->contains(
                fn ($a) => (string) $a->classroom_id === (string) $pair['classroom_id'] && (string) $a->subject_id === (string) $pair['subject_id']
            );

            if ($taken) {
                $skipped++;

                continue;
            }

            SubjectAssignment::create([
                'teacher_id' => $this->assignTeacherId,
                'classroom_id' => $pair['classroom_id'],
                'subject_id' => $pair['subject_id'],
                'academic_year_id' => $this->activeYear->id,
            ]);
            $created++;
        }

        $this->assignClassroomIds = [];
        $this->assignSubjectIds = [];

        unset($this->assignments);

        if ($skipped > 0) {
            Flux::toast(
                variant: 'warning',
                text: "{$created} asignación(es) creada(s). {$skipped} ya tenían esa materia con otro docente y se omitieron."
            );
        } else {
            Flux::toast(variant: 'success', text: "{$created} asignación(es) creada(s) correctamente.");
        }
    }

    public function removeAssignment(int $assignmentId): void
    {
        $this->authorize('teacher.manage');

        SubjectAssignment::where('id', $assignmentId)
            ->where('academic_year_id', $this->activeYear->id)
            ->delete();

        unset($this->assignments);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Docentes</flux:heading>
            <flux:subheading>Listado de docentes registrados</flux:subheading>
        </div>
        @can('teacher.manage')
            <flux:modal.trigger name="add-teacher">
                <flux:button icon="plus" variant="primary">Nuevo docente</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    {{-- Búsqueda y filtro --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o cédula..."
            icon="magnifying-glass"
            class="max-w-sm"
        />

        <flux:select wire:model.live="shiftFilter" placeholder="Todos los turnos" class="max-w-xs">
            <flux:select.option value="">Todos los turnos</flux:select.option>
            @foreach (Shift::cases() as $shiftOption)
                <flux:select.option value="{{ $shiftOption->value }}">{{ $shiftOption->labelWithTime() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabla --}}
    @if ($this->teachers->count())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Cédula</flux:table.column>
                <flux:table.column>Turno</flux:table.column>
                <flux:table.column>Teléfono</flux:table.column>
                <flux:table.column>Especialización</flux:table.column>
                <flux:table.column>Correo</flux:table.column>
                @can('teacher.manage')
                    <flux:table.column></flux:table.column>
                @endcan
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->teachers as $teacher)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            {{ $teacher->full_name }}
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ $teacher->cedula }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($teacher->shift)
                                <flux:badge size="sm" color="zinc">{{ Shift::from($teacher->shift)->label() }}</flux:badge>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $teacher->phone ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $teacher->specialization ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ $teacher->user?->email ?? '—' }}
                        </flux:table.cell>
                        @can('teacher.manage')
                            <flux:table.cell>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="book-open"
                                    wire:click="openAssignModal({{ $teacher->id }})"
                                >
                                    Materias
                                </flux:button>
                            </flux:table.cell>
                        @endcan
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-2">
            {{ $this->teachers->links() }}
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="academic-cap" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:heading>Sin resultados</flux:heading>
            <flux:text class="text-zinc-500">
                @if ($this->search)
                    No se encontraron docentes con "{{ $this->search }}".
                @else
                    Aún no hay docentes registrados.
                @endif
            </flux:text>
        </div>
    @endif

    {{-- Modal: Nuevo docente --}}
    <flux:modal name="add-teacher" class="max-w-md">
        <flux:heading size="lg" class="mb-1">Nuevo docente</flux:heading>
        <flux:subheading class="mb-4">Se creará también su cuenta de acceso al sistema.</flux:subheading>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="firstName" label="Nombre(s)" placeholder="Barbara" required />
                @error('firstName') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:input wire:model="lastName" label="Apellidos" placeholder="Wilson" required />
                @error('lastName') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <flux:input wire:model="cedula" label="Cédula" placeholder="8-123-4567" required />
            @error('cedula') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:input wire:model="email" label="Correo electrónico" type="email" placeholder="docente@siga.pa" required />
            @error('email') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:input wire:model="password" label="Contraseña temporal" type="password" required />
            @error('password') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="phone" label="Teléfono" placeholder="6000-0000" type="tel" />
                <flux:input wire:model="specialization" label="Especialización" placeholder="Educación Primaria" />
            </div>

            <flux:select wire:model="shift" label="Turno">
                @foreach (Shift::cases() as $shiftOption)
                    <flux:select.option value="{{ $shiftOption->value }}">{{ $shiftOption->labelWithTime() }}</flux:select.option>
                @endforeach
            </flux:select>
            @error('shift') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="createTeacher" wire:loading.attr="disabled">
                Registrar
            </flux:button>
        </div>
    </flux:modal>

    {{-- Modal: Materias asignadas --}}
    <flux:modal name="assign-subject" class="max-w-md">
        <flux:heading size="lg" class="mb-1">Materias asignadas</flux:heading>
        <flux:subheading class="mb-4">
            Aulas y materias que este docente puede calificar este año escolar.
            @if ($this->assignTeacher?->shift)
                Solo se muestran aulas del turno {{ Shift::from($this->assignTeacher->shift)->label() }}.
            @endif
        </flux:subheading>

        @if (! $this->activeYear)
            <flux:text class="text-zinc-500">No hay un año escolar activo.</flux:text>
        @else
            <div class="space-y-2 mb-4">
                @forelse ($this->assignments as $assignment)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2 text-sm">
                        <span>
                            {{ $assignment->classroom->grade->name }}-{{ $assignment->classroom->section }}
                            — {{ $assignment->subject->name }}
                        </span>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="trash"
                            wire:click="removeAssignment({{ $assignment->id }})"
                            wire:confirm="¿Quitar esta asignación?"
                        />
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-400">Aún no tiene materias asignadas.</flux:text>
                @endforelse
            </div>

            <div class="space-y-4 border-t border-zinc-100 dark:border-zinc-700 pt-4">
                <flux:radio.group wire:model.live="assignMode" label="Tipo de docente" variant="segmented">
                    <flux:radio value="homeroom" label="Maestro de grado" />
                    <flux:radio value="specialist" label="Especialista" />
                </flux:radio.group>

                @if ($assignMode === 'homeroom')
                    <flux:text class="text-sm text-zinc-500">
                        Da automáticamente todas las materias generales (Español, Matemática, Ciencias Sociales, Ciencias Naturales, Religión) en el/los aula(s) que elijas — no hace falta indicarlas una por una.
                    </flux:text>
                @else
                    <flux:text class="text-sm text-zinc-500">
                        Elige la(s) materia(s) especializada(s) que da (Inglés, Educación Física, Expresión Artística, Tecnologías) en el/los aula(s) que elijas.
                    </flux:text>
                @endif

                <div>
                    <flux:label>Aulas (puedes elegir varias)</flux:label>
                    <div class="mt-2 max-h-40 overflow-y-auto space-y-1 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        @forelse ($this->classroomsForAssignment as $classroom)
                            <flux:checkbox
                                wire:model.live="assignClassroomIds"
                                value="{{ $classroom->id }}"
                                label="{{ $classroom->grade->name }}-{{ $classroom->section }} ({{ $classroom->grade->educationLevel->name }})"
                            />
                        @empty
                            <flux:text class="text-sm text-zinc-400">No hay aulas registradas.</flux:text>
                        @endforelse
                    </div>
                    @error('assignClassroomIds') <flux:error>{{ $message }}</flux:error> @enderror
                </div>

                @if ($assignMode === 'specialist')
                    <div>
                        <flux:label>Materias especializadas</flux:label>
                        <div class="mt-2 max-h-40 overflow-y-auto space-y-1 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                            @forelse ($this->subjectsForAssignment as $subject)
                                <flux:checkbox
                                    wire:model="assignSubjectIds"
                                    value="{{ $subject->id }}"
                                    label="{{ $subject->name }}"
                                />
                            @empty
                                <flux:text class="text-sm text-zinc-400">Selecciona al menos un aula primero.</flux:text>
                            @endforelse
                        </div>
                        @error('assignSubjectIds') <flux:error>{{ $message }}</flux:error> @enderror
                    </div>
                @endif
            </div>
        @endif

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cerrar</flux:button>
            </flux:modal.close>
            @if ($this->activeYear)
                <flux:button variant="primary" wire:click="addAssignment" wire:loading.attr="disabled">
                    Agregar
                </flux:button>
            @endif
        </div>
    </flux:modal>

</div>
