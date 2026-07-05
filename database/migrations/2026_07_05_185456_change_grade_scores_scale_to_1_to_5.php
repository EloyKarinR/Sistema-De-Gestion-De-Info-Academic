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
        // Las notas existentes están en escala 0-100 y no tienen una
        // conversión válida a la escala real de Panamá (1.0-5.0), así que
        // se limpian en vez de intentar traducirlas.
        DB::table('grade_scores')->delete();

        Schema::table('grade_scores', function (Blueprint $table) {
            $table->decimal('score', 2, 1)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grade_scores', function (Blueprint $table) {
            $table->decimal('score', 4, 1)->change();
        });
    }
};
