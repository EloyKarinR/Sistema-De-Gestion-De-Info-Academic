<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activa los equipos de Spatie (permission.teams = true). La migración
 * original crea las tablas siempre sin equipos, y esta agrega team_id
 * tanto en bases nuevas como en producción.
 *
 * Los roles (admin, secretaria…) siguen siendo globales (roles.team_id null);
 * lo que pasa a ser por equipo es la ASIGNACIÓN de un rol a un usuario.
 */
return new class extends Migration
{
    /** @var array<string, string> tabla pivote => columna del rol/permiso */
    private array $pivots = [
        'model_has_roles' => 'role_id',
        'model_has_permissions' => 'permission_id',
    ];

    private array $primaryNames = [
        'model_has_roles' => 'model_has_roles_role_model_type_primary',
        'model_has_permissions' => 'model_has_permissions_permission_model_type_primary',
    ];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->after('id');
            $table->index('team_id', 'roles_team_foreign_key_index');
        });

        foreach ($this->pivots as $pivot => $column) {
            Schema::table($pivot, function (Blueprint $table) use ($pivot) {
                $table->dropPrimary($this->primaryNames[$pivot]);
                $table->unsignedBigInteger('team_id')->nullable()->first();
            });

            $this->backfill($pivot, $column);

            Schema::table($pivot, function (Blueprint $table) use ($pivot, $column) {
                $table->unsignedBigInteger('team_id')->nullable(false)->change();
                $table->index('team_id', "{$pivot}_team_foreign_key_index");
                $table->primary(['team_id', $column, 'model_id', 'model_type'], $this->primaryNames[$pivot]);
            });
        }
    }

    /**
     * Cada asignación existente se copia a cada escuela (equipo no personal)
     * de la que el usuario es miembro — así nadie pierde acceso al
     * desplegar. Si no es miembro de ninguna, se usa su equipo actual; si
     * tampoco tiene, la asignación se descarta (no tendría dónde aplicar).
     */
    private function backfill(string $pivot, string $column): void
    {
        $rows = DB::table($pivot)->whereNull('team_id')->get();

        foreach ($rows as $row) {
            $teamIds = DB::table('team_members')
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('team_members.user_id', $row->model_id)
                ->where('teams.is_personal', false)
                ->pluck('teams.id');

            if ($teamIds->isEmpty()) {
                $teamIds = DB::table('users')->where('id', $row->model_id)->whereNotNull('current_team_id')->pluck('current_team_id');
            }

            foreach ($teamIds as $teamId) {
                DB::table($pivot)->insertOrIgnore([
                    'team_id' => $teamId,
                    $column => $row->{$column},
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                ]);
            }
        }

        DB::table($pivot)->whereNull('team_id')->delete();
    }

    public function down(): void
    {
        foreach ($this->pivots as $pivot => $column) {
            Schema::table($pivot, function (Blueprint $table) use ($pivot) {
                $table->dropPrimary($this->primaryNames[$pivot]);
                $table->dropIndex("{$pivot}_team_foreign_key_index");
            });

            // Sin equipos, un usuario tiene cada rol una sola vez: se queda con
            // la primera asignación y descarta las de otras escuelas.
            DB::statement("DELETE FROM {$pivot} a USING {$pivot} b
                WHERE a.{$column} = b.{$column} AND a.model_id = b.model_id AND a.model_type = b.model_type
                AND a.team_id > b.team_id");

            Schema::table($pivot, function (Blueprint $table) use ($pivot, $column) {
                $table->dropColumn('team_id');
                $table->primary([$column, 'model_id', 'model_type'], $this->primaryNames[$pivot]);
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex('roles_team_foreign_key_index');
            $table->dropColumn('team_id');
        });
    }
};
