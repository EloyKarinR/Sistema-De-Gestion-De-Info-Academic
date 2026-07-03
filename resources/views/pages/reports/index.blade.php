<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Reportes')] class extends Component
{
    public string $studentSearch = '';

    public string $classroomId = '';

    public string $previewUrl = '';

    public string $previewTitle = '';

    public function preview(string $url, string $title): void
    {
        $this->previewUrl = $url;
        $this->previewTitle = $title;

        Flux::modal('preview')->show();
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    #[Computed]
    public function students()
    {
        if (! $this->studentSearch) {
            return collect();
        }

        return Student::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.strtolower($this->studentSearch).'%'])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($this->studentSearch).'%'])
                    ->orWhere('cedula', 'LIKE', '%'.$this->studentSearch.'%');
            })
            ->with('activeEnrollment.classroom.grade')
            ->orderBy('last_name')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function classrooms()
    {
        if (! $this->activeYear) {
            return collect();
        }

        return Classroom::where('academic_year_id', $this->activeYear->id)
            ->with('grade.educationLevel')
            ->get()
            ->sortBy(fn ($c) => $c->grade->order);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl">Reportes</flux:heading>
        <flux:subheading>Boletines, constancias y listados en PDF</flux:subheading>
    </div>

    {{-- Boletín y Constancia --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
        <flux:heading size="lg">Boletín de notas / Constancia de matrícula</flux:heading>
        <flux:subheading>Busca un estudiante para descargar sus documentos.</flux:subheading>

        <flux:input
            wire:model.live.debounce.300ms="studentSearch"
            placeholder="Buscar por nombre o cédula..."
            icon="magnifying-glass"
            class="max-w-sm"
        />

        @if ($studentSearch)
            @if ($this->students->count())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Nombre</flux:table.column>
                        <flux:table.column>Cédula</flux:table.column>
                        <flux:table.column>Aula</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->students as $student)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $student->full_name }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $student->cedula ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($student->activeEnrollment)
                                        {{ $student->activeEnrollment->classroom->grade->name }}-{{ $student->activeEnrollment->classroom->section }}
                                    @else
                                        <span class="text-zinc-400">Sin matrícula</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex gap-2">
                                        @can('reports.print')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="document-text"
                                                wire:click="preview('{{ route('reports.boletin', $student) }}', 'Boletín de notas — {{ $student->full_name }}')"
                                            >
                                                Boletín
                                            </flux:button>
                                            @if ($student->activeEnrollment)
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="clipboard-document-check"
                                                    wire:click="preview('{{ route('reports.constancia', $student->activeEnrollment) }}', 'Constancia de matrícula — {{ $student->full_name }}')"
                                                >
                                                    Constancia
                                                </flux:button>
                                            @endif
                                        @else
                                            <flux:text class="text-sm text-zinc-400">Sin permiso para imprimir</flux:text>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text class="text-zinc-500">No se encontraron estudiantes con "{{ $studentSearch }}".</flux:text>
            @endif
        @endif
    </div>

    {{-- Listado por aula --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
        <flux:heading size="lg">Listado de estudiantes por aula</flux:heading>
        <flux:subheading>Para pegar en la puerta del salón o control de secretaría.</flux:subheading>

        @if (! $this->activeYear)
            <flux:text class="text-zinc-500">No hay un año escolar activo.</flux:text>
        @else
            <div class="flex items-end gap-2">
                <flux:select wire:model.live="classroomId" label="Aula" placeholder="Selecciona un aula" class="max-w-sm">
                    @foreach ($this->classrooms as $classroom)
                        <flux:select.option value="{{ $classroom->id }}">
                            {{ $classroom->grade->name }}-{{ $classroom->section }} ({{ $classroom->grade->educationLevel->name }})
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @can('reports.print')
                    @if ($classroomId)
                        <flux:button icon="printer" wire:click="preview('{{ route('reports.listado', $classroomId) }}', 'Listado de estudiantes')">
                            Ver listado
                        </flux:button>
                    @endif
                @endcan
            </div>
        @endif
    </div>

    {{-- Modal: Vista previa --}}
    <flux:modal name="preview" class="max-w-4xl">
        <flux:heading size="lg" class="mb-4">{{ $previewTitle }}</flux:heading>

        @if ($previewUrl)
            <iframe
                src="{{ $previewUrl }}"
                class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700"
                style="height: 75vh;"
            ></iframe>
        @endif
    </flux:modal>

</div>
