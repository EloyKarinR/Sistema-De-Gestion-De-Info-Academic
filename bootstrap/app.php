<?php

use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetTeamUrlDefaults::class,
        ]);

        // La app corre detrás de Caddy (reverse proxy que termina TLS) dentro
        // de la misma red privada de Docker — confiamos en cualquier proxy
        // ('*') porque solo Caddy puede llegarle a este contenedor. Sin esto,
        // Laravel no sabe que la conexión original fue https y cosas como la
        // validación de URLs firmadas (subir archivos) fallan con 401.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
