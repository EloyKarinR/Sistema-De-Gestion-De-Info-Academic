<?php

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
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl">Mi Portal</flux:heading>
        <flux:subheading>Información académica de tu(s) hijo(s)</flux:subheading>
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
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Turno</dt>
                                <dd class="capitalize">{{ $enrollment->classroom->shift }}</dd>
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
                            <flux:text class="block text-sm {{ ! $loop->last ? 'mb-1' : '' }}">
                                {{ $classmate->full_name }}
                            </flux:text>
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
        @endif
    @endif

</div>
