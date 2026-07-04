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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique('enrollments_student_id_academic_year_id_unique');
            $table->date('status_date')->nullable()->after('status');
            $table->string('status_reason')->nullable()->after('status_date');
        });

        // Un estudiante solo puede tener UNA matrícula activa por año escolar,
        // pero sí puede acumular matrículas retiradas/trasladadas ese mismo año
        // (para permitir rehabilitación). No hay forma portable de expresar un
        // índice único parcial con Schema::unique(), así que se usa SQL crudo.
        DB::statement("
            CREATE UNIQUE INDEX enrollments_active_student_year_unique
            ON enrollments (student_id, academic_year_id)
            WHERE status = 'activo'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS enrollments_active_student_year_unique');

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['status_date', 'status_reason']);
            $table->unique(['student_id', 'academic_year_id']);
        });
    }
};
