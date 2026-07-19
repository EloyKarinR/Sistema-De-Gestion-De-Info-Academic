<?php

use App\Enums\Shift;
use App\Enums\TeamRole;
use App\Models\ClassSchedule;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Detalle Estudiante')] class extends Component
{
    use WithFileUploads;

    public Student $student;

    public $photo = null;

    public string $editStudentFirstName = '';

    public string $editStudentLastName = '';

    public string $editStudentCedula = '';

    public string $editStudentBirthDate = '';

    public string $editStudentSex = 'M';

    public string $editStudentAddress = '';

    public string $editStudentBirthPlace = '';

    public string $editStudentBloodType = '';

    public string $editStudentMedicalConditions = '';

    public string $editStudentPreviousSchool = '';

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
            'activeEnrollment.classroom.grade',
        ]);
    }

    #[Computed]
    public function scheduleByDay()
    {
        $enrollment = $this->student->activeEnrollment;

        if (! $enrollment) {
            return collect();
        }

        return ClassSchedule::where('classroom_id', $enrollment->classroom_id)
            ->with('subjectAssignment.subject', 'subjectAssignment.teacher')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');
    }

    public function updatePhoto(): void
    {
        $this->authorize('student.edit');

        $this->validate(['photo' => 'required|image|max:8192']);

        $this->student->update(['photo' => $this->photo->store('students', 'public')]);

        $this->photo = null;

        Flux::modal('update-photo')->close();
        Flux::toast(variant: 'success', text: 'Foto actualizada correctamente.');
    }

    public function openEditStudentModal(): void
    {
        $this->authorize('student.edit');

        $this->editStudentFirstName = $this->student->first_name;
        $this->editStudentLastName = $this->student->last_name;
        $this->editStudentCedula = $this->student->cedula ?? '';
        $this->editStudentBirthDate = $this->student->birth_date?->format('Y-m-d') ?? '';
        $this->editStudentSex = $this->student->sex;
        $this->editStudentAddress = $this->student->address ?? '';
        $this->editStudentBirthPlace = $this->student->birth_place ?? '';
        $this->editStudentBloodType = $this->student->blood_type ?? '';
        $this->editStudentMedicalConditions = $this->student->medical_conditions ?? '';
        $this->editStudentPreviousSchool = $this->student->previous_school ?? '';

        Flux::modal('edit-student')->show();
    }

    public function updateStudent(): void
    {
        $this->authorize('student.edit');

        $this->validate([
            'editStudentFirstName' => 'required|string|max:100',
            'editStudentLastName' => 'required|string|max:100',
            'editStudentCedula' => 'nullable|string|max:20|unique:students,cedula,'.$this->student->id,
            'editStudentBirthDate' => 'required|date',
            'editStudentSex' => 'required|in:M,F',
            'editStudentAddress' => 'required|string|max:255',
            'editStudentBirthPlace' => 'nullable|string|max:255',
            'editStudentBloodType' => 'nullable|string|max:10',
            'editStudentMedicalConditions' => 'nullable|string|max:500',
            'editStudentPreviousSchool' => 'nullable|string|max:255',
        ]);

        $this->student->update([
            'first_name' => $this->editStudentFirstName,
            'last_name' => $this->editStudentLastName,
            'cedula' => $this->editStudentCedula ?: null,
            'birth_date' => $this->editStudentBirthDate,
            'sex' => $this->editStudentSex,
            'address' => $this->editStudentAddress,
            'birth_place' => $this->editStudentBirthPlace ?: null,
            'blood_type' => $this->editStudentBloodType ?: null,
            'medical_conditions' => $this->editStudentMedicalConditions ?: null,
            'previous_school' => $this->editStudentPreviousSchool ?: null,
        ]);

        Flux::modal('edit-student')->close();
        Flux::toast(variant: 'success', text: 'Datos del estudiante actualizados.');
    }

    public function deleteStudent()
    {
        $this->authorize('student.delete');

        if ($this->student->enrollments()->count() > 0) {
            Flux::toast(variant: 'danger', text: 'No se puede eliminar un estudiante que ya tiene matrículas registradas.');

            return;
        }

        $this->student->delete();

        Flux::toast(variant: 'success', text: 'Estudiante eliminado.');

        return $this->redirect(route('students.index'), navigate: true);
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
    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <flux:button
                icon="arrow-left"
                variant="ghost"
                size="sm"
                :href="route('students.index')"
                wire:navigate
            />
            <div class="relative shrink-0">
                <x-avatar-initials :initials="$student->initials" :photo="$student->photo" size="size-14" text="text-lg" />
                @can('student.edit')
                    <flux:modal.trigger name="update-photo">
                        <button
                            type="button"
                            class="absolute -bottom-1 -right-1 flex size-6 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 border-2 border-white dark:border-zinc-900"
                        >
                            <flux:icon name="camera" class="size-3.5" />
                        </button>
                    </flux:modal.trigger>
                @endcan
            </div>
            <div>
                <flux:heading size="xl">{{ $student->full_name }}</flux:heading>
                <flux:subheading>Ficha del estudiante</flux:subheading>
            </div>
        </div>
        @can('student.delete')
            @if ($student->enrollments->isEmpty())
                <flux:button
                    icon="trash"
                    variant="danger"
                    size="sm"
                    wire:click="deleteStudent"
                    wire:confirm="¿Eliminar a {{ $student->full_name }}? No tiene ninguna matrícula registrada."
                >
                    Eliminar estudiante
                </flux:button>
            @endif
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Columna izquierda --}}
        <div class="space-y-6 lg:col-span-1">

            {{-- Datos personales --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Datos personales</flux:heading>
                    @can('student.edit')
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="pencil-square"
                            wire:click="openEditStudentModal"
                        >
                            Editar
                        </flux:button>
                    @endcan
                </div>

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
                            <div class="flex items-center gap-2">
                                <x-avatar-initials :initials="$guardian->initials" />
                                <span class="font-medium text-sm">{{ $guardian->full_name }}</span>
                            </div>
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

                        <dl class="grid grid-cols-1 gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
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
                                <dd>
                                    @php $enrollmentShift = Shift::from($enrollment->classroom->shift); @endphp
                                    <flux:badge size="sm" :color="$enrollmentShift->color()" :icon="$enrollmentShift->icon()">
                                        {{ $enrollmentShift->labelWithTime() }}
                                    </flux:badge>
                                </dd>
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

                        @if ($enrollment->status !== 'activo' && $enrollment->status_date)
                            <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 px-3 py-2 text-sm">
                                <span class="text-zinc-500">{{ ucfirst($enrollment->status) }} el {{ $enrollment->status_date->format('d/m/Y') }}</span>
                                @if ($enrollment->status_reason)
                                    <span class="text-zinc-500">— {{ $enrollment->status_reason }}</span>
                                @endif
                            </div>
                        @endif

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

    {{-- Horario semanal --}}
    @if ($student->activeEnrollment)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
            <flux:heading size="lg">Horario semanal</flux:heading>

            @if ($this->scheduleByDay->isEmpty())
                <flux:text class="text-zinc-500">Aún no hay un horario generado para esta aula.</flux:text>
            @else
                @php
                    $dayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
                    $timeSlots = $this->scheduleByDay->flatten(1)->unique(fn ($s) => $s->start_time->format('H:i'))->sortBy('start_time');
                @endphp
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm border-collapse">
                        <thead>
                            <tr>
                                <th class="text-left text-zinc-500 font-medium p-2 border-b border-zinc-200 dark:border-zinc-700">Hora</th>
                                @foreach ($dayNames as $dayName)
                                    <th class="text-left text-zinc-500 font-medium p-2 border-b border-zinc-200 dark:border-zinc-700">{{ $dayName }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timeSlots as $slot)
                                <tr>
                                    <td class="p-2 border-b border-zinc-100 dark:border-zinc-800 text-zinc-500 whitespace-nowrap">
                                        {{ $slot->start_time->format('H:i') }}–{{ $slot->end_time->format('H:i') }}
                                    </td>
                                    @foreach (array_keys($dayNames) as $day)
                                        @php
                                            $entry = ($this->scheduleByDay->get($day) ?? collect())
                                                ->first(fn ($s) => $s->start_time->format('H:i') === $slot->start_time->format('H:i'));
                                        @endphp
                                        <td class="p-2 border-b border-zinc-100 dark:border-zinc-800">
                                            @if ($entry)
                                                <div class="font-medium">{{ $entry->subjectAssignment->subject->name }}</div>
                                                <div class="text-xs text-zinc-400">{{ $entry->subjectAssignment->teacher->full_name }}</div>
                                            @else
                                                <span class="text-zinc-300 dark:text-zinc-600">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Modal: Editar estudiante --}}
    <flux:modal name="edit-student" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Editar estudiante</flux:heading>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="editStudentFirstName" label="Nombre(s)" required />
                @error('editStudentFirstName') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:input wire:model="editStudentLastName" label="Apellidos" required />
                @error('editStudentLastName') <flux:error>{{ $message }}</flux:error> @enderror
            </div>

            <flux:input wire:model="editStudentCedula" label="Cédula" />
            @error('editStudentCedula') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="editStudentBirthDate" label="Fecha de nacimiento" type="date" required />
                @error('editStudentBirthDate') <flux:error>{{ $message }}</flux:error> @enderror

                <flux:select wire:model="editStudentSex" label="Sexo">
                    <flux:select.option value="M">Masculino</flux:select.option>
                    <flux:select.option value="F">Femenino</flux:select.option>
                </flux:select>
            </div>

            <flux:input wire:model="editStudentAddress" label="Dirección" required />
            @error('editStudentAddress') <flux:error>{{ $message }}</flux:error> @enderror

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="editStudentBirthPlace" label="Lugar de nacimiento" />
                <flux:input wire:model="editStudentBloodType" label="Tipo de sangre" placeholder="O+" />
            </div>

            <flux:input wire:model="editStudentPreviousSchool" label="Escuela anterior" />

            <flux:textarea wire:model="editStudentMedicalConditions" label="Condiciones médicas / alergias" rows="2" />
            @error('editStudentMedicalConditions') <flux:error>{{ $message }}</flux:error> @enderror
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="updateStudent" wire:loading.attr="disabled">
                Guardar cambios
            </flux:button>
        </div>
    </flux:modal>

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

    {{-- Modal: Actualizar foto --}}
    <flux:modal name="update-photo" class="max-w-md">
        <flux:heading size="lg" class="mb-4">Actualizar foto</flux:heading>

        <div class="space-y-4">
            <flux:input wire:model="photo" label="Foto del estudiante" type="file" accept="image/*" />
            @error('photo') <flux:error>{{ $message }}</flux:error> @enderror

            @if ($photo)
                <img src="{{ $photo->temporaryUrl() }}" class="size-20 rounded-full object-cover border border-zinc-200 dark:border-zinc-700">
            @endif
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancelar</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" wire:click="updatePhoto" wire:loading.attr="disabled">
                Guardar
            </flux:button>
        </div>
    </flux:modal>

</div>
