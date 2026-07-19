<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Un usuario solo puede tener una sesión activa a la vez — al iniciar sesión
 * en un dispositivo nuevo, se cierran las demás sesiones que tuviera abiertas
 * en otros dispositivos/navegadores.
 */
class InvalidateOtherSessions
{
    public function handle(Login $event): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $event->user->getAuthIdentifier())
            ->where('id', '!=', session()->getId())
            ->delete();
    }
}
