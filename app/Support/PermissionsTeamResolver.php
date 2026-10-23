<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\PermissionsTeamResolver as PermissionsTeamResolverContract;

/**
 * Le dice a Spatie en qué equipo (escuela) evaluar roles y permisos.
 *
 * Igual que TenantContext: si hay un usuario autenticado se resuelve EN VIVO
 * desde su current_team_id, así ninguna ruta depende de que un middleware
 * haya corrido antes. Sin usuario (seeders, consola) se usa lo fijado con
 * setPermissionsTeamId(). Si no hay ni lo uno ni lo otro, nadie tiene roles.
 *
 * Para ASIGNAR un rol no se depende de esto: usar User::assignRoleInTeam().
 */
class PermissionsTeamResolver implements PermissionsTeamResolverContract
{
    protected int|string|null $teamId = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        if (Auth::hasUser()) {
            return Auth::user()->current_team_id;
        }

        return $this->teamId;
    }
}
