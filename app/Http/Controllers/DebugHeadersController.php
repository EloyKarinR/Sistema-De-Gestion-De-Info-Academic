<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DebugHeadersController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'X-Forwarded-Proto (server)' => $request->server('HTTP_X_FORWARDED_PROTO'),
            'X-Forwarded-For (server)' => $request->server('HTTP_X_FORWARDED_FOR'),
            'X-Forwarded-Host (server)' => $request->server('HTTP_X_FORWARDED_HOST'),
            'scheme (request)' => $request->getScheme(),
            'isSecure' => $request->isSecure(),
            'app.url config' => config('app.url'),
            'all HTTP_* server vars' => collect($request->server())->filter(
                fn ($v, $k) => str_starts_with($k, 'HTTP_')
            )->all(),
        ]);
    }
}
