<?php

use App\Enums\Shift;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Estudiantes')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'aula')]
    public string $classroomId = '';

    #[Url(as: 'turno')]
    public string $shiftFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClassroomId(): void
    {
        $this->resetPage();
    }

    public function updatedShiftFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    #[Computed]
    public function classroomsForFilter()
    {
        if (! $this->activeYear) {
            return collect();
        }

        return Classroom::where('academic_year_id', $this->activeYear->id)
            ->with('grade.educationLevel')
            ->get()
            ->sortBy(fn ($c) => $c->grade->order);
    }

    #[Computed]
    public function students()
    {
        return Student::query()
            ->select('students.*')
            ->leftJoin('enrollments', function ($join) {
                $join->on('enrollments.student_id', '=', 'students.id')
                    ->where('enrollments.status', '=', 'activo');
            })
            ->leftJoin('classrooms', 'classrooms.id', '=', 'enrollments.classroom_id')
            ->leftJoin('grades', 'grades.id', '=', 'classrooms.grade_id')
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->whereRaw('LOWER(students.first_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhereRaw('LOWER(students.last_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhere('students.cedula', 'LIKE', '%'.$this->search.'%');
                });
            })
            ->when($this->classroomId, fn ($q) => $q->where('enrollments.classroom_id', $this->classroomId))
            ->when($this->shiftFilter, fn ($q) => $q->where('classrooms.shift', $this->shiftFilter))
            ->with([
                'activeEnrollment.classroom.grade',
                'guardians' => fn ($q) => $q->wherePivot('is_primary', true),
            ])
            ->orderByRaw('grades."order" asc nulls last')
            ->orderBy('classrooms.section')
            ->orderBy('students.last_name')
            ->paginate(15);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Estudiantes</flux:heading>
            <flux:subheading>Listado de estudiantes registrados</flux:subheading>
        </div>
    </div>

    {{-- Búsqueda y filtro --}}
    <div class="flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o cédula..."
            icon="magnifying-glass"
            class="max-w-sm"
        />

        <flux:select wire:model.live="classroomId" placeholder="Todas las aulas" class="max-w-xs">
            <flux:select.option value="">Todas las aulas</flux:select.option>
            @foreach ($this->classroomsForFilter as $classroom)
                <flux:select.option value="{{ $classroom->id }}">
                    {{ $classroom->grade->name }}-{{ $classroom->section }} ({{ $classroom->grade->educationLevel->name }})
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="shiftFilter" placeholder="Todos los turnos" class="max-w-xs">
            <flux:select.option value="">Todos los turnos</flux:select.option>
            @foreach (Shift::cases() as $shiftOption)
                <flux:select.option value="{{ $shiftOption->value }}">{{ $shiftOption->labelWithTime() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Tabla --}}
    @if ($this->students->count())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Cédula</flux:table.column>
                <flux:table.column>Grado actual</flux:table.column>
                <flux:table.column>Acudiente</flux:table.column>
                <flux:table.column>Estado</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->students as $student)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <x-avatar-initials :initials="$student->initials" />
                                <span class="font-medium">{{ $student->full_name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ $student->cedula ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($student->activeEnrollment?->classroom)
                                {{ $student->activeEnrollment->classroom->grade->name }}-{{ $student->activeEnrollment->classroom->section }}
                            @else
                                <span class="text-zinc-400">Sin matrícula</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $student->guardians->first()?->full_name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($student->activeEnrollment)
                                <flux:badge color="green" size="sm">Activo</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Sin matrícula</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                :href="route('students.show', $student)"
                                wire:navigate
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-2">
            {{ $this->students->links() }}
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="users" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:heading>Sin resultados</flux:heading>
            <flux:text class="text-zinc-500">
                @if ($this->search)
                    No se encontraron estudiantes con "{{ $this->search }}".
                @else
                    Aún no hay estudiantes registrados.
                @endif
            </flux:text>
        </div>
    @endif

</div>
