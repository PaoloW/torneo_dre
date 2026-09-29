<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // F4: login por usuario simple (admin, juez1, juez2) en lugar de email
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario', 60)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        // backfill: usuario = parte antes de la @ del email existente
        DB::statement("UPDATE users SET usuario = SUBSTRING_INDEX(email, '@', 1) WHERE usuario IS NULL AND email IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['usuario']);
            $table->dropColumn('usuario');
        });
    }
};
