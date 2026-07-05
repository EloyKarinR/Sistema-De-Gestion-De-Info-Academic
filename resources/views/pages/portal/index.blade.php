<?php

use App\Enums\Shift;
use App\Models\ClassSchedule;
use App\Models\Enrollment;
use App\Models\GradeScore;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Mi Portal')] class extends Component
{
    public ?int $selectedStudentId = null;

    public function mount(): void
    {
        $this->selectedStudentId = $this->children->first()?->id;
    }

    #[Computed]
    public function guardian()
    {
        return Auth::user()->guardian;
    }

    #[Computed]
    public function children()
    {
        return $this->guardian
            ?->students()
            ->with(['activeEnrollment.classroom.grade.educationLevel', 'activeEnrollment.academicYear.periods'])
            ->get() ?? collect();
    }

    #[Computed]
    public function selectedStudent()
    {
        return $this->children->firstWhere('id', $this->selectedStudentId);
    }

    #[Computed]
    public function classmates()
    {
        $enrollment = $this->selectedStudent?->activeEnrollment;

        if (! $enrollment) {
            return collect();
        }

        return Enrollment::where('classroom_id', $enrollment->classroom_id)
            ->where('status', 'activo')
            ->where('student_id', '!=', $this->selectedStudent->id)
            ->with('student')
            ->get()
            ->map(fn ($e) => $e->student)
            ->sortBy('last_name');
    }

    #[Computed]
    public function gradesMatrix()
    {
        $enrollment = $this->selectedStudent?->activeEnrollment;

        if (! $enrollment) {
            return [];
        }

        $matrix = [];

        foreach (GradeScore::where('enrollment_id', $enrollment->id)->with('subject')->get() as $score) {
            $matrix[$score->subject->name][$score->period_id] = $score->score;
        }

        return $matrix;
    }

    #[Computed]
    public function scheduleByDay()
    {
        $enrollment = $this->selectedStudent?->activeEnrollment;

        if (! $enrollment) {
            return collect();
        }

        return ClassSchedule::where('classroom_id', $enrollment->classroom_id)
            ->with('subjectAssignment.subject', 'subjectAssignment.teacher')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center gap-3">
        @if ($this->selectedStudent)
            <x-avatar-initials :initials="$this->selectedStudent->initials" :photo="$this->selectedStudent->photo" size="size-11" />
        @endif
        <div>
            <flux:heading size="xl">Mi Portal</flux:heading>
            <flux:subheading>Información académica de tu(s) hijo(s)</flux:subheading>
        </div>
    </div>

    @if ($this->children->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="user-group" class="mb-3 size-10 text-zinc-300" />
            <flux:heading>Sin estudiantes asociados</flux:heading>
            <flux:text class="text-zinc-500">Tu cuenta no tiene ningún estudiante vinculado todavía.</flux:text>
        </div>
    @else
        {{-- Selector de hijo(s) --}}
        @if ($this->children->count() > 1)
            <div class="flex gap-2">
                @foreach ($this->children as $child)
                    <flux:button
                        size="sm"
                        :variant="$selectedStudentId === $child->id ? 'primary' : 'ghost'"
                        wire:click="$set('selectedStudentId', {{ $child->id }})"
                    >
                        {{ $child->full_name }}
                    </flux:button>
                @endforeach
            </div>
        @endif

        @if (! $this->selectedStudent?->activeEnrollment)
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <flux:icon name="calendar" class="mb-3 size-10 text-zinc-300" />
                <flux:heading>Sin matrícula activa</flux:heading>
                <flux:text class="text-zinc-500">{{ $this->selectedStudent?->full_name }} no tiene una matrícula activa este año escolar.</flux:text>
            </div>
        @else
            @php $enrollment = $this->selectedStudent->activeEnrollment; @endphp

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- Aula y compañeros --}}
                <div class="space-y-6 lg:col-span-1">
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-3">
                        <flux:heading size="lg">Aula</flux:heading>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Grado</dt>
                                <dd class="font-medium">{{ $enrollment->classroom->grade->name }}-{{ $enrollment->classroom->section }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Nivel</dt>
                                <dd>{{ $enrollment->classroom->grade->educationLevel->name }}</dd>
                            </div>
                            <div class="flex justify-between items-center">
                                <dt class="text-zinc-500">Turno</dt>
                                <dd>
                                    @php $portalShift = Shift::from($enrollment->classroom->shift); @endphp
                                    <flux:badge size="sm" :color="$portalShift->color()" :icon="$portalShift->icon()">
                                        {{ $portalShift->labelWithTime() }}
                                    </flux:badge>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Año escolar</dt>
                                <dd>{{ $enrollment->academicYear->year }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-3">
                        <flux:heading size="lg">Compañeros de aula</flux:heading>
                        @forelse ($this->classmates as $classmate)
                            <div class="flex items-center gap-2 {{ ! $loop->last ? 'mb-2' : '' }}">
                                <x-avatar-initials :initials="$classmate->initials" :photo="$classmate->photo" size="size-7" />
                                <span class="text-sm">{{ $classmate->full_name }}</span>
                            </div>
                        @empty
                            <flux:text class="text-sm text-zinc-400">Sin otros compañeros registrados.</flux:text>
                        @endforelse
                    </div>
                </div>

                {{-- Notas --}}
                <div class="lg:col-span-2">
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                        <flux:heading size="lg">Notas por trimestre</flux:heading>

                        @if (empty($this->gradesMatrix))
                            <flux:text class="text-zinc-500">Aún no hay notas registradas para este año escolar.</flux:text>
                        @else
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>Materia</flux:table.column>
                                    @foreach ($enrollment->academicYear->periods as $period)
                                        <flux:table.column>{{ $period->name }}</flux:table.column>
                                    @endforeach
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($this->gradesMatrix as $subjectName => $periodScores)
                                        <flux:table.row>
                                            <flux:table.cell class="font-medium">{{ $subjectName }}</flux:table.cell>
                                            @foreach ($enrollment->academicYear->periods as $period)
                                                <flux:table.cell>{{ $periodScores[$period->id] ?? '—' }}</flux:table.cell>
                                            @endforeach
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Horario semanal --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <flux:heading size="lg">Horario semanal</flux:heading>

                @if ($this->scheduleByDay->isEmpty())
                    <flux:text class="text-zinc-500">Aún no hay un horario generado para esta aula.</flux:text>
                @else
                    @php
                        $dayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
                        $timeSlots = $this->scheduleByDay->flatten(1)->unique(fn ($s) => $s->start_time->format('H:i'))->sortBy('start_time');
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
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
            </div>
        @endif
    @endif

</div>
