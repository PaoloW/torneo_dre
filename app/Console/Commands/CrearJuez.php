<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CrearJuez extends Command
{
    protected $signature = 'torneo:crear
        {email : Correo del usuario}
        {nombre : Nombre completo}
        {password? : Contraseña (por defecto: secret)}
        {rol? : admin o juez (por defecto: juez)}';

    protected $description = 'Crea o actualiza un usuario juez/admin del torneo';

    public function handle(): int
    {
        $email = $this->argument('email');
        $nombre = $this->argument('nombre');
        $password = $this->argument('password') ?: 'secret';
        $rol = $this->argument('rol') ?: 'juez';

        if (! in_array($rol, ['admin', 'juez'], true)) {
            $this->error('Rol inválido (use admin o juez).');

            return self::FAILURE;
        }

        $usuario = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $nombre,
                'password' => Hash::make($password),
                'rol' => $rol,
            ]
        );

        $this->info("Usuario #{$usuario->id} {$usuario->email} ({$usuario->rol}) listo.");

        return self::SUCCESS;
    }
}
