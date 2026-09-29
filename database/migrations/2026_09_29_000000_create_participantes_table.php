<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ÚNICA tabla de clasificados, plana, una fila por participante
        Schema::create('participantes', function (Blueprint $table) {
            $table->bigIncrements('id');
            // columnas originales del CSV
            $table->integer('id_anio')->nullable();
            $table->bigInteger('id_ficha_evento')->nullable();
            $table->string('dre', 100)->nullable();
            $table->string('ugel', 100)->nullable();
            $table->string('regionie', 60)->nullable();
            $table->string('provinciaie', 80)->nullable();
            $table->string('distritoie', 80)->nullable();
            $table->string('cod_mod', 20)->nullable();
            $table->string('cod_mod_origen', 20)->nullable();
            $table->string('ie', 150)->nullable();
            $table->string('nivel_educativo', 40)->nullable();
            $table->string('grado', 30)->nullable();
            $table->string('categoria', 50)->nullable();      // CATEGORÍA A/B/C...
            $table->string('disciplina', 80)->nullable();     // VOLEY PLAYA, ATLETISMO...
            $table->string('clase_variante', 100)->nullable();// CLASE_VARIANTE_DE_DISCIPLINA
            $table->string('genero', 20)->nullable();         // VARONES / DAMAS
            $table->string('nombre_etapa', 80)->nullable();
            $table->string('tipo_participante', 20)->nullable(); // TITULAR / SUSTITUTO
            $table->string('tipo_documento', 20)->nullable();
            $table->string('numdoc_participante', 20)->nullable();
            $table->string('apepat', 60)->nullable();
            $table->string('apemat', 60)->nullable();
            $table->string('nombres', 80)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->dateTime('fecha_registro')->nullable();
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->string('estado_ficha', 30)->nullable();
            // 3 columnas extra (atletismo, natación, paratletismo, taekwondo)
            $table->string('dsc_prueba', 100)->nullable();
            $table->string('metrica', 50)->nullable();
            $table->string('marca', 30)->nullable();
            // resultado del juez (autoguardado): NULL = sin asignar; duplicados = EMPATE
            $table->integer('puesto')->nullable();

            $table->index(['disciplina', 'categoria', 'clase_variante', 'dsc_prueba'], 'idx_grupo');
            $table->index('numdoc_participante', 'idx_doc');
        });

        // observación opcional: 1 fila por grupo (upsert)
        Schema::create('observaciones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('disciplina', 80);
            $table->string('categoria', 50);
            $table->string('clase', 100);
            $table->string('prueba', 100)->default(''); // '' cuando no aplica
            $table->text('texto')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['disciplina', 'categoria', 'clase', 'prueba'], 'uq_grupo');
        });

        // auditoría simple: quién modificó el puesto y cuándo (1 fila por cambio real)
        Schema::create('puesto_auditoria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('participante_id');
            $table->integer('puesto_anterior')->nullable(); // NULL = sin asignar
            $table->integer('puesto_nuevo')->nullable();    // NULL = limpiado
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nombre', 120)->nullable(); // copia del nombre del juez
            $table->timestamp('created_at')->nullable()->useCurrent();

            $table->index(['participante_id', 'created_at'], 'idx_part');
        });

        // users estándar de Laravel + rol
        DB::statement("ALTER TABLE users ADD COLUMN rol ENUM('admin','juez') NOT NULL DEFAULT 'juez'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP COLUMN rol');
        Schema::dropIfExists('puesto_auditoria');
        Schema::dropIfExists('observaciones');
        Schema::dropIfExists('participantes');
    }
};
