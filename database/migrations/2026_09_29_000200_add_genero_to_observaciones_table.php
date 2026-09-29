<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * F13: el género (DAMAS/VARONES) pasa a formar parte de la clave de grupo,
     * por lo que la observación también se guarda por género.
     */
    public function up(): void
    {
        Schema::table('observaciones', function (Blueprint $table) {
            $table->string('genero', 20)->default('')->after('categoria');
            $table->dropUnique('uq_grupo');
            $table->unique(['disciplina', 'categoria', 'genero', 'clase', 'prueba'], 'uq_grupo');
        });

        // índice del grupo de participantes, ahora también por género
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropIndex('idx_grupo');
            $table->index(['disciplina', 'categoria', 'genero', 'clase_variante', 'dsc_prueba'], 'idx_grupo');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropIndex('idx_grupo');
            $table->index(['disciplina', 'categoria', 'clase_variante', 'dsc_prueba'], 'idx_grupo');
        });

        Schema::table('observaciones', function (Blueprint $table) {
            $table->dropUnique('uq_grupo');
            $table->unique(['disciplina', 'categoria', 'clase', 'prueba'], 'uq_grupo');
            $table->dropColumn('genero');
        });
    }
};
