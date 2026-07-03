<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 text-center">
            <div class="flex flex-col items-center gap-4">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-zinc-900 dark:bg-white">
                    <x-app-logo-icon class="size-9 text-white dark:text-zinc-900" />
                </span>
                <div class="space-y-1">
                    <flux:heading size="xl">SIGA</flux:heading>
                    <flux:subheading>Sistema de Gestión de Información Académica</flux:subheading>
                </div>
            </div>

            <flux:button variant="primary" :href="route('login')" wire:navigate>
                Iniciar sesión
            </flux:button>
        </div>

        @fluxScripts
    </body>
</html>
