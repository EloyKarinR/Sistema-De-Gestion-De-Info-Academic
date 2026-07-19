<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Promoción de estudiantes')] class extends Component
{
    public ?int $sourceYearId = null;

    public ?int $targetYearId = null;

    public function mount(): void
    {
        $active = AcademicYear::where('is_active', true)->first();
        $this->targetYearId = $active?->id;

        $this->sourceYearId = AcademicYear::where('id', '!=', $active?->id)
            ->orderByDesc('year')
            ->value('id');
    }

    #[Computed]
    public function years()
    {
        return AcademicYear::orderByDesc('year')->get();
    }

    #[Computed]
    public function sourceYear(): ?AcademicYear
    {
        return $this->sourceYearId ? AcademicYear::find($this->sourceYearId) : null;
    }

    #[Computed]
    public function targetYear(): ?AcademicYear
    {
        return $this->targetYearId ? AcademicYear::find($this->targetYearId) : null;
    }

    /**
     * Clasifica cada matrícula activa del año de origen en un balde según lo
     * que le pasaría al promoverla: promovida, egresa (no hay grado
     * siguiente), no alcanza la nota mínima (solo secundaria), sin aula
     * destino, sin cupo, edad fuera de rango, o ya promovida antes (para que
     * repetir la operación sea seguro/idempotente).
     */
    #[Computed]
    public function preview(): array
    {
        $result = [
            'promote' => collect(),
            'already_done' => collect(),
            'graduate' => collect(),
            'below_minimum' => collect(),
            'no_classroom' => collect(),
            'no_capacity' => collect(),
            'age_mismatch' => collect(),
        ];

        if (! $this->sourceYear || ! $this->targetYear || $this->sourceYearId === $this->targetYearId) {
            return $result;
        }

        $alreadyPromotedStudentIds = Enrollment::where('academic_year_id', $this->targetYearId)
            ->where('status', 'activo')
            ->pluck('student_id');

        $activeEnrollments = Enrollment::where('academic_year_id', $this->sourceYearId)
            ->where('status', 'activo')
            ->with(['student', 'classroom.grade.educationLevel'])
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name);

        $targetClassrooms = Classroom::where('academic_year_id', $this->targetYearId)
            ->withCount(['enrollments as active_count' => fn ($q) => $q->where('status', 'activo')])
            ->with('grade')
            ->get();

        foreach ($activeEnrollments as $enrollment) {
            if ($alreadyPromotedStudentIds->contains($enrollment->student_id)) {
                $result['already_done']->push($enrollment);

                continue;
            }

            $nextGrade = $enrollment->classroom->grade->next();

            if (! $nextGrade) {
                $result['graduate']->push($enrollment);

                continue;
            }

            if ($enrollment->classroom->grade->isSecondary()) {
                $average = $enrollment->finalAverage();

                if ($average === null || $average < Enrollment::MINIMUM_PASSING_AVERAGE) {
                    $result['below_minimum']->push(['enrollment' => $enrollment, 'next_grade' => $nextGrade, 'average' => $average]);

                    continue;
                }
            }

            $candidates = $targetClassrooms
                ->where('grade_id', $nextGrade->id)
                ->where('shift', $enrollment->classroom->shift);

            if ($candidates->isEmpty()) {
                $result['no_classroom']->push(['enrollment' => $enrollment, 'next_grade' => $nextGrade]);

                continue;
            }

            if (! $nextGrade->acceptsAge($enrollment->student->age)) {
                $result['age_mismatch']->push(['enrollment' => $enrollment, 'next_grade' => $nextGrade]);

                continue;
            }

            $withRoom = $candidates->filter(fn ($c) => ($c->capacity - $c->active_count) > 0);

            if ($withRoom->isEmpty()) {
                $result['no_capacity']->push(['enrollment' => $enrollment, 'next_grade' => $nextGrade]);

                continue;
            }

            $target = $withRoom->firstWhere('section', $enrollment->classroom->section)
                ?? $withRoom->sortByDesc(fn ($c) => $c->capacity - $c->active_count)->first();

            $result['promote']->push([
                'enrollment' => $enrollment,
                'next_grade' => $nextGrade,
                'target_classroom' => $target,
            ]);

            // Refleja el cupo tomado para que el resto de la vista previa no
            // asuma que el mismo puesto sigue libre para el siguiente estudiante.
            $target->active_count++;
        }

        return $result;
    }

    public function confirmPromotion(): void
    {
        $this->authorize('academic.manage');

        $toPromote = $this->preview['promote'];

        abort_if($toPromote->isEmpty(), 400, 'No hay estudiantes para promover.');

        DB::transaction(function () use ($toPromote) {
            foreach ($toPromote as $row) {
                $enrollment = Enrollment::create([
                    'student_id' => $row['enrollment']->student_id,
                    'classroom_id' => $row['target_classroom']->id,
                    'academic_year_id' => $this->targetYearId,
                    'registered_by' => Auth::id(),
                    'enrollment_date' => $this->targetYear->start_date,
                    'status' => 'activo',
                    'enrollment_type' => 'promovido',
                    'doc_cedula_student' => true,
                    'doc_cedula_guardian' => true,
                    'doc_boletin' => true,
                    'doc_foto' => true,
                    'doc_address' => true,
                ]);

                $enrollment->update([
                    'receipt_number' => 'MAT-'.$this->targetYear->year.'-'.str_pad($enrollment->id, 4, '0', STR_PAD_LEFT),
                ]);
            }
        });

        $count = $toPromote->count();

        unset($this->preview);

        Flux::toast(variant: 'success', text: "{$count} estudiante(s) promovido(s) correctamente.");
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center gap-3">
        <flux:button icon="arrow-left" variant="ghost" size="sm" :href="route('academic.index')" wire:navigate />
        <div>
            <flux:heading size="xl">Promoción de estudiantes</flux:heading>
            <flux:subheading>Pasa en bloque a los estudiantes activos de un año al siguiente grado</flux:subheading>
        </div>
    </div>

    {{-- Selección de años --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:select wire:model.live="sourceYearId" label="Año de origen" placeholder="Selecciona un año">
                @foreach ($this->years as $year)
                    <flux:select.option value="{{ $year->id }}">{{ $year->year }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="targetYearId" label="Año destino" placeholder="Selecciona un año">
                @foreach ($this->years as $year)
                    <flux:select.option value="{{ $year->id }}">{{ $year->year }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->sourceYearId && $this->sourceYearId === $this->targetYearId)
            <flux:text class="text-amber-600 dark:text-amber-400 text-sm">El año de origen y destino no pueden ser el mismo.</flux:text>
        @endif
    </div>

    @if ($this->sourceYear && $this->targetYear && $this->sourceYearId !== $this->targetYearId)
        @php $preview = $this->preview; @endphp

        {{-- Resumen --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <flux:heading size="xl" class="leading-tight text-green-600 dark:text-green-400">{{ $preview['promote']->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm">Listos para promover</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <flux:heading size="xl" class="leading-tight">{{ $preview['graduate']->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm">Egresan</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <flux:heading size="xl" class="leading-tight text-red-600 dark:text-red-400">{{ $preview['below_minimum']->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm">Bajo nota mínima</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <flux:heading size="xl" class="leading-tight text-amber-600 dark:text-amber-400">{{ $preview['no_classroom']->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm">Sin aula destino</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <flux:heading size="xl" class="leading-tight text-amber-600 dark:text-amber-400">{{ $preview['no_capacity']->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm">Sin cupo</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <flux:heading size="xl" class="leading-tight text-amber-600 dark:text-amber-400">{{ $preview['age_mismatch']->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm">Edad fuera de rango</flux:text>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                <flux:heading size="xl" class="leading-tight text-zinc-400">{{ $preview['already_done']->count() }}</flux:heading>
                <flux:text class="text-zinc-500 text-sm">Ya promovidos</flux:text>
            </div>
        </div>

        {{-- Listos para promover --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
            <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <flux:heading size="lg">Listos para promover</flux:heading>
                @can('academic.manage')
                    <flux:button
                        variant="primary"
                        icon="arrow-up-circle"
                        :disabled="$preview['promote']->isEmpty()"
                        wire:click="confirmPromotion"
                        wire:loading.attr="disabled"
                        wire:confirm="¿Promover a los {{ $preview['promote']->count() }} estudiantes listados a continuación?"
                    >
                        Confirmar promoción
                    </flux:button>
                @endcan
            </div>

            @if ($preview['promote']->isEmpty())
                <flux:text class="text-zinc-500 text-sm">No hay estudiantes listos para promover con la selección actual.</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Estudiante</flux:table.column>
                        <flux:table.column>Aula actual</flux:table.column>
                        <flux:table.column>Aula destino</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($preview['promote'] as $row)
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <x-avatar-initials :initials="$row['enrollment']->student->initials" :photo="$row['enrollment']->student->photo" />
                                        <span class="font-medium">{{ $row['enrollment']->student->full_name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500">
                                    {{ $row['enrollment']->classroom->grade->name }}-{{ $row['enrollment']->classroom->section }}
                                </flux:table.cell>
                                <flux:table.cell class="font-medium">
                                    {{ $row['target_classroom']->grade->name }}-{{ $row['target_classroom']->section }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>

        {{-- Bajo nota mínima (requieren rehabilitación) --}}
        @if ($preview['below_minimum']->isNotEmpty())
            <div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/10 p-6 space-y-3">
                <flux:heading size="lg">No alcanzan la nota mínima</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Secundaria requiere un promedio ≥ {{ number_format(\App\Models\Enrollment::MINIMUM_PASSING_AVERAGE, 1) }} para pasar de año. No se promueven automáticamente — requieren rehabilitación.
                </flux:text>

                <div class="space-y-2">
                    @foreach ($preview['below_minimum'] as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $row['enrollment']->student->full_name }}</span>
                            <span class="text-zinc-500">
                                Promedio: {{ $row['average'] !== null ? number_format($row['average'], 1) : 'sin notas' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Requieren atención manual --}}
        @php
            $manual = $preview['no_classroom']->concat($preview['no_capacity'])->concat($preview['age_mismatch']);
        @endphp
        @if ($manual->isNotEmpty())
            <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/10 p-6 space-y-3">
                <flux:heading size="lg">Requieren atención manual</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    No se pueden promover automáticamente. Usa "Nueva matrícula" para ubicarlos manualmente.
                </flux:text>

                <div class="space-y-2">
                    @foreach ($preview['no_classroom'] as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $row['enrollment']->student->full_name }}</span>
                            <span class="text-zinc-500">No existe aula de {{ $row['next_grade']->name }} en el año destino</span>
                        </div>
                    @endforeach
                    @foreach ($preview['no_capacity'] as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $row['enrollment']->student->full_name }}</span>
                            <span class="text-zinc-500">Sin cupo en {{ $row['next_grade']->name }}</span>
                        </div>
                    @endforeach
                    @foreach ($preview['age_mismatch'] as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $row['enrollment']->student->full_name }}</span>
                            <span class="text-zinc-500">Edad fuera del rango de {{ $row['next_grade']->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Egresan --}}
        @if ($preview['graduate']->isNotEmpty())
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-3">
                <flux:heading size="lg">Egresan (último grado)</flux:heading>
                <div class="flex flex-wrap gap-2">
                    @foreach ($preview['graduate'] as $enrollment)
                        <flux:badge size="sm" color="zinc">{{ $enrollment->student->full_name }}</flux:badge>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

</div>
