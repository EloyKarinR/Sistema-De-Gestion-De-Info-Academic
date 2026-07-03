<x-layouts::auth title="Confirmar contraseña">
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Confirmar contraseña"
            description="Esta es un área segura de la aplicación. Confirma tu contraseña antes de continuar."
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="password"
                label="Contraseña"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Contraseña"
                viewable
            />

            <flux:button variant="primary" type="submit" class="w-full" data-test="confirm-password-button">
                Confirmar
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
