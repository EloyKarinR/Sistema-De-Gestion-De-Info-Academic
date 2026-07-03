<x-layouts::auth title="Autenticación de dos factores">
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;

                    this.code = '';
                    this.recovery_code = '';

                    $dispatch('clear-2fa-auth-code');

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : $dispatch('focus-2fa-auth-code');
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    title="Código de autenticación"
                    description="Ingresa el código de autenticación de tu aplicación autenticadora."
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    title="Código de recuperación"
                    description="Confirma el acceso a tu cuenta ingresando uno de tus códigos de recuperación de emergencia."
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="flex items-center justify-center my-5">
                            <flux:otp
                                x-model="code"
                                length="6"
                                name="code"
                                label="Código OTP"
                                label:sr-only
                                class="mx-auto"
                             />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <flux:input
                                type="text"
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-bind:required="showRecoveryInput"
                                autocomplete="one-time-code"
                                x-model="recovery_code"
                            />
                        </div>

                        @error('recovery_code')
                            <flux:text color="red">
                                {{ $message }}
                            </flux:text>
                        @enderror
                    </div>

                    <flux:button
                        variant="primary"
                        type="submit"
                        class="w-full"
                    >
                        Continuar
                    </flux:button>
                </div>

                <div class="mt-5 space-x-0.5 text-sm leading-5 text-center">
                    <span class="opacity-50">o puedes</span>
                    <div class="inline font-medium underline cursor-pointer opacity-80">
                        <span x-show="!showRecoveryInput" @click="toggleInput()">iniciar sesión con un código de recuperación</span>
                        <span x-show="showRecoveryInput" @click="toggleInput()">iniciar sesión con un código de autenticación</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
