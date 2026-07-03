<?php

use App\Enums\TeamRole;
use App\Models\Guardian;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Acudientes')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $portalGuardianId = null;

    public string $portalEmail = '';

    public string $portalPassword = '';

    public ?int $editGuardianId = null;

    public string $editFirstName = '';

    public string $editLastName = '';

    public string $editCedula = '';

    public string $editRelationship = 'padre';

    public string $editPrimaryPhone = '';

    public string $editEmergencyPhone = '';

    public string $editEmail = '';

    public string $editOccupation = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function guardians()
    {
        return Guardian::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ['%'.strtolower($this->search).'%'])
                        ->orWhere('cedula', 'LIKE', '%'.$this->search.'%');
                });
            })
            ->with(['user', 'students'])
            ->orderBy('last_name')
            ->paginate(15);
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

        unset($this->guardians);
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

        unset($this->guardians);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Encabezado --}}
    <div>
        <flux:heading size="xl">Acudientes</flux:heading>
        <flux:subheading>Listado de acudientes registrados</flux:subheading>
    </div>

    {{-- Búsqueda --}}
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Buscar por nombre o cédula..."
        icon="magnifying-glass"
        class="max-w-sm"
    />

    {{-- Tabla --}}
    @if ($this->guardians->count())
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Nombre</flux:table.column>
                <flux:table.column>Cédula</flux:table.column>
                <flux:table.column>Teléfono</flux:table.column>
                <flux:table.column>Hijo(s)</flux:table.column>
                <flux:table.column>Portal</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->guardians as $guardian)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            {{ $guardian->full_name }}
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ $guardian->cedula ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $guardian->primary_phone }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $guardian->students->pluck('full_name')->join(', ') ?: '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($guardian->user_id)
                                <flux:badge color="green" size="sm">Activo</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">Sin acceso</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @can('guardian.edit')
                                <div class="flex gap-2">
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
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-2">
            {{ $this->guardians->links() }}
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <flux:icon name="user-group" class="mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
            <flux:heading>Sin resultados</flux:heading>
            <flux:text class="text-zinc-500">
                @if ($this->search)
                    No se encontraron acudientes con "{{ $this->search }}".
                @else
                    Aún no hay acudientes registrados.
                @endif
            </flux:text>
        </div>
    @endif

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

</div>
