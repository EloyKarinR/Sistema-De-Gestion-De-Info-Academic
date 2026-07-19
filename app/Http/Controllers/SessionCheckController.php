<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Consultado en segundo plano por la app (ver layouts/app/sidebar.blade.php)
 * para detectar casi al instante si esta sesión fue cerrada porque alguien
 * inició sesión en otro dispositivo (InvalidateOtherSessions). Deliberadamente
 * fuera del middleware "auth" — si la sesión ya fue invalidada, Auth::check()
 * ya da false por sí solo, sin necesidad de redirigir aquí.
 */
class SessionCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['authenticated' => Auth::check()]);
    }
}
