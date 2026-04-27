<?php

use App\Models\Student;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Estudiantes')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function students()
    {
        return Student::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . strtolower($this->search) . '%'])
                      ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . strtolower($this->search) . '%'])
                      ->orWhere('cedula', 'LIKE', '%' . $this->search . '%');
                });
            })
            ->with([
                'activeEnrollment.classroom.grade',
                'guardians' => fn ($q) => $q->wherePivot('is_primary', true),
            ])
            ->orderBy('last_name')
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

    {{-- Búsqueda --}}
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Buscar por nombre o cédula..."
        icon="magnifying-glass"
        class="max-w-sm"
    />

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
                        <flux:table.cell class="font-medium">
                            {{ $student->full_name }}
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
