<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            // Materias como Inglés o Educación Física requieren un docente específico
            // asignado por materia. Las demás ("generales") las da automáticamente el
            // maestro de grado al asignarle su aula, sin necesidad de elegirlas una a una.
            $table->boolean('is_specialized')->default(false)->after('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('is_specialized');
        });
    }
};
