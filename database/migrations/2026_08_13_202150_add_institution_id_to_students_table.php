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
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['cedula']);
            $table->foreignId('institution_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $institutionId = DB::table('institutions')->orderBy('id')->value('id');

        if ($institutionId) {
            DB::table('students')->update(['institution_id' => $institutionId]);
        }

        DB::statement('ALTER TABLE students ALTER COLUMN institution_id SET NOT NULL');

        Schema::table('students', function (Blueprint $table) {
            $table->unique(['institution_id', 'cedula']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['students_institution_id_cedula_unique']);
            $table->dropConstrainedForeignId('institution_id');
            $table->unique(['cedula']);
        });
    }
};
