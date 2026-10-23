<?php

namespace Database\Seeders\Concerns;

use App\Models\Institution;
use App\Support\TenantContext;
use RuntimeException;

/**
 * Los seeders nunca deben usar Institution::first() / Team::first(): con
 * varias escuelas en la base agarrarían una cualquiera.
 */
trait InteractsWithTenant
{
    /**
     * Para seeders de la cadena de demo: la escuela que fijó InstitutionSeeder.
     */
    protected function currentInstitution(): Institution
    {
        return app(TenantContext::class)->get()
            ?? throw new RuntimeException(static::class.' necesita una institución en el TenantContext (corre InstitutionSeeder primero).');
    }

    /**
     * Para seeders que se corren sueltos (backfills en producción): si ya hay
     * una escuela en el contexto trabaja solo sobre ella; si no, recorre
     * todas, fijando el contexto en cada una para que los scopes filtren.
     *
     * @param  callable(Institution): void  $callback
     */
    protected function forEachInstitution(callable $callback): void
    {
        $context = app(TenantContext::class);
        $current = $context->get();

        foreach ($current ? [$current] : Institution::all() as $institution) {
            $context->set($institution);
            $callback($institution);
        }

        $context->set($current);
    }
}
