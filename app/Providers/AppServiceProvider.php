<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // La app corre detrás de un proxy que termina TLS (Caddy, Render, túneles
        // de desarrollo) y nos reenvía la petición por HTTP — sin esto, las URLs
        // generadas (assets, rutas) quedarían en http:// y el navegador las
        // bloquearía como contenido mixto en una página https://. No confiamos
        // solo en X-Forwarded-Proto (el proxy no siempre lo reenvía tal cual):
        // si configuramos la app para vivir en una URL https://, generamos
        // siempre https://, sin importar el esquema de la petición entrante.
        if (request()->server('HTTP_X_FORWARDED_PROTO') === 'https' || str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
