<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Habit;
use App\Models\HabitScore;
use App\Models\Institution;
use App\Models\SubjectAssignment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Hábitos y Actitudes')] class extends Component
{
    #[Url(as: 'aula')]
    public string $classroomId = '';

    #[Url(as: 'trimestre')]
    public string $periodId = '';

    /** @var array<int, array<int, string>> scores[enrollment_id][habit_id] = S|R|X */
    public array $scores = [];

    public function mount(): void
    {
        if ($this->periodId === '' && $this->activeYear) {
            $current = $this->activeYear->periods->first(fn ($p) => now()->between($p->start_date, $p->end_date));

            $this->periodId = (string) ($current?->id ?? $this->activeYear->periods->last()?->id ?? '');
        }

        if ($this->classroomId && $this->periodId) {
            $this->loadScores();
        }
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->with('periods')->first();
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
    public function habits()
    {
        $institution = Institution::first();

        if (! $institution) {
            return collect();
        }

        return Habit::where('institution_id', $institution->id)->orderBy('order')->get();
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
        $this->loadScores();
    }

    public function updatedPeriodId(): void
    {
        $this->loadScores();
    }

    public function loadScores(): void
    {
        $this->scores = [];

        if (! $this->classroomId || ! $this->periodId) {
            return;
        }

        $existing = HabitScore::whereIn('enrollment_id', $this->enrollments->pluck('id'))
            ->where('period_id', $this->periodId)
            ->get()
            ->groupBy('enrollment_id');

        foreach ($this->enrollments as $enrollment) {
            $byHabit = $existing->get($enrollment->id, collect())->keyBy('habit_id');

            foreach ($this->habits as $habit) {
                $this->scores[$enrollment->id][$habit->id] = $byHabit->get($habit->id)?->score ?? '';
            }
        }
    }

    public function saveScores(): void
    {
        $this->authorize('habits.enter');

        if ($this->myTeacher) {
            $assigned = $this->myAssignments->contains(
                fn ($a) => (string) $a->classroom_id === $this->classroomId
            );

            abort_unless($assigned, 403, 'No tienes esta aula asignada.');
        }

        foreach ($this->scores as $enrollmentId => $habitScores) {
            foreach ($habitScores as $habitId => $score) {
                if ($score === '' || $score === null) {
                    continue;
                }

                HabitScore::updateOrCreate(
                    ['enrollment_id' => $enrollmentId, 'habit_id' => $habitId, 'period_id' => $this->periodId],
                    ['score' => $score]
                );
            }
        }

        Flux::toast(variant: 'success', text: 'Hábitos y actitudes guardados correctamente.');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl">Hábitos y Actitudes</flux:heading>
        <flux:subheading>Evaluación de hábitos por aula y trimestre</flux:subheading>
    </div>

    @if (! $this->activeYear)
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="calendar" class="mb-3 size-10 text-zinc-300" />
            <flux:heading>Sin año escolar activo</flux:heading>
            <flux:text class="text-zinc-500">Activa un año escolar antes de registrar hábitos.</flux:text>
        </div>
    @elseif ($this->habits->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="face-smile" class="mb-3 size-10 text-zinc-300" />
            <flux:heading>Sin hábitos configurados</flux:heading>
            <flux:text class="text-zinc-500">No hay hábitos registrados para la institución.</flux:text>
        </div>
    @else
        {{-- Selectores --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                    <flux:icon name="face-smile" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg" class="leading-tight">Selecciona la clase</flux:heading>
                    <flux:text class="text-zinc-500 text-sm">Elige aula y trimestre para cargar los hábitos</flux:text>
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

                <flux:select wire:model.live="periodId" label="Trimestre" placeholder="Selecciona un trimestre">
                    @foreach ($this->activeYear->periods as $period)
                        <flux:select.option value="{{ $period->id }}">{{ $period->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        {{-- Tabla de hábitos --}}
        @if ($classroomId && $periodId)
            @if ($this->enrollments->count())
                <fieldset @disabled(! auth()->user()->can('habits.enter'))>
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                        @cannot('habits.enter')
                            <div class="flex items-center gap-2 rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-4 py-3 text-sm text-zinc-500">
                                <flux:icon name="lock-closed" class="size-4 shrink-0" />
                                Solo lectura — no tienes permiso para registrar hábitos.
                            </div>
                        @endcannot

                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Estudiante</flux:table.column>
                                @foreach ($this->habits as $habit)
                                    <flux:table.column>{{ $habit->name }}</flux:table.column>
                                @endforeach
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
                                        @foreach ($this->habits as $habit)
                                            <flux:table.cell>
                                                <flux:select wire:model="scores.{{ $enrollment->id }}.{{ $habit->id }}" size="sm" class="max-w-[90px]">
                                                    <flux:select.option value="">—</flux:select.option>
                                                    <flux:select.option value="S">S</flux:select.option>
                                                    <flux:select.option value="R">R</flux:select.option>
                                                    <flux:select.option value="X">X</flux:select.option>
                                                </flux:select>
                                            </flux:table.cell>
                                        @endforeach
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>

                        <flux:text class="text-xs text-zinc-500">
                            S: Satisfactorio · R: Regular · X: No satisface
                        </flux:text>

                        @can('habits.enter')
                            <div class="flex justify-end">
                                <flux:button variant="primary" wire:click="saveScores" wire:loading.attr="disabled">
                                    Guardar hábitos
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
