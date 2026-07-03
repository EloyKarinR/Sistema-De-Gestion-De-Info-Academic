<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    #[Computed]
    public function studentsCount(): int
    {
        return Student::count();
    }

    #[Computed]
    public function activeEnrollmentsCount(): int
    {
        if (! $this->activeYear) {
            return 0;
        }

        return Enrollment::where('academic_year_id', $this->activeYear->id)
            ->where('status', 'activo')
            ->count();
    }

    #[Computed]
    public function teachersCount(): int
    {
        return Teacher::count();
    }

    #[Computed]
    public function classrooms()
    {
        if (! $this->activeYear) {
            return collect();
        }

        return Classroom::where('academic_year_id', $this->activeYear->id)
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'activo')])
            ->with('grade.educationLevel')
            ->get()
            ->sortBy(fn ($c) => $c->grade->order);
    }

    #[Computed]
    public function recentEnrollments()
    {
        if (! $this->activeYear) {
            return collect();
        }

        return Enrollment::where('academic_year_id', $this->activeYear->id)
            ->with(['student', 'classroom.grade'])
            ->latest()
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function myTeacher(): ?Teacher
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
            ->with(['classroom.grade', 'subject'])
            ->get();
    }

    #[Computed]
    public function myChildren()
    {
        $guardian = Auth::user()->guardian;

        return $guardian
            ?->students()
            ->with('activeEnrollment.classroom.grade')
            ->get() ?? collect();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    <div>
        <flux:heading size="xl">Dashboard</flux:heading>
        <flux:subheading>
            @if ($this->activeYear)
                Año escolar {{ $this->activeYear->year }}
            @else
                Sin año escolar activo
            @endif
        </flux:subheading>
    </div>

    @cannot('academic.view')
        @can('portal.view')
            {{-- Acudiente --}}
            @forelse ($this->myChildren as $child)
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-3">
                    <flux:heading size="lg">{{ $child->full_name }}</flux:heading>
                    @if ($child->activeEnrollment)
                        <flux:text class="text-zinc-500">
                            {{ $child->activeEnrollment->classroom->grade->name }}-{{ $child->activeEnrollment->classroom->section }}
                        </flux:text>
                    @else
                        <flux:text class="text-zinc-400">Sin matrícula activa este año.</flux:text>
                    @endif
                    <flux:button size="sm" icon="home-modern" :href="route('portal.index')" wire:navigate>
                        Ver Mi Portal
                    </flux:button>
                </div>
            @empty
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                    <flux:text class="text-zinc-500">Tu cuenta no tiene ningún estudiante vinculado todavía.</flux:text>
                </div>
            @endforelse
        @endcan
    @endcannot

    @can('academic.view')
        @if (! $this->activeYear)
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <flux:icon name="calendar" class="mb-3 size-10 text-zinc-300" />
                <flux:heading>Sin año escolar activo</flux:heading>
                <flux:text class="text-zinc-500">Crea uno desde Académico para empezar a ver datos aquí.</flux:text>
            </div>
        @else
            {{-- Tarjetas de resumen --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                @can('student.view')
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                        <flux:text class="text-zinc-500 text-sm">Estudiantes</flux:text>
                        <flux:heading size="xl">{{ $this->studentsCount }}</flux:heading>
                    </div>
                @endcan
                @can('enrollment.view')
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                        <flux:text class="text-zinc-500 text-sm">Matrículas activas</flux:text>
                        <flux:heading size="xl">{{ $this->activeEnrollmentsCount }}</flux:heading>
                    </div>
                @endcan
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                    <flux:text class="text-zinc-500 text-sm">Aulas</flux:text>
                    <flux:heading size="xl">{{ $this->classrooms->count() }}</flux:heading>
                </div>
                @can('teacher.view')
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                        <flux:text class="text-zinc-500 text-sm">Docentes</flux:text>
                        <flux:heading size="xl">{{ $this->teachersCount }}</flux:heading>
                    </div>
                @endcan
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Cupos por aula --}}
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-3">
                    <flux:heading size="lg">Cupos por aula</flux:heading>
                    @forelse ($this->classrooms as $classroom)
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $classroom->grade->name }}-{{ $classroom->section }}</span>
                            <span class="text-zinc-500">{{ $classroom->enrollments_count }} / {{ $classroom->capacity }}</span>
                        </div>
                    @empty
                        <flux:text class="text-zinc-400 text-sm">No hay aulas registradas.</flux:text>
                    @endforelse
                </div>

                {{-- Matrículas recientes --}}
                @can('enrollment.view')
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-3">
                        <flux:heading size="lg">Matrículas recientes</flux:heading>
                        @forelse ($this->recentEnrollments as $enrollment)
                            <div class="flex items-center justify-between text-sm">
                                <span>{{ $enrollment->student->full_name }}</span>
                                <span class="text-zinc-500">{{ $enrollment->classroom->grade->name }}-{{ $enrollment->classroom->section }}</span>
                            </div>
                        @empty
                            <flux:text class="text-zinc-400 text-sm">Aún no hay matrículas este año.</flux:text>
                        @endforelse
                    </div>
                @endcan
            </div>
        @endif
    @endcan

    @if ($this->myTeacher)
        {{-- Docente --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-3">
            <flux:heading size="lg">Tus aulas asignadas</flux:heading>
            @forelse ($this->myAssignments as $assignment)
                <div class="flex items-center justify-between text-sm">
                    <span>
                        {{ $assignment->classroom->grade->name }}-{{ $assignment->classroom->section }}
                        — {{ $assignment->subject->name }}
                    </span>
                    <flux:button size="sm" variant="ghost" :href="route('scores.index')" wire:navigate>
                        Ir a Notas
                    </flux:button>
                </div>
            @empty
                <flux:text class="text-zinc-400 text-sm">Aún no tienes materias asignadas. Pídele al administrador que te asigne una desde Docentes.</flux:text>
            @endforelse
        </div>
    @endif

</div>
