<?php

use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Detalle Estudiante')] class extends Component {

    public Student $student;

    public function mount(Student $student): void
    {
        $this->student = $student->load([
            'guardians',
            'enrollments.classroom.grade',
            'enrollments.academicYear',
            'enrollments.registeredBy',
        ]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center gap-3">
        <flux:button
            icon="arrow-left"
            variant="ghost"
            size="sm"
            :href="route('students.index')"
            wire:navigate
        />
        <div>
            <flux:heading size="xl">{{ $student->full_name }}</flux:heading>
            <flux:subheading>Ficha del estudiante</flux:subheading>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Columna izquierda --}}
        <div class="space-y-6 lg:col-span-1">

            {{-- Datos personales --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <flux:heading size="lg">Datos personales</flux:heading>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Cédula</dt>
                        <dd class="font-medium">{{ $student->cedula ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Sexo</dt>
                        <dd>{{ $student->sex === 'M' ? 'Masculino' : 'Femenino' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Fecha de nacimiento</dt>
                        <dd>{{ $student->birth_date?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Edad</dt>
                        <dd>{{ $student->birth_date ? $student->birth_date->age . ' años' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Lugar de nacimiento</dt>
                        <dd>{{ $student->birth_place ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Tipo de sangre</dt>
                        <dd>{{ $student->blood_type ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">Dirección</dt>
                        <dd class="text-right max-w-[60%]">{{ $student->address ?? '—' }}</dd>
                    </div>
                    @if ($student->medical_conditions)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Condiciones médicas</dt>
                            <dd class="text-right max-w-[60%]">{{ $student->medical_conditions }}</dd>
                        </div>
                    @endif
                    @if ($student->previous_school)
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Colegio anterior</dt>
                            <dd class="text-right max-w-[60%]">{{ $student->previous_school }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Acudientes --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <flux:heading size="lg">Acudientes</flux:heading>

                @forelse ($student->guardians as $guardian)
                    <div class="space-y-2 {{ ! $loop->last ? 'pb-4 border-b border-zinc-100 dark:border-zinc-700' : '' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-sm">{{ $guardian->full_name }}</span>
                            @if ($guardian->pivot->is_primary)
                                <flux:badge size="sm" color="blue">Principal</flux:badge>
                            @endif
                        </div>
                        <dl class="space-y-1 text-sm text-zinc-500">
                            <div class="flex justify-between">
                                <dt>Parentesco</dt>
                                <dd class="capitalize">{{ $guardian->relationship }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt>Teléfono</dt>
                                <dd>{{ $guardian->primary_phone }}</dd>
                            </div>
                            @if ($guardian->emergency_phone)
                                <div class="flex justify-between">
                                    <dt>Emergencia</dt>
                                    <dd>{{ $guardian->emergency_phone }}</dd>
                                </div>
                            @endif
                            @if ($guardian->email)
                                <div class="flex justify-between">
                                    <dt>Correo</dt>
                                    <dd>{{ $guardian->email }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @empty
                    <flux:text class="text-zinc-400">Sin acudientes registrados.</flux:text>
                @endforelse
            </div>

        </div>

        {{-- Columna derecha: historial de matrículas --}}
        <div class="lg:col-span-2">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <flux:heading size="lg">Historial de matrículas</flux:heading>

                @forelse ($student->enrollments->sortByDesc('enrollment_date') as $enrollment)
                    <div class="rounded-lg border border-zinc-100 dark:border-zinc-700 p-4 space-y-3 {{ ! $loop->last ? 'mb-3' : '' }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">
                                    {{ $enrollment->classroom->grade->name }}-{{ $enrollment->classroom->section }}
                                </span>
                                <flux:badge size="sm" color="zinc">
                                    {{ $enrollment->academicYear->year }}
                                </flux:badge>
                            </div>
                            @php
                                $statusColor = match($enrollment->status) {
                                    'activo'     => 'green',
                                    'retirado'   => 'red',
                                    'trasladado' => 'yellow',
                                    default      => 'zinc',
                                };
                            @endphp
                            <flux:badge size="sm" :color="$statusColor" class="capitalize">
                                {{ $enrollment->status }}
                            </flux:badge>
                        </div>

                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <div class="flex justify-between col-span-1">
                                <dt class="text-zinc-500">Fecha</dt>
                                <dd>{{ $enrollment->enrollment_date->format('d/m/Y') }}</dd>
                            </div>
                            <div class="flex justify-between col-span-1">
                                <dt class="text-zinc-500">Tipo</dt>
                                <dd class="capitalize">{{ str_replace('_', ' ', $enrollment->enrollment_type) }}</dd>
                            </div>
                            <div class="flex justify-between col-span-1">
                                <dt class="text-zinc-500">Turno</dt>
                                <dd class="capitalize">{{ $enrollment->classroom->shift }}</dd>
                            </div>
                            <div class="flex justify-between col-span-1">
                                <dt class="text-zinc-500">Registrado por</dt>
                                <dd>{{ $enrollment->registeredBy?->name ?? '—' }}</dd>
                            </div>
                        </dl>

                        {{-- Documentos --}}
                        <div>
                            <p class="text-xs text-zinc-400 mb-1">Documentos entregados</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach ([
                                    'doc_cedula_student'  => 'Cédula alumno',
                                    'doc_cedula_guardian' => 'Cédula acudiente',
                                    'doc_boletin'         => 'Boletín',
                                    'doc_foto'            => 'Foto',
                                    'doc_address'         => 'Dirección',
                                ] as $field => $label)
                                    <flux:badge
                                        size="sm"
                                        :color="$enrollment->$field ? 'green' : 'zinc'"
                                    >
                                        {{ $label }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </div>

                        @if ($enrollment->notes)
                            <flux:text class="text-sm text-zinc-500">{{ $enrollment->notes }}</flux:text>
                        @endif
                    </div>
                @empty
                    <flux:text class="text-zinc-400">Sin matrículas registradas.</flux:text>
                @endforelse
            </div>
        </div>

    </div>

</div>
