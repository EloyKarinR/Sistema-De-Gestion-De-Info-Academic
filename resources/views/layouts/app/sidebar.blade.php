<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <livewire:team-switcher />

            <flux:sidebar.nav>
                {{-- Principal --}}
                <flux:sidebar.group heading="Principal" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Dashboard
                    </flux:sidebar.item>
                </flux:sidebar.group>

                {{-- Administración --}}
                @canany(['institution.view', 'academic.view'])
                    <flux:sidebar.group heading="Administración" class="grid">
                        @can('institution.view')
                            <flux:sidebar.item icon="building-office-2" :href="route('institution.edit')" :current="request()->routeIs('institution.edit')" wire:navigate>
                                Institución
                            </flux:sidebar.item>
                        @endcan
                        @can('academic.view')
                            <flux:sidebar.item icon="academic-cap" :href="route('academic.index')" :current="request()->routeIs('academic.index')" wire:navigate>
                                Académico
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                {{-- Gestión --}}
                @canany(['student.view', 'guardian.view', 'enrollment.view', 'teacher.view', 'scores.view', 'attendance.view'])
                    <flux:sidebar.group heading="Gestión" class="grid">
                        @can('student.view')
                            <flux:sidebar.item icon="users" :href="route('students.index')" :current="request()->routeIs('students.*')" wire:navigate>
                                Estudiantes
                            </flux:sidebar.item>
                        @endcan
                        @can('guardian.view')
                            <flux:sidebar.item icon="user-group" :href="route('guardians.index')" :current="request()->routeIs('guardians.*')" wire:navigate>
                                Acudientes
                            </flux:sidebar.item>
                        @endcan
                        @can('teacher.view')
                            <flux:sidebar.item icon="identification" :href="route('teachers.index')" :current="request()->routeIs('teachers.*')" wire:navigate>
                                Docentes
                            </flux:sidebar.item>
                        @endcan
                        @can('enrollment.view')
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('enrollments.index')" :current="request()->routeIs('enrollments.*')" wire:navigate>
                                Matrículas
                            </flux:sidebar.item>
                        @endcan
                        @can('scores.view')
                            <flux:sidebar.item icon="pencil-square" :href="route('scores.index')" :current="request()->routeIs('scores.*')" wire:navigate>
                                Notas
                            </flux:sidebar.item>
                        @endcan
                        @can('attendance.view')
                            <flux:sidebar.item icon="clipboard-document-check" :href="route('attendance.index')" :current="request()->routeIs('attendance.*')" wire:navigate>
                                Asistencia
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                {{-- Portal del acudiente --}}
                @can('portal.view')
                    <flux:sidebar.group heading="Portal" class="grid">
                        <flux:sidebar.item icon="home-modern" :href="route('portal.index')" :current="request()->routeIs('portal.*')" wire:navigate>
                            Mi Portal
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                {{-- Reportes --}}
                @can('reports.view')
                    <flux:sidebar.group heading="Reportes" class="grid">
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                            Reportes
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
