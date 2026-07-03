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
                    <div class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400">
                            <flux:icon name="users" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="leading-tight">{{ $this->studentsCount }}</flux:heading>
                            <flux:text class="text-zinc-500 text-sm">Estudiantes</flux:text>
                        </div>
                    </div>
                @endcan
                @can('enrollment.view')
                    <div class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/50 dark:text-teal-400">
                            <flux:icon name="clipboard-document-list" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="leading-tight">{{ $this->activeEnrollmentsCount }}</flux:heading>
                            <flux:text class="text-zinc-500 text-sm">Matrículas activas</flux:text>
                        </div>
                    </div>
                @endcan
                <div class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-400">
                        <flux:icon name="building-office-2" class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="xl" class="leading-tight">{{ $this->classrooms->count() }}</flux:heading>
                        <flux:text class="text-zinc-500 text-sm">Aulas</flux:text>
                    </div>
                </div>
                @can('teacher.view')
                    <div class="flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-orange-50 text-orange-600 dark:bg-orange-950/50 dark:text-orange-400">
                            <flux:icon name="identification" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="leading-tight">{{ $this->teachersCount }}</flux:heading>
                            <flux:text class="text-zinc-500 text-sm">Docentes</flux:text>
                        </div>
                    </div>
                @endcan
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Cupos por aula --}}
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                    <flux:heading size="lg">Cupos por aula</flux:heading>
                    @forelse ($this->classrooms as $classroom)
                        @php
                            $percentage = $classroom->capacity > 0
                                ? min(100, round(($classroom->enrollments_count / $classroom->capacity) * 100))
                                : 0;
                            $colors = $this->meterClasses($classroom->enrollments_count, $classroom->capacity);
                        @endphp
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium">
                                    {{ $classroom->grade->name }}-{{ $classroom->section }}
                                    <span class="text-zinc-400 font-normal">({{ $classroom->grade->educationLevel->name }})</span>
                                </span>
                                <span class="text-zinc-500 tabular-nums">
                                    {{ $classroom->enrollments_count }} / {{ $classroom->capacity }} · {{ $percentage }}%
                                </span>
                            </div>
                            <div class="h-2 w-full rounded-full {{ $colors['track'] }} overflow-hidden">
                                <div class="h-full rounded-full {{ $colors['fill'] }}" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @empty
                        <flux:text class="text-zinc-400 text-sm">No hay aulas registradas.</flux:text>
                    @endforelse
                </div>

                {{-- Matrículas recientes --}}
                @can('enrollment.view')
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                        <flux:heading size="lg">Matrículas recientes</flux:heading>
                        @forelse ($this->recentEnrollments as $enrollment)
                            @php
                                $student = $enrollment->student;
                                $initials = mb_strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name, 0, 1));
                            @endphp
                            <div class="flex items-center gap-3">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                    {{ $initials }}
                                </div>
                                <div class="flex flex-1 items-center justify-between text-sm min-w-0">
                                    <span class="truncate">{{ $student->full_name }}</span>
                                    <flux:badge size="sm" color="zinc">{{ $enrollment->classroom->grade->name }}-{{ $enrollment->classroom->section }}</flux:badge>
                                </div>
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
