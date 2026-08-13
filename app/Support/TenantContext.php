<?php

namespace App\Support;

use App\Models\Institution;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve la Institution "actual" para el scoping multi-tenant.
 *
 * Si hay un usuario autenticado, SIEMPRE se resuelve en vivo a partir de él
 * (Auth::user()->currentInstitution()) — nunca se cachea, porque un cambio
 * de equipo (switchTeam) a mitad de proceso debe reflejarse de inmediato.
 * Sin usuario autenticado (seeders, comandos de consola, fixtures de test
 * antes de un actingAs) se usa el valor fijado explícitamente con set().
 *
 * Registrado como singleton: vive durante toda la petición/proceso.
 */
class TenantContext
{
    private ?Institution $institution = null;

    public function set(?Institution $institution): void
    {
        $this->institution = $institution;
    }

    public function get(): ?Institution
    {
        if (Auth::check()) {
            return Auth::user()->currentInstitution();
        }

        return $this->institution;
    }

    /**
     * IMPORTANTE: cualquier job en cola o comando artisan futuro que toque
     * datos de Student/Teacher/AcademicYear/etc. para UNA escuela específica
     * DEBE llamar a set() explícitamente antes de consultar — si no, el
     * scope global no filtra nada y se ve/toca data de TODAS las escuelas.
     */
    public function forget(): void
    {
        $this->institution = null;
    }
}
