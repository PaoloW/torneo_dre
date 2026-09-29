<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // F4: usuarios iniciales (admin, juez1, juez2) con contraseñas aleatorias
        $this->call(UsuariosSeeder::class);
    }
}
