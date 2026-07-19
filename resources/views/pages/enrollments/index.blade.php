<?php

use App\Models\AcademicYear;
use App\Models\Enrollment;
use Flux\Flux;
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

    public ?int $statusEnrollmentId = null;

    public string $newStatus = 'retirado';

    public string $statusDate = '';

    public string $statusReason = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openStatusModal(int $enrollmentId, string $status): void
    {
        $this->authorize('enrollment.edit');

        $this->statusEnrollmentId = $enrollmentId;
        $this->newStatus = $status;
        $this->statusDate = now()->format('Y-m-d');
        $this->statusReason = '';

        Flux::modal('change-status')->show();
    }

    #[Computed]
    public function statusEnrollment(): ?Enrollment
    {
        return $this->statusEnrollmentId ? Enrollment::with('student')->find($this->statusEnrollmentId) : null;
    }

    public function updateStatus(): void
    {
        $this->authorize('enrollment.edit');

        $this->validate([
            'statusDate' => 'required|date',
            'statusReason' => 'nullable|string|max:255',
        ]);

        $enrollment = Enrollment::findOrFail($this->statusEnrollmentId);

        abort_unless($enrollment->status === 'activo', 403, 'Solo se puede cambiar el estado de una matrícula activa.');

        $enrollment->update([
            'status' => $this->newStatus,
            'status_date' => $this->statusDate,
            'status_reason' => $this->statusReason ?: null,
        ]);

        Flux::modal('change-status')->close();
        Flux::toast(
            variant: 'success',
            text: $this->newStatus === 'retirado' ? 'Estudiante retirado correctamente.' : 'Estudiante marcado como trasladado.'
        );

        unset($this->enrollments);
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
    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
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
                            <flux:table.cell>
                                <div class="flex items-center gap-3">
                                    <x-avatar-initials :initials="$enrollment->student->initials" :photo="$enrollment->student->photo" />
                                    <span class="font-medium">{{ $enrollment->student->full_name }}</span>
                                </div>
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
                                @if ($enrollment->status !== 'activo' && $enrollment->status_date)
                                    <flux:text class="block text-xs text-zinc-400 mt-0.5">
                                        {{ $enrollment->status_date->format('d/m/Y') }}
                                    </flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-1.5 {{ $docsCount === 5 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }} text-sm font-medium">
                                    <flux:icon :icon="$docsCount === 5 ? 'document-check' : 'document-text'" class="size-4" />
                                    {{ $docsCount }}/5
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-1">
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="eye"
                                        :href="route('students.show', $enrollment->student)"
                                        wire:navigate
                                    />
                                    @can('enrollment.edit')
                                        @if ($enrollment->status === 'activo')
                                            <flux:dropdown>
                                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                                <flux:menu>
                                                    <flux:menu.item
                                                        icon="user-minus"
                                                        wire:click="openStatusModal({{ $enrollment->id }}, 'retirado')"
                                                    >
                                                        Retirar
                                                    </flux:menu.item>
                                                    <flux:menu.item
                                                        icon="arrow-right-circle"
                                                        wire:click="openStatusModal({{ $enrollment->id }}, 'trasladado')"
                                                    >
                                                        Marcar como trasladado
                                                    </flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        @endif
                                    @endcan
                                </div>
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

    {{-- Modal: Cambiar estado (retirar / trasladar) --}}
    <flux:modal name="change-status" class="max-w-md">
        <flux:heading size="lg" class="mb-1">
            {{ $newStatus === 'retirado' ? 'Retirar estudiante' : 'Marcar como trasladado' }}
        </flux:heading>
        <flux:subheading class="mb-4">
            {{ $this->statusEnrollment?->student->full_name }}
        </flux:subheading>

        <div class="space-y-4">
            <flux:input wire:model="statusDate" label="Fecha" type="date" required />
            @error('statusDate') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:textarea wire:model="statusReason" label="Motivo (opcional)" placeholder="Se traslada a otro colegio, se muda de ciudad..." rows="2" />
            @error('statusReason') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="updateStatus" wire:loading.attr="disabled">
                Confirmar
            </flux:button>
        </div>
    </flux:modal>

</div>
