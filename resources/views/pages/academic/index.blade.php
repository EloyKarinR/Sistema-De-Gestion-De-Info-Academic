<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\Institution;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Académico')] class extends Component {

    // Formulario: nueva aula
    public string $gradeId  = '';
    public string $section  = '';
    public string $shift     = 'matutino';
    public int    $capacity  = 30;

    // Formulario: nuevo año escolar
    public int    $newYear   = 0;
    public string $startDate = '';
    public string $endDate   = '';

    public function mount(): void
    {
        $this->newYear   = now()->year;
        $this->startDate = now()->startOfYear()->format('Y-m-d');
        $this->endDate   = now()->endOfYear()->format('Y-m-d');
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)
            ->with(['periods', 'classrooms.grade.educationLevel'])
            ->first();
    }

    #[Computed]
    public function grades()
    {
        $institution = Institution::first();

        return Grade::whereHas('educationLevel', fn ($q) =>
            $q->where('institution_type', $institution?->type ?? 'escuela')
        )
        ->with('educationLevel')
        ->orderBy('order')
        ->get()
        ->groupBy(fn ($g) => $g->educationLevel->name);
    }

    public function addClassroom(): void
    {
        $this->validate([
            'gradeId'  => 'required|exists:grades,id',
            'section'  => 'required|string|max:1|regex:/^[a-zA-Z]$/',
            'shift'    => 'required|in:matutino,vespertino,nocturno',
            'capacity' => 'required|integer|min:1|max:60',
        ], [
            'section.regex' => 'La sección debe ser una sola letra (A-Z).',
        ]);

        $year = AcademicYear::where('is_active', true)->first();

        if (! $year) {
            Flux::toast(variant: 'danger', text: 'No hay un año escolar activo.');
            return;
        }

        $sectionUpper = strtoupper($this->section);

        $exists = Classroom::where('academic_year_id', $year->id)
            ->where('grade_id', $this->gradeId)
            ->where('section', $sectionUpper)
            ->exists();

        if ($exists) {
            $this->addError('section', 'Ya existe esa sección para este grado en el año activo.');
            return;
        }

        Classroom::create([
            'academic_year_id' => $year->id,
            'grade_id'         => $this->gradeId,
            'section'          => $sectionUpper,
            'shift'            => $this->shift,
            'capacity'         => $this->capacity,
        ]);

        $this->reset(['gradeId', 'section']);
        $this->shift    = 'matutino';
        $this->capacity = 30;

        Flux::modal('add-classroom')->close();
        Flux::toast(variant: 'success', text: 'Aula creada correctamente.');

        unset($this->activeYear);
    }

    public function deleteClassroom(int $id): void
    {
        $classroom = Classroom::withCount(['enrollments' => fn ($q) => $q->where('status', 'activo')])->findOrFail($id);

        if ($classroom->enrollments_count > 0) {
            Flux::toast(variant: 'danger', text: 'No se puede eliminar un aula con estudiantes activos.');
            return;
        }

        $classroom->delete();

        unset($this->activeYear);
        Flux::toast(variant: 'success', text: 'Aula eliminada.');
    }

    public function createYear(): void
    {
        $this->validate([
            'newYear'   => 'required|integer|min:2020|max:2099',
            'startDate' => 'required|date',
            'endDate'   => 'required|date|after:startDate',
        ]);

        $institution = Institution::first();

        if (! $institution) {
            Flux::toast(variant: 'danger', text: 'Configura los datos de la institución primero.');
            return;
        }

        AcademicYear::where('is_active', true)->update(['is_active' => false]);

        $start = \Carbon\Carbon::parse($this->startDate);
        $end   = \Carbon\Carbon::parse($this->endDate);
        $days  = (int) ($start->diffInDays($end) / 3);

        $year = AcademicYear::create([
            'institution_id' => $institution->id,
            'year'           => $this->newYear,
            'start_date'     => $this->startDate,
            'end_date'       => $this->endDate,
            'is_active'      => true,
        ]);

        $year->periods()->createMany([
            ['number' => 1, 'name' => 'I Trimestre',   'start_date' => $start->toDateString(),                          'end_date' => $start->copy()->addDays($days)->toDateString()],
            ['number' => 2, 'name' => 'II Trimestre',  'start_date' => $start->copy()->addDays($days + 1)->toDateString(), 'end_date' => $start->copy()->addDays($days * 2)->toDateString()],
            ['number' => 3, 'name' => 'III Trimestre', 'start_date' => $start->copy()->addDays($days * 2 + 1)->toDateString(), 'end_date' => $end->toDateString()],
        ]);

        unset($this->activeYear);
        Flux::modal('create-year')->close();
        Flux::toast(variant: 'success', text: "Año escolar {$this->newYear} creado y activado.");
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Académico</flux:heading>
            <flux:subheading>Gestión de años escolares y aulas</flux:subheading>
        </div>
    </div>

    {{-- Año Escolar Activo --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Año Escolar Activo</flux:heading>
            <flux:modal.trigger name="create-year">
                <flux:button icon="plus" size="sm">Nuevo año</flux:button>
            </flux:modal.trigger>
        </div>

        @if ($this->activeYear)
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-4">
                    <flux:badge color="green" size="lg">{{ $this->activeYear->year }}</flux:badge>
                    <flux:text class="text-sm text-zinc-500">
                        {{ $this->activeYear->start_date->format('d/m/Y') }} — {{ $this->activeYear->end_date->format('d/m/Y') }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->activeYear->periods as $period)
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 text-sm">
                            <span class="font-medium">{{ $period->name }}</span>
                            <span class="ml-2 text-zinc-500">
                                {{ $period->start_date->format('d/m') }} — {{ $period->end_date->format('d/m') }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <flux:text class="text-zinc-500">No hay un año escolar activo. Crea uno para comenzar.</flux:text>
        @endif
    </div>

    {{-- Aulas --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Aulas</flux:heading>
            @if ($this->activeYear)
                <flux:modal.trigger name="add-classroom">
                    <flux:button icon="plus" size="sm" variant="primary">Agregar aula</flux:button>
                </flux:modal.trigger>
            @endif
        </div>

        @if ($this->activeYear && $this->activeYear->classrooms->count())
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Grado</flux:table.column>
                    <flux:table.column>Sección</flux:table.column>
                    <flux:table.column>Nivel</flux:table.column>
                    <flux:table.column>Turno</flux:table.column>
                    <flux:table.column>Capacidad</flux:table.column>
                    <flux:table.column>Matriculados</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->activeYear->classrooms->sortBy(fn($c) => $c->grade->order) as $classroom)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $classroom->grade->name }}</flux:table.cell>
                            <flux:table.cell>{{ $classroom->section }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" color="zinc">{{ $classroom->grade->educationLevel->name }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="capitalize">{{ $classroom->shift }}</flux:table.cell>
                            <flux:table.cell>{{ $classroom->capacity }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $classroom->enrollments->where('status', 'activo')->count() }} / {{ $classroom->capacity }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    icon="trash"
                                    size="sm"
                                    variant="ghost"
                                    wire:click="deleteClassroom({{ $classroom->id }})"
                                    wire:confirm="¿Eliminar este aula?"
                                />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @elseif ($this->activeYear)
            <flux:text class="text-zinc-500">No hay aulas registradas para este año. Agrega la primera.</flux:text>
        @else
            <flux:text class="text-zinc-500">Crea un año escolar para poder gestionar aulas.</flux:text>
        @endif
    </div>

    {{-- Modal: Agregar Aula --}}
    <flux:modal name="add-classroom" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Agregar aula</flux:heading>

        <div class="space-y-4">
            <flux:select wire:model="gradeId" label="Grado" placeholder="Selecciona un grado">
                @foreach ($this->grades as $levelName => $gradeList)
                    <flux:select.option disabled value="">— {{ $levelName }} —</flux:select.option>
                    @foreach ($gradeList as $grade)
                        <flux:select.option value="{{ $grade->id }}">{{ $grade->name }}</flux:select.option>
                    @endforeach
                @endforeach
            </flux:select>
            @error('gradeId') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="section"
                    label="Sección"
                    placeholder="A"
                    maxlength="1"
                    class="uppercase"
                />
                @error('section') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:select wire:model="shift" label="Turno">
                    <flux:select.option value="matutino">Matutino</flux:select.option>
                    <flux:select.option value="vespertino">Vespertino</flux:select.option>
                    <flux:select.option value="nocturno">Nocturno</flux:select.option>
                </flux:select>
            </div>

            <flux:input
                wire:model="capacity"
                label="Capacidad"
                type="number"
                min="1"
                max="60"
            />
            @error('capacity') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="addClassroom">Guardar</flux:button>
        </div>
    </flux:modal>

    {{-- Modal: Nuevo Año Escolar --}}
    <flux:modal name="create-year" class="max-w-md">
        <flux:heading size="lg" class="mb-1">Nuevo año escolar</flux:heading>
        <flux:subheading class="mb-4">El año actual quedará como inactivo.</flux:subheading>

        <div class="space-y-4">
            <flux:input
                wire:model="newYear"
                label="Año"
                type="number"
                min="2020"
                max="2099"
            />
            @error('newYear') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="grid grid-cols-2 gap-4">
                <flux:input
                    wire:model="startDate"
                    label="Fecha inicio"
                    type="date"
                />
                @error('startDate') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:input
                    wire:model="endDate"
                    label="Fecha fin"
                    type="date"
                />
                @error('endDate') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <flux:text class="text-xs text-zinc-500">Los tres trimestres se crearán automáticamente dividiendo el período en partes iguales.</flux:text>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="createYear">Crear y activar</flux:button>
        </div>
    </flux:modal>

</div>
