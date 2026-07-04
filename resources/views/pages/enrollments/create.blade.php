<?php

use App\Enums\Shift;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Nueva Matrícula')] class extends Component
{
    public int $step = 1;

    // Paso 1 — Estudiante
    public string $cedulaSearch = '';

    public string $studentMode = 'search'; // search | found | create

    public ?int $studentId = null;

    public string $firstName = '';

    public string $lastName = '';

    public string $birthDate = '';

    public string $sex = 'M';

    public string $address = '';

    public string $birthPlace = '';

    public string $bloodType = '';

    public string $medicalConditions = '';

    public string $previousSchool = '';

    // Paso 2 — Acudiente
    public string $guardianCedula = '';

    public string $guardianMode = 'create'; // create | existing

    public ?int $guardianId = null;

    public string $guardianFirstName = '';

    public string $guardianLastName = '';

    public string $relationship = 'padre';

    public string $primaryPhone = '';

    public string $emergencyPhone = '';

    public string $guardianEmail = '';

    public string $occupation = '';

    // Paso 3 — Matrícula
    public string $classroomId = '';

    public string $enrollmentType = 'nuevo_ingreso';

    public string $enrollmentDate = '';

    public bool $docCedulaStudent = false;

    public bool $docCedulaGuardian = false;

    public bool $docBoletin = false;

    public bool $docFoto = false;

    public bool $docAddress = false;

    public string $notes = '';

    // Recibo
    public ?int $enrollmentId = null;

    public string $previewUrl = '';

    public function mount(): void
    {
        $this->enrollmentDate = now()->format('Y-m-d');
    }

    public function previewConstancia(): void
    {
        $this->previewUrl = route('reports.constancia', $this->enrollmentId);

        Flux::modal('preview-constancia')->show();
    }

    #[Computed]
    public function activeYear(): ?AcademicYear
    {
        return AcademicYear::where('is_active', true)->first();
    }

    #[Computed]
    public function foundStudent(): ?Student
    {
        return $this->studentId ? Student::with('guardians')->find($this->studentId) : null;
    }

    #[Computed]
    public function receipt(): ?Enrollment
    {
        return $this->enrollmentId
            ? Enrollment::with(['student', 'student.guardians', 'classroom.grade', 'academicYear', 'registeredBy'])->find($this->enrollmentId)
            : null;
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

    // ── Paso 1 ────────────────────────────────────────────────────────────────

    public function searchStudent(): void
    {
        $this->validate(['cedulaSearch' => 'required|string|min:3']);

        $student = Student::where('cedula', trim($this->cedulaSearch))->first();

        if ($student) {
            if ($this->activeYear) {
                $alreadyEnrolled = Enrollment::where('student_id', $student->id)
                    ->where('academic_year_id', $this->activeYear->id)
                    ->where('status', 'activo')
                    ->exists();

                if ($alreadyEnrolled) {
                    $this->addError('cedulaSearch', 'Este estudiante ya está matriculado en el año escolar activo.');

                    return;
                }
            }

            $this->studentId = $student->id;
            $this->studentMode = 'found';
        } else {
            $this->studentMode = 'create';
        }
    }

    public function confirmFoundStudent(): void
    {
        $student = Student::with('guardians')->find($this->studentId);
        $primary = $student->guardians()->wherePivot('is_primary', true)->first();

        if ($primary) {
            $this->guardianId = $primary->id;
            $this->guardianMode = 'existing';
        } else {
            $this->guardianMode = 'create';
        }

        $this->step = 2;
        unset($this->foundStudent);
    }

    public function createStudent(): void
    {
        $this->validate([
            'firstName' => 'required|string|max:100',
            'lastName' => 'required|string|max:100',
            'birthDate' => 'required|date',
            'sex' => 'required|in:M,F',
            'address' => 'required|string|max:255',
        ]);

        $student = Student::create([
            'cedula' => trim($this->cedulaSearch) ?: null,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'birth_date' => $this->birthDate,
            'sex' => $this->sex,
            'address' => $this->address,
            'birth_place' => $this->birthPlace ?: null,
            'blood_type' => $this->bloodType ?: null,
            'medical_conditions' => $this->medicalConditions ?: null,
            'previous_school' => $this->previousSchool ?: null,
        ]);

        $this->studentId = $student->id;
        $this->guardianMode = 'create';
        $this->step = 2;
        unset($this->foundStudent);
    }

    public function backToStep1(): void
    {
        $this->studentMode = 'search';
        $this->studentId = null;
        $this->step = 1;
        unset($this->foundStudent);
    }

    // ── Paso 2 ────────────────────────────────────────────────────────────────

    public function searchGuardian(): void
    {
        $this->validate(['guardianCedula' => 'required|string|min:3']);

        $guardian = Guardian::where('cedula', trim($this->guardianCedula))->first();

        if ($guardian) {
            $this->guardianId = $guardian->id;
            $this->guardianMode = 'existing';
        } else {
            $this->addError('guardianCedula', 'No se encontró ningún acudiente con esa cédula. Completa el formulario para registrarlo.');
            $this->guardianFirstName = '';
            $this->guardianLastName = '';
            $this->guardianMode = 'create';
        }
    }

    public function confirmGuardian(): void
    {
        $this->step = 3;
    }

    public function createGuardian(): void
    {
        $this->validate([
            'guardianFirstName' => 'required|string|max:100',
            'guardianLastName' => 'required|string|max:100',
            'relationship' => 'required|in:padre,madre,abuelo,abuela,tio,tia,hermano,hermana,tutor,otro',
            'primaryPhone' => 'required|string|max:20',
            'guardianCedula' => 'nullable|string|max:20',
        ]);

        $guardian = Guardian::create([
            'cedula' => trim($this->guardianCedula) ?: null,
            'first_name' => $this->guardianFirstName,
            'last_name' => $this->guardianLastName,
            'relationship' => $this->relationship,
            'primary_phone' => $this->primaryPhone,
            'emergency_phone' => $this->emergencyPhone ?: null,
            'email' => $this->guardianEmail ?: null,
            'occupation' => $this->occupation ?: null,
        ]);

        $this->guardianId = $guardian->id;
        $this->step = 3;
    }

    public function backToStep2(): void
    {
        $this->step = 2;
    }

    // ── Paso 3 ────────────────────────────────────────────────────────────────

    public function saveEnrollment(): void
    {
        $this->validate([
            'classroomId' => 'required|exists:classrooms,id',
            'enrollmentType' => 'required|in:nuevo_ingreso,promovido,rehabilitacion,traslado',
            'enrollmentDate' => 'required|date',
        ]);

        if (! $this->activeYear) {
            Flux::toast(variant: 'danger', text: 'No hay año escolar activo.');

            return;
        }

        $student = Student::find($this->studentId);
        $classroom = Classroom::with('grade')->find($this->classroomId);

        if (! $classroom->grade->acceptsAge($student->age)) {
            $this->addError('classroomId', "Esta aula es para edades de {$classroom->grade->min_age} a {$classroom->grade->max_age} años. El estudiante tiene {$student->age}.");

            return;
        }

        if ($this->guardianId && ! $student->guardians()->where('guardian_id', $this->guardianId)->exists()) {
            $student->guardians()->attach($this->guardianId, ['is_primary' => true]);
        }

        $enrollment = Enrollment::create([
            'student_id' => $this->studentId,
            'classroom_id' => $this->classroomId,
            'academic_year_id' => $this->activeYear->id,
            'registered_by' => Auth::id(),
            'enrollment_date' => $this->enrollmentDate,
            'status' => 'activo',
            'enrollment_type' => $this->enrollmentType,
            'doc_cedula_student' => $this->docCedulaStudent,
            'doc_cedula_guardian' => $this->docCedulaGuardian,
            'doc_boletin' => $this->docBoletin,
            'doc_foto' => $this->docFoto,
            'doc_address' => $this->docAddress,
            'notes' => $this->notes ?: null,
        ]);

        $enrollment->update(['receipt_number' => 'MAT-'.$this->activeYear->year.'-'.str_pad($enrollment->id, 4, '0', STR_PAD_LEFT)]);

        $this->enrollmentId = $enrollment->id;
        $this->step = 4;
        unset($this->activeYear, $this->classrooms, $this->foundStudent, $this->receipt);
    }

    public function newEnrollment(): void
    {
        $this->reset();
        $this->enrollmentDate = now()->format('Y-m-d');
        $this->step = 1;
        $this->studentMode = 'search';
        $this->guardianMode = 'create';
        $this->relationship = 'padre';
        $this->sex = 'M';
        $this->enrollmentType = 'nuevo_ingreso';
        unset($this->activeYear, $this->classrooms, $this->foundStudent, $this->receipt);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div class="flex items-center gap-3">
        @if ($step < 4)
            <flux:button icon="arrow-left" variant="ghost" size="sm" :href="route('enrollments.index')" wire:navigate />
        @endif
        <div>
            <flux:heading size="xl">Nueva Matrícula</flux:heading>
            <flux:subheading>
                @if ($this->activeYear)
                    Año escolar {{ $this->activeYear->year }}
                @endif
            </flux:subheading>
        </div>
    </div>

    @if (! $this->activeYear && $step < 4)
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="calendar" class="mb-3 size-10 text-zinc-300" />
            <flux:heading>Sin año escolar activo</flux:heading>
            <flux:text class="text-zinc-500">Activa un año escolar antes de registrar matrículas.</flux:text>
        </div>
    @else

        {{-- Indicador de pasos --}}
        @if ($step < 4)
            <div class="flex items-center gap-2">
                @foreach (['Estudiante', 'Acudiente', 'Matrícula'] as $i => $label)
                    @php $n = $i + 1; @endphp
                    <div class="flex items-center gap-2">
                        <div class="flex items-center justify-center w-7 h-7 rounded-full text-sm font-semibold
                            {{ $step > $n ? 'bg-green-500 text-white' : ($step === $n ? 'bg-zinc-900 dark:bg-white text-white dark:text-zinc-900' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-500') }}">
                            @if ($step > $n)
                                <flux:icon name="check" class="size-4" />
                            @else
                                {{ $n }}
                            @endif
                        </div>
                        <span class="text-sm {{ $step === $n ? 'font-medium' : 'text-zinc-400' }}">{{ $label }}</span>
                    </div>
                    @if ($i < 2)
                        <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700 max-w-12"></div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- PASO 1: ESTUDIANTE                                                 --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($step === 1)
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-5 max-w-2xl">
                <flux:heading size="lg">Datos del estudiante</flux:heading>

                {{-- Búsqueda por cédula --}}
                @if ($studentMode === 'search' || $studentMode === 'found')
                    <div class="flex gap-2">
                        <flux:input
                            wire:model="cedulaSearch"
                            label="Cédula del estudiante"
                            placeholder="1-782-1109"
                            class="flex-1"
                            wire:keydown.enter="searchStudent"
                        />
                        <div class="flex items-end">
                            <flux:button wire:click="searchStudent" wire:loading.attr="disabled">
                                Buscar
                            </flux:button>
                        </div>
                    </div>
                    @error('cedulaSearch') <flux:error>{{ $message }}</flux:error> @enderror
                @endif

                {{-- Estudiante encontrado --}}
                @if ($studentMode === 'found' && $this->foundStudent)
                    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 space-y-2">
                        <div class="flex items-center gap-2 text-green-700 dark:text-green-400 font-medium text-sm">
                            <flux:icon name="check-circle" class="size-4" />
                            Estudiante encontrado
                        </div>
                        <div class="flex items-center gap-3">
                            <x-avatar-initials :initials="$this->foundStudent->initials" />
                            <span class="font-medium">{{ $this->foundStudent->full_name }}</span>
                        </div>
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Cédula</dt>
                                <dd>{{ $this->foundStudent->cedula ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Sexo</dt>
                                <dd>{{ $this->foundStudent->sex === 'M' ? 'Masculino' : 'Femenino' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-zinc-500">Fecha nac.</dt>
                                <dd>{{ $this->foundStudent->birth_date?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <flux:button variant="ghost" wire:click="backToStep1">Buscar otro</flux:button>
                        <flux:button variant="primary" wire:click="confirmFoundStudent">Continuar con este estudiante</flux:button>
                    </div>
                @endif

                {{-- Estudiante no encontrado: crear --}}
                @if ($studentMode === 'create')
                    <div class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 px-4 py-3 text-sm text-yellow-700 dark:text-yellow-400">
                        No se encontró ningún estudiante con esa cédula. Completa los datos para registrarlo.
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model="firstName" label="Nombre(s)" placeholder="Osmar Jesse" required />
                        @error('firstName') <flux:error>{{ $message }}</flux:error> @enderror

                        <flux:input wire:model="lastName" label="Apellidos" placeholder="Bowie Miller" required />
                        @error('lastName') <flux:error>{{ $message }}</flux:error> @enderror

                        <flux:input wire:model="birthDate" label="Fecha de nacimiento" type="date" required />
                        @error('birthDate') <flux:error>{{ $message }}</flux:error> @enderror

                        <flux:select wire:model="sex" label="Sexo">
                            <flux:select.option value="M">Masculino</flux:select.option>
                            <flux:select.option value="F">Femenino</flux:select.option>
                        </flux:select>

                        <flux:input wire:model="address" label="Dirección" placeholder="Almirante, Bocas del Toro" required class="sm:col-span-2" />
                        @error('address') <flux:error>{{ $message }}</flux:error> @enderror

                        <flux:input wire:model="birthPlace" label="Lugar de nacimiento" placeholder="Almirante, Bocas del Toro" />
                        <flux:input wire:model="bloodType" label="Tipo de sangre" placeholder="O+" />
                        <flux:input wire:model="previousSchool" label="Escuela anterior" placeholder="Escuela Berta A. López" class="sm:col-span-2" />
                        <flux:input wire:model="medicalConditions" label="Condiciones médicas / alergias" placeholder="Ninguna" class="sm:col-span-2" />
                    </div>

                    <div class="flex gap-2 justify-end">
                        <flux:button variant="ghost" wire:click="backToStep1">Volver</flux:button>
                        <flux:button variant="primary" wire:click="createStudent" wire:loading.attr="disabled">
                            Registrar y continuar
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- PASO 2: ACUDIENTE                                                  --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($step === 2)
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-5 max-w-2xl">
                <flux:heading size="lg">Datos del acudiente</flux:heading>

                {{-- Acudiente existente --}}
                @if ($guardianMode === 'existing' && $guardianId)
                    @php $existingGuardian = \App\Models\Guardian::find($guardianId); @endphp
                    @if ($existingGuardian)
                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 space-y-2">
                            <div class="flex items-center gap-2 text-green-700 dark:text-green-400 font-medium text-sm">
                                <flux:icon name="check-circle" class="size-4" />
                                Acudiente registrado
                            </div>
                            <div class="flex items-center gap-3">
                                <x-avatar-initials :initials="$existingGuardian->initials" />
                                <span class="font-medium">{{ $existingGuardian->full_name }}</span>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500">Parentesco</dt>
                                    <dd class="capitalize">{{ $existingGuardian->relationship }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500">Teléfono</dt>
                                    <dd>{{ $existingGuardian->primary_phone }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-zinc-500">Cédula</dt>
                                    <dd>{{ $existingGuardian->cedula ?? '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                        <div class="flex gap-2 justify-between">
                            <flux:button variant="ghost" wire:click="backToStep1">← Anterior</flux:button>
                            <div class="flex gap-2">
                                <flux:button variant="ghost" wire:click="$set('guardianMode', 'create'); $set('guardianId', null)">
                                    Usar otro acudiente
                                </flux:button>
                                <flux:button variant="primary" wire:click="confirmGuardian">Continuar</flux:button>
                            </div>
                        </div>
                    @endif
                @else
                    {{-- Buscar acudiente existente --}}
                    <div class="flex gap-2">
                        <flux:input
                            wire:model="guardianCedula"
                            label="Cédula del acudiente (opcional — para buscar uno existente)"
                            placeholder="8-123-4567"
                            class="flex-1"
                        />
                        <div class="flex items-end">
                            <flux:button wire:click="searchGuardian">Buscar</flux:button>
                        </div>
                    </div>
                    @error('guardianCedula') <flux:error>{{ $message }}</flux:error> @enderror

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input wire:model="guardianFirstName" label="Nombre(s)" placeholder="Juan" required />
                        @error('guardianFirstName') <flux:error>{{ $message }}</flux:error> @enderror

                        <flux:input wire:model="guardianLastName" label="Apellidos" placeholder="Bowie" required />
                        @error('guardianLastName') <flux:error>{{ $message }}</flux:error> @enderror

                        <flux:select wire:model="relationship" label="Parentesco">
                            @foreach (['padre','madre','abuelo','abuela','tio','tia','hermano','hermana','tutor','otro'] as $rel)
                                <flux:select.option value="{{ $rel }}">{{ ucfirst($rel) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="primaryPhone" label="Teléfono principal" placeholder="6000-0000" type="tel" required />
                        @error('primaryPhone') <flux:error>{{ $message }}</flux:error> @enderror

                        <flux:input wire:model="emergencyPhone" label="Teléfono de emergencia" placeholder="6000-0001" type="tel" />
                        <flux:input wire:model="guardianEmail" label="Correo electrónico" placeholder="juan@email.com" type="email" />
                        <flux:input wire:model="occupation" label="Ocupación" placeholder="Docente" class="sm:col-span-2" />
                    </div>

                    <div class="flex gap-2 justify-between">
                        <flux:button variant="ghost" wire:click="backToStep1">← Anterior</flux:button>
                        <flux:button variant="primary" wire:click="createGuardian" wire:loading.attr="disabled">
                            Registrar y continuar
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- PASO 3: MATRÍCULA                                                  --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($step === 3)
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-5 max-w-2xl">
                <flux:heading size="lg">Datos de la matrícula</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:select wire:model="classroomId" label="Aula" placeholder="Selecciona un aula" class="sm:col-span-2">
                        @foreach ($this->classrooms as $classroom)
                            @php
                                $available = $classroom->available_spots;
                                $ageOk = $classroom->grade->acceptsAge($this->foundStudent?->age);
                            @endphp
                            <flux:select.option
                                value="{{ $classroom->id }}"
                                :disabled="$available <= 0 || ! $ageOk"
                            >
                                {{ $classroom->grade->name }}-{{ $classroom->section }}
                                ({{ $classroom->grade->educationLevel->name }})
                                — {{ $available }} disponibles
                                @if (! $ageOk)
                                    (requiere {{ $classroom->grade->min_age }}-{{ $classroom->grade->max_age }} años)
                                @endif
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('classroomId') <flux:error>{{ $message }}</flux:error> @enderror
                    @if ($this->foundStudent?->age !== null)
                        <flux:text class="text-xs text-zinc-500 sm:col-span-2">
                            Edad actual del estudiante: {{ $this->foundStudent->age }} años.
                        </flux:text>
                    @endif

                    <flux:select wire:model="enrollmentType" label="Tipo de matrícula">
                        <flux:select.option value="nuevo_ingreso">Nuevo ingreso</flux:select.option>
                        <flux:select.option value="promovido">Promovido</flux:select.option>
                        <flux:select.option value="rehabilitacion">Rehabilitación</flux:select.option>
                        <flux:select.option value="traslado">Traslado</flux:select.option>
                    </flux:select>

                    <flux:input wire:model="enrollmentDate" label="Fecha de matrícula" type="date" />
                    @error('enrollmentDate') <flux:error>{{ $message }}</flux:error> @enderror
                </div>

                {{-- Documentos --}}
                <div>
                    <flux:heading size="sm" class="mb-3">Documentos entregados</flux:heading>
                    <div class="space-y-2">
                        <flux:checkbox wire:model="docCedulaStudent"  label="Copia de cédula del estudiante" />
                        <flux:checkbox wire:model="docCedulaGuardian" label="Copia de cédula del acudiente" />
                        <flux:checkbox wire:model="docBoletin"        label="Copia del boletín de notas" />
                        <flux:checkbox wire:model="docFoto"           label="Foto del estudiante" />
                        <flux:checkbox wire:model="docAddress"        label="Comprobante de dirección" />
                    </div>
                </div>

                <flux:textarea wire:model="notes" label="Observaciones (opcional)" placeholder="Notas adicionales..." rows="2" />

                <div class="flex gap-2 justify-between">
                    <flux:button variant="ghost" wire:click="backToStep2">← Anterior</flux:button>
                    <flux:button variant="primary" wire:click="saveEnrollment" wire:loading.attr="disabled">
                        <span wire:loading.remove>Matricular</span>
                        <span wire:loading>Guardando...</span>
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        {{-- PASO 4: RECIBO                                                     --}}
        {{-- ═══════════════════════════════════════════════════════════════════ --}}
        @if ($step === 4 && $this->receipt)
            @php
                $r        = $this->receipt;
                $guardian = $r->student->guardians->first();
            @endphp
            <div class="max-w-2xl space-y-4">

                <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 flex items-center gap-2 text-green-700 dark:text-green-400 font-medium">
                    <flux:icon name="check-circle" class="size-5" />
                    Matrícula registrada exitosamente
                </div>

                {{-- Recibo --}}
                <div id="receipt" class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-5">
                    <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700 pb-4">
                        <div>
                            <flux:heading size="lg">Recibo de Matrícula</flux:heading>
                            <flux:text class="text-zinc-500 text-sm">{{ $r->receipt_number }}</flux:text>
                        </div>
                        <flux:badge color="green" size="lg">{{ $r->academicYear->year }}</flux:badge>
                    </div>

                    <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        <div class="col-span-2 flex items-center gap-2 mb-1">
                            <x-avatar-initials :initials="$r->student->initials" size="size-7" />
                            <span class="font-medium text-zinc-700 dark:text-zinc-300">Estudiante</span>
                        </div>
                        <div class="flex justify-between"><span class="text-zinc-500">Nombre</span><span class="font-medium">{{ $r->student->full_name }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Cédula</span><span>{{ $r->student->cedula ?? '—' }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Aula</span><span>{{ $r->classroom->grade->name }}-{{ $r->classroom->section }}</span></div>
                        @php $receiptShift = Shift::from($r->classroom->shift); @endphp
                        <div class="flex justify-between items-center"><span class="text-zinc-500">Turno</span><flux:badge size="sm" :color="$receiptShift->color()" :icon="$receiptShift->icon()">{{ $receiptShift->labelWithTime() }}</flux:badge></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Tipo</span><span class="capitalize">{{ str_replace('_', ' ', $r->enrollment_type) }}</span></div>
                        <div class="flex justify-between"><span class="text-zinc-500">Fecha</span><span>{{ $r->enrollment_date->format('d/m/Y') }}</span></div>
                    </div>

                    @if ($guardian)
                        <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm border-t border-zinc-100 dark:border-zinc-700 pt-4">
                            <div class="col-span-2 font-medium text-zinc-700 dark:text-zinc-300 mb-1">Acudiente</div>
                            <div class="flex justify-between"><span class="text-zinc-500">Nombre</span><span class="font-medium">{{ $guardian->full_name }}</span></div>
                            <div class="flex justify-between"><span class="text-zinc-500">Parentesco</span><span class="capitalize">{{ $guardian->relationship }}</span></div>
                            <div class="flex justify-between"><span class="text-zinc-500">Teléfono</span><span>{{ $guardian->primary_phone }}</span></div>
                            <div class="flex justify-between"><span class="text-zinc-500">Cédula</span><span>{{ $guardian->cedula ?? '—' }}</span></div>
                        </div>
                    @endif

                    <div class="border-t border-zinc-100 dark:border-zinc-700 pt-4">
                        <p class="text-xs text-zinc-400 mb-2">Documentos entregados</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ([
                                'doc_cedula_student'  => 'Cédula alumno',
                                'doc_cedula_guardian' => 'Cédula acudiente',
                                'doc_boletin'         => 'Boletín',
                                'doc_foto'            => 'Foto',
                                'doc_address'         => 'Dirección',
                            ] as $field => $label)
                                <flux:badge size="sm" :color="$r->$field ? 'green' : 'zinc'">
                                    {{ $r->$field ? '✓' : '✗' }} {{ $label }}
                                </flux:badge>
                            @endforeach
                        </div>
                    </div>

                    <div class="border-t border-zinc-100 dark:border-zinc-700 pt-3 text-xs text-zinc-400">
                        Registrado por {{ $r->registeredBy?->name }} · {{ $r->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>

                <div class="flex gap-2 justify-between">
                    <flux:button
                        variant="ghost"
                        icon="eye"
                        :href="route('students.show', $r->student)"
                        wire:navigate
                    >
                        Ver ficha del estudiante
                    </flux:button>
                    <div class="flex gap-2">
                        <flux:button icon="printer" onclick="window.print()">Imprimir recibo</flux:button>
                        @can('reports.print')
                            <flux:button icon="document-text" wire:click="previewConstancia">
                                Ver constancia (PDF)
                            </flux:button>
                        @endcan
                        <flux:button variant="primary" wire:click="newEnrollment">Nueva matrícula</flux:button>
                    </div>
                </div>
            </div>
        @endif

    @endif

    {{-- Modal: Vista previa de la constancia --}}
    <flux:modal name="preview-constancia" class="max-w-4xl">
        <flux:heading size="lg" class="mb-4">Constancia de matrícula</flux:heading>

        @if ($previewUrl)
            <iframe
                src="{{ $previewUrl }}"
                class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700"
                style="height: 75vh;"
            ></iframe>
        @endif
    </flux:modal>
</div>
