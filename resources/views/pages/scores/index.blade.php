<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\GradeScore;
use App\Models\SubjectAssignment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Notas')] class extends Component
{
    public string $classroomId = '';

    public string $subjectId = '';

    public string $periodId = '';

    /** @var array<int, string> */
    public array $scores = [];

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
            $query->whereIn('id', $this->myAssignments->pluck('classroom_id'));
        }

        return $query->get()->sortBy(fn ($c) => $c->grade->order);
    }

    #[Computed]
    public function subjects()
    {
        if (! $this->classroomId) {
            return collect();
        }

        $subjects = Classroom::with('grade.subjects')->find($this->classroomId)?->grade->subjects ?? collect();

        if ($this->myTeacher) {
            $allowedSubjectIds = $this->myAssignments
                ->where('classroom_id', (int) $this->classroomId)
                ->pluck('subject_id');

            $subjects = $subjects->whereIn('id', $allowedSubjectIds);
        }

        return $subjects;
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
        $this->subjectId = '';
        $this->loadScores();
    }

    public function updatedSubjectId(): void
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

        if (! $this->classroomId || ! $this->subjectId || ! $this->periodId) {
            return;
        }

        $existing = GradeScore::whereIn('enrollment_id', $this->enrollments->pluck('id'))
            ->where('subject_id', $this->subjectId)
            ->where('period_id', $this->periodId)
            ->get()
            ->keyBy('enrollment_id');

        foreach ($this->enrollments as $enrollment) {
            $this->scores[$enrollment->id] = (string) ($existing->get($enrollment->id)?->score ?? '');
        }
    }

    public function saveScores(): void
    {
        $this->authorize('scores.enter');

        if ($this->myTeacher) {
            $assigned = $this->myAssignments->contains(
                fn ($a) => (string) $a->classroom_id === $this->classroomId && (string) $a->subject_id === $this->subjectId
            );

            abort_unless($assigned, 403, 'No tienes esta materia asignada en esta aula.');
        }

        $this->validate([
            'scores.*' => 'nullable|numeric|min:0|max:100',
        ]);

        foreach ($this->scores as $enrollmentId => $score) {
            if ($score === '' || $score === null) {
                continue;
            }

            GradeScore::updateOrCreate(
                ['enrollment_id' => $enrollmentId, 'subject_id' => $this->subjectId, 'period_id' => $this->periodId],
                ['score' => $score]
            );
        }

        Flux::toast(variant: 'success', text: 'Notas guardadas correctamente.');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl">Notas</flux:heading>
        <flux:subheading>Captura de calificaciones por aula, materia y trimestre</flux:subheading>
    </div>

    @if (! $this->activeYear)
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="calendar" class="mb-3 size-10 text-zinc-300" />
            <flux:heading>Sin año escolar activo</flux:heading>
            <flux:text class="text-zinc-500">Activa un año escolar antes de registrar notas.</flux:text>
        </div>
    @else
        {{-- Selectores --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:select wire:model.live="classroomId" label="Aula" placeholder="Selecciona un aula">
                @foreach ($this->classrooms as $classroom)
                    <flux:select.option value="{{ $classroom->id }}">
                        {{ $classroom->grade->name }}-{{ $classroom->section }} ({{ $classroom->grade->educationLevel->name }})
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="subjectId" label="Materia" placeholder="Selecciona una materia" :disabled="! $classroomId">
                @foreach ($this->subjects as $subject)
                    <flux:select.option value="{{ $subject->id }}">{{ $subject->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="periodId" label="Trimestre" placeholder="Selecciona un trimestre">
                @foreach ($this->activeYear->periods as $period)
                    <flux:select.option value="{{ $period->id }}">{{ $period->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        {{-- Tabla de notas --}}
        @if ($classroomId && $subjectId && $periodId)
            @if ($this->enrollments->count())
                <fieldset @disabled(! auth()->user()->can('scores.enter'))>
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                        @cannot('scores.enter')
                            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-4 py-3 text-sm text-zinc-500">
                                Solo lectura — no tienes permiso para registrar notas.
                            </div>
                        @endcannot

                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column>Estudiante</flux:table.column>
                                <flux:table.column>Nota (0-100)</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->enrollments as $enrollment)
                                    <flux:table.row>
                                        <flux:table.cell class="font-medium">
                                            {{ $enrollment->student->full_name }}
                                        </flux:table.cell>
                                        <flux:table.cell>
                                            <flux:input
                                                wire:model="scores.{{ $enrollment->id }}"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.1"
                                                class="max-w-[120px]"
                                            />
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>

                        @can('scores.enter')
                            <div class="flex justify-end">
                                <flux:button variant="primary" wire:click="saveScores" wire:loading.attr="disabled">
                                    Guardar notas
                                </flux:button>
                            </div>
                        @endcan
                    </div>
                </fieldset>
            @else
                <flux:text class="text-zinc-500">No hay estudiantes matriculados activos en esta aula.</flux:text>
            @endif
        @endif
    @endif

</div>
