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
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropUnique(['cedula']);
            $table->foreignId('institution_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $institutionId = DB::table('institutions')->orderBy('id')->value('id');

        if ($institutionId) {
            DB::table('teachers')->update(['institution_id' => $institutionId]);
        }

        DB::statement('ALTER TABLE teachers ALTER COLUMN institution_id SET NOT NULL');

        Schema::table('teachers', function (Blueprint $table) {
            $table->unique(['institution_id', 'cedula']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropUnique(['teachers_institution_id_cedula_unique']);
            $table->dropConstrainedForeignId('institution_id');
            $table->unique(['cedula']);
        });
    }
};
