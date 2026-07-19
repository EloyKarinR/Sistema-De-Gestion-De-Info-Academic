<?php

use App\Actions\Academic\GenerateClassSchedule;
use App\Enums\Shift;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\Grade;
use App\Models\Institution;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Académico')] class extends Component
{
    // Formulario: nueva aula
    public string $gradeId = '';

    public string $section = '';

    public string $shift = 'matutino';

    public int $capacity = 30;

    // Formulario: nuevo año escolar
    public int $newYear = 0;

    public string $startDate = '';

    public string $endDate = '';

    public ?int $scheduleClassroomId = null;

    public function mount(): void
    {
        $this->newYear = now()->year;
        $this->startDate = now()->startOfYear()->format('Y-m-d');
        $this->endDate = now()->endOfYear()->format('Y-m-d');
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)
            ->with(['periods', 'classrooms.grade.educationLevel'])
            ->first();
    }

    #[Computed]
    public function classroomsWithoutScheduleCount(): int
    {
        if (! $this->activeYear) {
            return 0;
        }

        return Classroom::where('academic_year_id', $this->activeYear->id)
            ->doesntHave('classSchedules')
            ->count();
    }

    #[Computed]
    public function classroomsByShift()
    {
        if (! $this->activeYear) {
            return collect();
        }

        return $this->activeYear->classrooms
            ->sortBy(fn ($c) => $c->grade->order)
            ->groupBy('shift');
    }

    #[Computed]
    public function grades()
    {
        $institution = Institution::first();

        return Grade::whereHas('educationLevel', fn ($q) => $q->where('institution_type', $institution?->type ?? 'escuela')
        )
            ->with('educationLevel')
            ->orderBy('order')
            ->get()
            ->groupBy(fn ($g) => $g->educationLevel->name);
    }

    public function addClassroom(): void
    {
        $this->authorize('academic.manage');

        $this->validate([
            'gradeId' => 'required|exists:grades,id',
            'section' => 'required|string|max:1|regex:/^[a-zA-Z]$/',
            'shift' => 'required|in:matutino,vespertino',
            'capacity' => 'required|integer|min:1|max:60',
        ], [
            'section.regex' => 'La sección debe ser una sola letra (A-Z).',
        ]);

        $year = AcademicYear::where('is_active', true)->first();

        if (! $year) {
            Flux::toast(variant: 'danger', text: 'No hay un año escolar activo.');

            return;
        }

        $sectionUpper = strtoupper($this->section);

        $exists = Classroom::where('academic_year_id', $year->id)
            ->where('grade_id', $this->gradeId)
            ->where('section', $sectionUpper)
            ->exists();

        if ($exists) {
            $this->addError('section', 'Ya existe esa sección para este grado en el año activo.');

            return;
        }

        Classroom::create([
            'academic_year_id' => $year->id,
            'grade_id' => $this->gradeId,
            'section' => $sectionUpper,
            'shift' => $this->shift,
            'capacity' => $this->capacity,
        ]);

        $this->reset(['gradeId', 'section']);
        $this->shift = 'matutino';
        $this->capacity = 30;

        Flux::modal('add-classroom')->close();
        Flux::toast(variant: 'success', text: 'Aula creada correctamente.');

        unset($this->activeYear);
    }

    public function copyClassroomsFromPreviousYear(): void
    {
        $this->authorize('academic.manage');

        $year = AcademicYear::where('is_active', true)->first();

        if (! $year) {
            Flux::toast(variant: 'danger', text: 'No hay un año escolar activo.');

            return;
        }

        $previousYear = AcademicYear::where('id', '!=', $year->id)
            ->orderByDesc('year')
            ->first();

        if (! $previousYear) {
            Flux::toast(variant: 'danger', text: 'No hay un año anterior del cual copiar aulas.');

            return;
        }

        $existingKeys = Classroom::where('academic_year_id', $year->id)
            ->get(['grade_id', 'section'])
            ->map(fn ($c) => "{$c->grade_id}-{$c->section}");

        $created = 0;

        foreach (Classroom::where('academic_year_id', $previousYear->id)->get() as $classroom) {
            if ($existingKeys->contains("{$classroom->grade_id}-{$classroom->section}")) {
                continue;
            }

            Classroom::create([
                'academic_year_id' => $year->id,
                'grade_id' => $classroom->grade_id,
                'section' => $classroom->section,
                'shift' => $classroom->shift,
                'capacity' => $classroom->capacity,
            ]);

            $created++;
        }

        unset($this->activeYear);

        Flux::toast(variant: 'success', text: "{$created} aula(s) copiada(s) desde {$previousYear->year}.");
    }

    public function generateMissingSchedules(): void
    {
        $this->authorize('academic.manage');

        $year = AcademicYear::where('is_active', true)->with('classrooms')->first();

        if (! $year) {
            Flux::toast(variant: 'danger', text: 'No hay un año escolar activo.');

            return;
        }

        $generator = new GenerateClassSchedule;
        $generated = 0;
        $skipped = 0;

        foreach ($year->classrooms as $classroom) {
            if ($classroom->classSchedules()->exists()) {
                continue;
            }

            $generator->handle($classroom);

            if ($classroom->classSchedules()->exists()) {
                $generated++;
            } else {
                $skipped++;
            }
        }

        unset($this->activeYear);

        $message = "{$generated} horario(s) generado(s).";

        if ($skipped > 0) {
            $message .= " {$skipped} aula(s) sin materias asignadas todavía, no se les pudo generar horario.";
        }

        Flux::toast(variant: 'success', text: $message);
    }

    public function deleteClassroom(int $id): void
    {
        $this->authorize('academic.manage');

        $classroom = Classroom::withCount(['enrollments' => fn ($q) => $q->where('status', 'activo')])->findOrFail($id);

        if ($classroom->enrollments_count > 0) {
            Flux::toast(variant: 'danger', text: 'No se puede eliminar un aula con estudiantes activos.');

            return;
        }

        $classroom->delete();

        unset($this->activeYear);
        Flux::toast(variant: 'success', text: 'Aula eliminada.');
    }

    public function createYear(): void
    {
        $this->authorize('academic.manage');

        $this->validate([
            'newYear' => 'required|integer|min:2020|max:2099',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]);

        $institution = Institution::first();

        if (! $institution) {
            Flux::toast(variant: 'danger', text: 'Configura los datos de la institución primero.');

            return;
        }

        $existing = AcademicYear::where('year', $this->newYear)->first();

        if ($existing) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $existing->update(['is_active' => true]);

            unset($this->activeYear);
            Flux::modal('create-year')->close();
            Flux::toast(
                variant: 'success',
                text: "El año {$existing->year} ya existía — se activó de nuevo en vez de crear uno duplicado."
            );

            return;
        }

        AcademicYear::where('is_active', true)->update(['is_active' => false]);

        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);
        $days = (int) ($start->diffInDays($end) / 3);

        $year = AcademicYear::create([
            'institution_id' => $institution->id,
            'year' => $this->newYear,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'is_active' => true,
        ]);

        $year->periods()->createMany([
            ['number' => 1, 'name' => 'I Trimestre',   'start_date' => $start->toDateString(),                          'end_date' => $start->copy()->addDays($days)->toDateString()],
            ['number' => 2, 'name' => 'II Trimestre',  'start_date' => $start->copy()->addDays($days + 1)->toDateString(), 'end_date' => $start->copy()->addDays($days * 2)->toDateString()],
            ['number' => 3, 'name' => 'III Trimestre', 'start_date' => $start->copy()->addDays($days * 2 + 1)->toDateString(), 'end_date' => $end->toDateString()],
        ]);

        unset($this->activeYear);
        Flux::modal('create-year')->close();
        Flux::toast(variant: 'success', text: "Año escolar {$this->newYear} creado y activado.");
    }

    public function viewSchedule(int $classroomId): void
    {
        $this->scheduleClassroomId = $classroomId;

        Flux::modal('view-schedule')->show();
    }

    #[Computed]
    public function scheduleClassroom(): ?Classroom
    {
        if (! $this->scheduleClassroomId) {
            return null;
        }

        return Classroom::with('grade.educationLevel')->find($this->scheduleClassroomId);
    }

    #[Computed]
    public function scheduleByDay()
    {
        if (! $this->scheduleClassroomId) {
            return collect();
        }

        return ClassSchedule::where('classroom_id', $this->scheduleClassroomId)
            ->with('subjectAssignment.subject', 'subjectAssignment.teacher')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');
    }

    /**
     * Fill/track classes for a capacity meter, keyed by severity so both
     * halves of the bar shift hue together (blue-on-blue, amber-on-amber…).
     */
    public function meterClasses(int $enrolled, int $capacity): array
    {
        $percentage = $capacity > 0 ? $enrolled / $capacity : 0;

        return match (true) {
            $percentage >= 0.9 => ['fill' => 'bg-red-500', 'track' => 'bg-red-100 dark:bg-red-950/50'],
            $percentage >= 0.7 => ['fill' => 'bg-amber-500', 'track' => 'bg-amber-100 dark:bg-amber-950/50'],
            default => ['fill' => 'bg-blue-500', 'track' => 'bg-blue-100 dark:bg-blue-950/50'],
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Académico</flux:heading>
            <flux:subheading>Gestión de años escolares y aulas</flux:subheading>
        </div>
    </div>

    {{-- Año Escolar Activo --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                    <flux:icon name="calendar" class="size-5" />
                </div>
                <flux:heading size="lg">Año Escolar Activo</flux:heading>
            </div>
            @can('academic.manage')
                <div class="flex flex-wrap gap-2">
                    <flux:button icon="arrow-up-circle" size="sm" variant="ghost" :href="route('academic.promote')" wire:navigate>
                        Promover estudiantes
                    </flux:button>
                    <flux:modal.trigger name="create-year">
                        <flux:button icon="plus" size="sm">Nuevo año</flux:button>
                    </flux:modal.trigger>
                </div>
            @endcan
        </div>

        @if ($this->activeYear)
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-4">
                    <flux:badge color="green" size="lg">{{ $this->activeYear->year }}</flux:badge>
                    <flux:text class="text-sm text-zinc-500">
                        {{ $this->activeYear->start_date->format('d/m/Y') }} — {{ $this->activeYear->end_date->format('d/m/Y') }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->activeYear->periods as $period)
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 text-sm">
                            <span class="font-medium">{{ $period->name }}</span>
                            <span class="ml-2 text-zinc-500">
                                {{ $period->start_date->format('d/m') }} — {{ $period->end_date->format('d/m') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <flux:text class="text-zinc-500">No hay un año escolar activo. Crea uno para comenzar.</flux:text>
        @endif
    </div>

    {{-- Aulas --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
        <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-400">
                    <flux:icon name="building-office-2" class="size-5" />
                </div>
                <flux:heading size="lg">Aulas</flux:heading>
            </div>
            @can('academic.manage')
                @if ($this->activeYear)
                    <div class="flex flex-wrap gap-2">
                        @if ($this->activeYear->classrooms->isEmpty())
                            <flux:button
                                icon="document-duplicate"
                                size="sm"
                                variant="ghost"
                                wire:click="copyClassroomsFromPreviousYear"
                                wire:confirm="¿Copiar las aulas del año anterior a {{ $this->activeYear->year }}?"
                            >
                                Copiar aulas del año anterior
                            </flux:button>
                        @endif
                        @if ($this->classroomsWithoutScheduleCount > 0)
                            <flux:button
                                icon="calendar-days"
                                size="sm"
                                variant="ghost"
                                wire:click="generateMissingSchedules"
                                wire:confirm="¿Generar el horario de las {{ $this->classroomsWithoutScheduleCount }} aula(s) que todavía no tienen uno?"
                            >
                                Generar horarios faltantes
                            </flux:button>
                        @endif
                        <flux:modal.trigger name="add-classroom">
                            <flux:button icon="plus" size="sm" variant="primary">Agregar aula</flux:button>
                        </flux:modal.trigger>
                    </div>
                @endif
            @endcan
        </div>

        @if ($this->activeYear && $this->activeYear->classrooms->count())
            <div class="space-y-6">
                @foreach (Shift::cases() as $shiftOption)
                    @continue($this->classroomsByShift->get($shiftOption->value, collect())->isEmpty())
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$shiftOption->color()" :icon="$shiftOption->icon()">{{ $shiftOption->label() }}</flux:badge>
                            <flux:subheading>{{ $shiftOption->timeRange() }}</flux:subheading>
                        </div>
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Grado</flux:table.column>
                                <flux:table.column>Sección</flux:table.column>
                                <flux:table.column>Nivel</flux:table.column>
                                <flux:table.column>Capacidad</flux:table.column>
                                <flux:table.column>Matriculados</flux:table.column>
                                <flux:table.column></flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->classroomsByShift->get($shiftOption->value, collect()) as $classroom)
                                    <flux:table.row>
                                        <flux:table.cell class="font-medium">{{ $classroom->grade->name }}</flux:table.cell>
                                        <flux:table.cell>{{ $classroom->section }}</flux:table.cell>
                                        <flux:table.cell>
                                            <flux:badge size="sm" color="zinc">{{ $classroom->grade->educationLevel->name }}</flux:badge>
                                        </flux:table.cell>
                                        <flux:table.cell>{{ $classroom->capacity }}</flux:table.cell>
                                        <flux:table.cell>
                                            @php
                                                $enrolledCount = $classroom->enrollments->where('status', 'activo')->count();
                                                $percentage = $classroom->capacity > 0 ? min(100, round($enrolledCount / $classroom->capacity * 100)) : 0;
                                                $meter = $this->meterClasses($enrolledCount, $classroom->capacity);
                                            @endphp
                                            <div class="flex items-center gap-2 min-w-[100px]">
                                                <div class="h-2 flex-1 rounded-full {{ $meter['track'] }} overflow-hidden">
                                                    <div class="h-full rounded-full {{ $meter['fill'] }}" style="width: {{ $percentage }}%"></div>
                                                </div>
                                                <span class="text-xs text-zinc-500 tabular-nums shrink-0">{{ $enrolledCount }}/{{ $classroom->capacity }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <div class="flex gap-2">
                                                <flux:button
                                                    icon="calendar-days"
                                                    size="sm"
                                                    variant="ghost"
                                                    wire:click="viewSchedule({{ $classroom->id }})"
                                                >
                                                    Horario
                                                </flux:button>
                                                @can('academic.manage')
                                                    <flux:button
                                                        icon="trash"
                                                        size="sm"
                                                        variant="ghost"
                                                        wire:click="deleteClassroom({{ $classroom->id }})"
                                                        wire:confirm="¿Eliminar este aula?"
                                                    />
                                                @endcan
                                            </div>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endforeach
            </div>
        @elseif ($this->activeYear)
            <flux:text class="text-zinc-500">No hay aulas registradas para este año. Agrega la primera.</flux:text>
        @else
            <flux:text class="text-zinc-500">Crea un año escolar para poder gestionar aulas.</flux:text>
        @endif
    </div>

    {{-- Modal: Agregar Aula --}}
    <flux:modal name="add-classroom" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Agregar aula</flux:heading>

        <div class="space-y-4">
            <flux:select wire:model="gradeId" label="Grado" placeholder="Selecciona un grado">
                @foreach ($this->grades as $levelName => $gradeList)
                    <flux:select.option disabled value="">— {{ $levelName }} —</flux:select.option>
                    @foreach ($gradeList as $grade)
                        <flux:select.option value="{{ $grade->id }}">{{ $grade->name }}</flux:select.option>
                    @endforeach
                @endforeach
            </flux:select>
            @error('gradeId') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="section"
                    label="Sección"
                    placeholder="A"
                    maxlength="1"
                    class="uppercase"
                />
                @error('section') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:select wire:model="shift" label="Turno">
                    @foreach (Shift::cases() as $shiftOption)
                        <flux:select.option value="{{ $shiftOption->value }}">{{ $shiftOption->labelWithTime() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input
                wire:model="capacity"
                label="Capacidad"
                type="number"
                min="1"
                max="60"
            />
            @error('capacity') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="addClassroom">Guardar</flux:button>
        </div>
    </flux:modal>

    {{-- Modal: Nuevo Año Escolar --}}
    <flux:modal name="create-year" class="max-w-md">
        <flux:heading size="lg" class="mb-1">Nuevo año escolar</flux:heading>
        <flux:subheading class="mb-4">El año actual quedará como inactivo. Si el año que escribes ya existe, se activará de nuevo en vez de crear uno duplicado.</flux:subheading>

        <div class="space-y-4">
            <flux:input
                wire:model="newYear"
                label="Año"
                type="number"
                min="2020"
                max="2099"
            />
            @error('newYear') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="startDate"
                    label="Fecha inicio"
                    type="date"
                />
                @error('startDate') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:input
                    wire:model="endDate"
                    label="Fecha fin"
                    type="date"
                />
                @error('endDate') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <flux:text class="text-xs text-zinc-500">Los tres trimestres se crearán automáticamente dividiendo el período en partes iguales.</flux:text>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="createYear">Crear y activar</flux:button>
        </div>
    </flux:modal>

    {{-- Modal: Horario semanal --}}
    <flux:modal name="view-schedule" class="max-w-3xl">
        @if ($this->scheduleClassroom)
            <flux:heading size="lg" class="mb-1">
                Horario — {{ $this->scheduleClassroom->grade->name }}-{{ $this->scheduleClassroom->section }}
            </flux:heading>
            @php $scheduleShift = Shift::from($this->scheduleClassroom->shift); @endphp
            <flux:badge size="sm" :color="$scheduleShift->color()" :icon="$scheduleShift->icon()" class="mb-4">
                {{ $scheduleShift->labelWithTime() }}
            </flux:badge>

            @if ($this->scheduleByDay->isEmpty())
                <flux:text class="text-zinc-500">Esta aula todavía no tiene un horario generado.</flux:text>
            @else
                @php
                    $dayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
                    $timeSlots = $this->scheduleByDay->flatten(1)->unique(fn ($s) => $s->start_time->format('H:i'))->sortBy('start_time');
                @endphp
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm border-collapse">
                        <thead>
                            <tr>
                                <th class="text-left text-zinc-500 font-medium p-2 border-b border-zinc-200 dark:border-zinc-700">Hora</th>
                                @foreach ($dayNames as $dayName)
                                    <th class="text-left text-zinc-500 font-medium p-2 border-b border-zinc-200 dark:border-zinc-700">{{ $dayName }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timeSlots as $slot)
                                <tr>
                                    <td class="p-2 border-b border-zinc-100 dark:border-zinc-800 text-zinc-500 whitespace-nowrap">
                                        {{ $slot->start_time->format('H:i') }}–{{ $slot->end_time->format('H:i') }}
                                    </td>
                                    @foreach (array_keys($dayNames) as $day)
                                        @php
                                            $entry = ($this->scheduleByDay->get($day) ?? collect())
                                                ->first(fn ($s) => $s->start_time->format('H:i') === $slot->start_time->format('H:i'));
                                        @endphp
                                        <td class="p-2 border-b border-zinc-100 dark:border-zinc-800">
                                            @if ($entry)
                                                <div class="font-medium">{{ $entry->subjectAssignment->subject->name }}</div>
                                                <div class="text-xs text-zinc-400">{{ $entry->subjectAssignment->teacher->full_name }}</div>
                                            @else
                                                <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        <div class="mt-6 flex justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Cerrar</flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>

</div>
