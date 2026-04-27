<?php

use App\Models\Institution;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Institución')] class extends Component {
    use WithFileUploads;

    public ?Institution $institution = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|in:escuela,colegio')]
    public string $type = 'escuela';

    #[Validate('nullable|string|max:50')]
    public string $ruc = '';

    #[Validate('required|string|max:255')]
    public string $address = '';

    #[Validate('nullable|string|max:20')]
    public string $phone = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:255')]
    public string $director_name = '';

    #[Validate('nullable|image|max:2048')]
    public $logo = null;

    public function mount(): void
    {
        $this->institution = Institution::first();

        if ($this->institution) {
            $this->name          = $this->institution->name;
            $this->type          = $this->institution->type;
            $this->ruc           = $this->institution->ruc ?? '';
            $this->address       = $this->institution->address;
            $this->phone         = $this->institution->phone ?? '';
            $this->email         = $this->institution->email ?? '';
            $this->director_name = $this->institution->director_name ?? '';
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'          => $this->name,
            'type'          => $this->type,
            'ruc'           => $this->ruc ?: null,
            'address'       => $this->address,
            'phone'         => $this->phone ?: null,
            'email'         => $this->email ?: null,
            'director_name' => $this->director_name ?: null,
        ];

        if ($this->logo) {
            $data['logo'] = $this->logo->store('institution', 'public');
        }

        if ($this->institution) {
            $this->institution->update($data);
        } else {
            $this->institution = Institution::create($data);
        }

        $this->logo = null;

        Flux::toast(variant: 'success', text: 'Datos de la institución actualizados.');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">

        {{-- Encabezado --}}
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Institución</flux:heading>
                <flux:subheading>Información general del centro educativo</flux:subheading>
            </div>
        </div>

        <form wire:submit="save" class="space-y-6">

            {{-- Logo actual --}}
            @if ($institution?->logo)
                <div class="flex items-center gap-4">
                    <img src="{{ Storage::url($institution->logo) }}"
                         alt="Logo institución"
                         class="h-20 w-20 rounded-lg object-contain border border-zinc-200 dark:border-zinc-700 p-1">
                    <flux:text class="text-sm text-zinc-500">Logo actual</flux:text>
                </div>
            @endif

            {{-- Información general --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <flux:heading size="lg">Información general</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="name"
                        label="Nombre de la institución"
                        placeholder="Escuela Bilingüe Berta A. López"
                        required
                    />

                    <flux:select wire:model="type" label="Tipo de institución">
                        <flux:select.option value="escuela">Escuela</flux:select.option>
                        <flux:select.option value="colegio">Colegio</flux:select.option>
                    </flux:select>

                    <flux:input
                        wire:model="ruc"
                        label="RUC"
                        placeholder="4-76-2305"
                    />

                    <flux:input
                        wire:model="director_name"
                        label="Nombre del director(a)"
                        placeholder="Magister Silvana Ng"
                    />
                </div>
            </div>

            {{-- Contacto --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <flux:heading size="lg">Contacto</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="address"
                        label="Dirección"
                        placeholder="Una Milla, Almirante, Bocas del Toro"
                        required
                        class="sm:col-span-2"
                    />

                    <flux:input
                        wire:model="phone"
                        label="Teléfono"
                        placeholder="721-7979"
                        type="tel"
                    />

                    <flux:input
                        wire:model="email"
                        label="Correo electrónico"
                        placeholder="escuela@meduca.gob.pa"
                        type="email"
                    />
                </div>
            </div>

            {{-- Logo --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 space-y-4">
                <flux:heading size="lg">Logo</flux:heading>
                <flux:input
                    wire:model="logo"
                    label="Subir logo"
                    type="file"
                    accept="image/*"
                />
                @error('logo')
                    <flux:error>{{ $message }}</flux:error>
                @enderror

                @if ($logo)
                    <div class="mt-2">
                        <flux:text class="text-sm text-zinc-500 mb-1">Vista previa:</flux:text>
                        <img src="{{ $logo->temporaryUrl() }}"
                             class="h-20 w-20 rounded-lg object-contain border border-zinc-200 dark:border-zinc-700 p-1">
                    </div>
                @endif
            </div>

            {{-- Botón guardar --}}
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Guardar cambios</span>
                    <span wire:loading>Guardando...</span>
                </flux:button>
            </div>

        </form>
    </div>
