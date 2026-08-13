<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('id')->unique()->constrained()->restrictOnDelete();
        });

        // Backfill: hoy existe exactamente una Institution y un Team no-personal
        // en producción — se enlazan directamente.
        $institutionId = DB::table('institutions')->orderBy('id')->value('id');
        $teamId = DB::table('teams')->where('is_personal', false)->orderBy('id')->value('id');

        if ($institutionId && $teamId) {
            DB::table('institutions')->where('id', $institutionId)->update(['team_id' => $teamId]);
        }

        DB::statement('ALTER TABLE institutions ALTER COLUMN team_id SET NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
