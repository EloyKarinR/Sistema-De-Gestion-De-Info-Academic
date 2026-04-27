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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registered_by')->constrained('users')->cascadeOnDelete();
            $table->date('enrollment_date');
            $table->enum('status', ['activo', 'retirado', 'trasladado'])->default('activo');
            $table->enum('enrollment_type', ['nuevo_ingreso', 'promovido', 'rehabilitacion', 'traslado'])->default('nuevo_ingreso');
            $table->string('receipt_number')->nullable();
            $table->boolean('doc_cedula_student')->default(false);
            $table->boolean('doc_cedula_guardian')->default(false);
            $table->boolean('doc_boletin')->default(false);
            $table->boolean('doc_foto')->default(false);
            $table->boolean('doc_address')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
