<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CrearJuez extends Command
{
    // F4: acceso por usuario simple (admin, juez1, juez2) en lugar de email
    protected $signature = 'torneo:crear
        {usuario : Usuario (sin @)}
        {nombre : Nombre completo}
        {password? : Contraseña (por defecto: secret)}
        {rol? : admin o juez (por defecto: juez)}';

    protected $description = 'Crea o actualiza un usuario juez/admin del torneo';

    public function handle(): int
    {
        $usuario = $this->argument('usuario');
        $nombre = $this->argument('nombre');
        $password = $this->argument('password') ?: 'secret';
        $rol = $this->argument('rol') ?: 'juez';

        if (! in_array($rol, ['admin', 'juez'], true)) {
            $this->error('Rol inválido (use admin o juez).');

            return self::FAILURE;
        }

        $registro = User::updateOrCreate(
            ['usuario' => $usuario],
            [
                'name' => $nombre,
                'password' => Hash::make($password),
                'rol' => $rol,
            ]
        );

        $this->info("Usuario #{$registro->id} {$registro->usuario} ({$registro->rol}) listo.");

        return self::SUCCESS;
    }
}
