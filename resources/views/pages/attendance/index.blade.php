<?php

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\SubjectAssignment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Asistencia')] class extends Component
{
    #[Url(as: 'aula')]
    public string $classroomId = '';

    #[Url(as: 'fecha')]
    public string $date = '';

    /** @var array<int, string> presente|ausencia|tardanza, por enrollment_id */
    public array $statuses = [];

    /** @var array<int, bool> */
    public array $justified = [];

    /** @var array<int, string> */
    public array $reasons = [];

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = now()->format('Y-m-d');
        }

        if ($this->classroomId) {
            $this->loadAttendance();
        }
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    #[Computed]
    public function myTeacher()
    {
        return Auth::user()->teacher;
    }

    #[Computed]
    public function myAssignments()
    {
        if (! $this->myTeacher || ! $this->activeYear) {
            return collect();
        }

        return SubjectAssignment::where('teacher_id', $this->myTeacher->id)
            ->where('academic_year_id', $this->activeYear->id)
            ->get();
    }

    #[Computed]
    public function classrooms()
    {
        if (! $this->activeYear) {
            return collect();
        }

        $query = Classroom::where('academic_year_id', $this->activeYear->id)->with('grade.educationLevel');

        if ($this->myTeacher) {
            $query->whereIn('id', $this->myAssignments->pluck('classroom_id')->unique());
        }

        return $query->get()->sortBy(fn ($c) => $c->grade->order);
    }

    #[Computed]
    public function enrollments()
    {
        if (! $this->classroomId) {
            return collect();
        }

        return Enrollment::where('classroom_id', $this->classroomId)
            ->where('status', 'activo')
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name);
    }

    public function updatedClassroomId(): void
    {
        $this->loadAttendance();
    }

    public function updatedDate(): void
    {
        $this->loadAttendance();
    }

    public function loadAttendance(): void
    {
        $this->statuses = [];
        $this->justified = [];
        $this->reasons = [];

        if (! $this->classroomId || ! $this->date) {
            return;
        }

        $existing = Attendance::whereIn('enrollment_id', $this->enrollments->pluck('id'))
            ->whereDate('date', $this->date)
            ->get()
            ->keyBy('enrollment_id');

        foreach ($this->enrollments as $enrollment) {
            $record = $existing->get($enrollment->id);

            $this->statuses[$enrollment->id] = $record?->type ?? 'presente';
            $this->justified[$enrollment->id] = $record?->justified ?? false;
            $this->reasons[$enrollment->id] = $record?->reason ?? '';
        }
    }

    public function saveAttendance(): void
    {
        $this->authorize('attendance.enter');

        if ($this->myTeacher) {
            $assigned = $this->myAssignments->contains(
                fn ($a) => (string) $a->classroom_id === $this->classroomId
            );

            abort_unless($assigned, 403, 'No tienes esta aula asignada.');
        }

        $this->validate([
            'reasons.*' => 'nullable|string|max:255',
        ]);

        foreach ($this->enrollments as $enrollment) {
            $status = $this->statuses[$enrollment->id] ?? 'presente';

            // Solo se guarda una excepción por día (ausencia/tardanza); "presente"
            // simplemente no deja registro, así que se borra cualquier fila previa.
            Attendance::where('enrollment_id', $enrollment->id)->whereDate('date', $this->date)->delete();

            if ($status !== 'presente') {
                Attendance::create([
                    'enrollment_id' => $enrollment->id,
                    'date' => $this->date,
                    'type' => $status,
                    'justified' => $this->justified[$enrollment->id] ?? false,
                    'reason' => $this->reasons[$enrollment->id] ?: null,
                ]);
            }
        }

        Flux::toast(variant: 'success', text: 'Asistencia guardada correctamente.');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl">Asistencia</flux:heading>
        <flux:subheading>Registro diario de asistencia por aula</flux:subheading>
    </div>

    @if (! $this->activeYear)
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="calendar" class="mb-3 size-10 text-zinc-300" />
            <flux:heading>Sin año escolar activo</flux:heading>
            <flux:text class="text-zinc-500">Activa un año escolar antes de registrar asistencia.</flux:text>
        </div>
    @else
        {{-- Selectores --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/50 dark:text-teal-400">
                    <flux:icon name="clipboard-document-check" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg" class="leading-tight">Selecciona la clase</flux:heading>
                    <flux:text class="text-zinc-500 text-sm">Elige aula y fecha para cargar la asistencia</flux:text>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="classroomId" label="Aula" placeholder="Selecciona un aula">
                    @foreach ($this->classrooms as $classroom)
                        <flux:select.option value="{{ $classroom->id }}">
                            {{ $classroom->grade->name }}-{{ $classroom->section }} ({{ $classroom->grade->educationLevel->name }})
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model.live="date" label="Fecha" type="date" />
            </div>
        </div>

        {{-- Tabla de asistencia --}}
        @if ($classroomId && $date)
            @if ($this->enrollments->count())
                <fieldset @disabled(! auth()->user()->can('attendance.enter'))>
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                        @cannot('attendance.enter')
                            <div class="flex items-center gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-4 py-3 text-sm text-zinc-500">
                                <flux:icon name="lock-closed" class="size-4 shrink-0" />
                                Solo lectura — no tienes permiso para registrar asistencia.
                            </div>
                        @endcannot

                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Estudiante</flux:table.column>
                                <flux:table.column>Estado</flux:table.column>
                                <flux:table.column>Justificada</flux:table.column>
                                <flux:table.column>Motivo</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->enrollments as $enrollment)
                                    <flux:table.row>
                                        <flux:table.cell>
                                            <div class="flex items-center gap-3">
                                                <x-avatar-initials :initials="$enrollment->student->initials" :photo="$enrollment->student->photo" />
                                                <span class="font-medium">{{ $enrollment->student->full_name }}</span>
                                            </div>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:select wire:model.live="statuses.{{ $enrollment->id }}" size="sm" class="max-w-[160px]">
                                                <flux:select.option value="presente">Presente</flux:select.option>
                                                <flux:select.option value="ausencia">Ausente</flux:select.option>
                                                <flux:select.option value="tardanza">Tardanza</flux:select.option>
                                            </flux:select>
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if (($statuses[$enrollment->id] ?? 'presente') !== 'presente')
                                                <flux:checkbox wire:model="justified.{{ $enrollment->id }}" />
                                            @endif
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            @if (($statuses[$enrollment->id] ?? 'presente') !== 'presente')
                                                <flux:input wire:model="reasons.{{ $enrollment->id }}" size="sm" placeholder="Motivo (opcional)" class="max-w-[200px]" />
                                            @endif
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>

                        @can('attendance.enter')
                            <div class="flex justify-end">
                                <flux:button variant="primary" wire:click="saveAttendance" wire:loading.attr="disabled">
                                    Guardar asistencia
                                </flux:button>
                            </div>
                        @endcan
                    </div>
                </fieldset>
            @else
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <flux:icon name="user-group" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading>Sin estudiantes</flux:heading>
                    <flux:text class="text-zinc-500">No hay estudiantes matriculados activos en esta aula.</flux:text>
                </div>
            @endif
        @endif
    @endif

</div>
