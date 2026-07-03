<?php

use App\Enums\Shift;
use App\Enums\TeamRole;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Detalle Estudiante')] class extends Component
{
    public Student $student;

    public ?int $editGuardianId = null;

    public string $editFirstName = '';

    public string $editLastName = '';

    public string $editCedula = '';

    public string $editRelationship = 'padre';

    public string $editPrimaryPhone = '';

    public string $editEmergencyPhone = '';

    public string $editEmail = '';

    public string $editOccupation = '';

    public ?int $portalGuardianId = null;

    public string $portalEmail = '';

    public string $portalPassword = '';

    public function mount(Student $student): void
    {
        $this->student = $student->load([
            'guardians',
            'enrollments.classroom.grade',
            'enrollments.academicYear',
            'enrollments.registeredBy',
        ]);
    }

    public function openEditModal(int $guardianId): void
    {
        $this->authorize('guardian.edit');

        $guardian = Guardian::findOrFail($guardianId);

        $this->editGuardianId = $guardianId;
        $this->editFirstName = $guardian->first_name;
        $this->editLastName = $guardian->last_name;
        $this->editCedula = $guardian->cedula ?? '';
        $this->editRelationship = $guardian->relationship;
        $this->editPrimaryPhone = $guardian->primary_phone;
        $this->editEmergencyPhone = $guardian->emergency_phone ?? '';
        $this->editEmail = $guardian->email ?? '';
        $this->editOccupation = $guardian->occupation ?? '';

        Flux::modal('edit-guardian')->show();
    }

    public function updateGuardian(): void
    {
        $this->authorize('guardian.edit');

        $this->validate([
            'editFirstName' => 'required|string|max:100',
            'editLastName' => 'required|string|max:100',
            'editCedula' => 'nullable|string|max:20|unique:guardians,cedula,'.$this->editGuardianId,
            'editRelationship' => 'required|in:padre,madre,abuelo,abuela,tio,tia,hermano,hermana,tutor,otro',
            'editPrimaryPhone' => 'required|string|max:20',
            'editEmergencyPhone' => 'nullable|string|max:20',
            'editEmail' => 'nullable|email|max:255',
            'editOccupation' => 'nullable|string|max:255',
        ]);

        Guardian::findOrFail($this->editGuardianId)->update([
            'first_name' => $this->editFirstName,
            'last_name' => $this->editLastName,
            'cedula' => $this->editCedula ?: null,
            'relationship' => $this->editRelationship,
            'primary_phone' => $this->editPrimaryPhone,
            'emergency_phone' => $this->editEmergencyPhone ?: null,
            'email' => $this->editEmail ?: null,
            'occupation' => $this->editOccupation ?: null,
        ]);

        Flux::modal('edit-guardian')->close();
        Flux::toast(variant: 'success', text: 'Datos del acudiente actualizados.');

        $this->student->load('guardians');
    }

    public function openPortalModal(int $guardianId): void
    {
        $this->authorize('guardian.edit');

        $guardian = Guardian::findOrFail($guardianId);

        $this->portalGuardianId = $guardianId;
        $this->portalEmail = $guardian->email ?? '';
        $this->portalPassword = '';

        Flux::modal('grant-portal')->show();
    }

    public function grantPortalAccess(): void
    {
        $this->authorize('guardian.edit');

        $this->validate([
            'portalEmail' => 'required|email|max:255|unique:users,email',
            'portalPassword' => 'required|string|min:8',
        ]);

        $guardian = Guardian::findOrFail($this->portalGuardianId);
        $team = Auth::user()->currentTeam;

        DB::transaction(function () use ($guardian, $team) {
            $user = User::create([
                'name' => $guardian->full_name,
                'email' => $this->portalEmail,
                'password' => Hash::make($this->portalPassword),
                'current_team_id' => $team->id,
            ]);
            $user->assignRole('acudiente');
            $team->members()->attach($user->id, ['role' => TeamRole::Member->value]);

            $guardian->update(['user_id' => $user->id]);
        });

        $this->reset(['portalGuardianId', 'portalEmail', 'portalPassword']);

        Flux::modal('grant-portal')->close();
        Flux::toast(variant: 'success', text: 'Acceso al portal creado correctamente.');

        $this->student->load('guardians');
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
                            <div class="flex justify-between">
                                <dt>Portal</dt>
                                <dd>
                                    @if ($guardian->user_id)
                                        <flux:badge color="green" size="sm">Activo</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">Sin acceso</flux:badge>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        @can('guardian.edit')
                            <div class="flex gap-2 pt-1">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil-square"
                                    wire:click="openEditModal({{ $guardian->id }})"
                                >
                                    Editar
                                </flux:button>
                                @if (! $guardian->user_id)
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="key"
                                        wire:click="openPortalModal({{ $guardian->id }})"
                                    >
                                        Dar acceso
                                    </flux:button>
                                @endif
                            </div>
                        @endcan
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
                                <dd>{{ Shift::from($enrollment->classroom->shift)->labelWithTime() }}</dd>
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

    {{-- Modal: Editar acudiente --}}
    <flux:modal name="edit-guardian" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Editar acudiente</flux:heading>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="editFirstName" label="Nombre(s)" required />
                @error('editFirstName') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:input wire:model="editLastName" label="Apellidos" required />
                @error('editLastName') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <flux:input wire:model="editCedula" label="Cédula" />
            @error('editCedula') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:select wire:model="editRelationship" label="Parentesco">
                @foreach (['padre','madre','abuelo','abuela','tio','tia','hermano','hermana','tutor','otro'] as $rel)
                    <flux:select.option value="{{ $rel }}">{{ ucfirst($rel) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="editPrimaryPhone" label="Teléfono principal" type="tel" required />
                @error('editPrimaryPhone') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:input wire:model="editEmergencyPhone" label="Teléfono de emergencia" type="tel" />
            </div>

            <flux:input wire:model="editEmail" label="Correo electrónico" type="email" />
            @error('editEmail') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:input wire:model="editOccupation" label="Ocupación" />
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="updateGuardian" wire:loading.attr="disabled">
                Guardar cambios
            </flux:button>
        </div>
    </flux:modal>

    {{-- Modal: Dar acceso al portal --}}
    <flux:modal name="grant-portal" class="max-w-md">
        <flux:heading size="lg" class="mb-1">Acceso al portal</flux:heading>
        <flux:subheading class="mb-4">Crea las credenciales para que el acudiente vea el portal de su hijo.</flux:subheading>

        <div class="space-y-4">
            <flux:input wire:model="portalEmail" label="Correo electrónico" type="email" placeholder="acudiente@correo.com" required />
            @error('portalEmail') <flux:error>{{ $message }}</flux:error> @enderror

            <flux:input wire:model="portalPassword" label="Contraseña temporal" type="password" required />
            @error('portalPassword') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="grantPortalAccess" wire:loading.attr="disabled">
                Crear acceso
            </flux:button>
        </div>
    </flux:modal>

</div>
