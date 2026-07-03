<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Matrículas')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    #[Computed]
    public function enrollments()
    {
        if (! $this->activeYear) {
            return null;
        }

        return Enrollment::where('academic_year_id', $this->activeYear->id)
            ->when($this->search, function ($q) {
                $q->whereHas('student', function ($q) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhere('cedula', 'LIKE', '%'.$this->search.'%');
                });
            })
            ->with([
                'student',
                'classroom.grade',
                'registeredBy',
            ])
            ->orderByDesc('enrollment_date')
            ->paginate(15);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Matrículas</flux:heading>
            <flux:subheading>
                @if ($this->activeYear)
                    Año escolar {{ $this->activeYear->year }}
                @else
                    Sin año escolar activo
                @endif
            </flux:subheading>
        </div>
        @can('enrollment.create')
            @if ($this->activeYear)
                <flux:button
                    variant="primary"
                    icon="plus"
                    :href="route('enrollments.create')"
                    wire:navigate
                >
                    Nueva matrícula
                </flux:button>
            @endif
        @endcan
    </div>

    @if (! $this->activeYear)
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="calendar" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:heading>Sin año escolar activo</flux:heading>
            <flux:text class="text-zinc-500">Activa un año escolar en el módulo Académico para gestionar matrículas.</flux:text>
        </div>
    @else
        {{-- Búsqueda --}}
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Buscar por nombre o cédula..."
            icon="magnifying-glass"
            class="max-w-sm"
        />

        @if ($this->enrollments && $this->enrollments->count())
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Estudiante</flux:table.column>
                    <flux:table.column>Cédula</flux:table.column>
                    <flux:table.column>Aula</flux:table.column>
                    <flux:table.column>Tipo</flux:table.column>
                    <flux:table.column>Fecha</flux:table.column>
                    <flux:table.column>Estado</flux:table.column>
                    <flux:table.column>Docs</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->enrollments as $enrollment)
                        @php
                            $docs = collect([
                                $enrollment->doc_cedula_student,
                                $enrollment->doc_cedula_guardian,
                                $enrollment->doc_boletin,
                                $enrollment->doc_foto,
                                $enrollment->doc_address,
                            ]);
                            $docsCount = $docs->filter()->count();
                        @endphp
                        <flux:table.row>
                            <flux:table.cell class="font-medium">
                                {{ $enrollment->student->full_name }}
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">
                                {{ $enrollment->student->cedula ?? '—' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $enrollment->classroom->grade->name }}-{{ $enrollment->classroom->section }}
                            </flux:table.cell>
                            <flux:table.cell class="capitalize text-sm">
                                {{ str_replace('_', ' ', $enrollment->enrollment_type) }}
                            </flux:table.cell>
                            <flux:table.cell class="text-sm">
                                {{ $enrollment->enrollment_date->format('d/m/Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @php
                                    $color = match($enrollment->status) {
                                        'activo'     => 'green',
                                        'retirado'   => 'red',
                                        'trasladado' => 'yellow',
                                        default      => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$color" class="capitalize">
                                    {{ $enrollment->status }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="{{ $docsCount === 5 ? 'text-green-600' : 'text-yellow-600' }} text-sm font-medium">
                                    {{ $docsCount }}/5
                                </span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="eye"
                                    :href="route('students.show', $enrollment->student)"
                                    wire:navigate
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-2">
                {{ $this->enrollments->links() }}
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <flux:icon name="clipboard-document-list" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                <flux:heading>Sin matrículas</flux:heading>
                <flux:text class="text-zinc-500">
                    @if ($this->search)
                        No se encontraron resultados para "{{ $this->search }}".
                    @else
                        Aún no hay matrículas para este año escolar.
                    @endif
                </flux:text>
            </div>
        @endif
    @endif

</div>
