<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    // F4: 3 usuarios iniciales con contraseña alfanumérica aleatoria de 8 caracteres
    public function run(): void
    {
        $usuarios = [
            ['usuario' => 'admin', 'name' => 'Admin', 'rol' => 'admin'],
            ['usuario' => 'juez1', 'name' => 'Juez 1', 'rol' => 'juez'],
            ['usuario' => 'juez2', 'name' => 'Juez 2', 'rol' => 'juez'],
        ];

        foreach ($usuarios as $datos) {
            // $password = $this->passwordAleatoria();
            $password = 'torneo26';

            User::updateOrCreate(
                ['usuario' => $datos['usuario']],
                [
                    'name' => $datos['name'],
                    'rol' => $datos['rol'],
                    'password' => Hash::make($password),
                ]
            );

            $this->command->info("Usuario: {$datos['usuario']} | Contraseña: {$password} | Rol: {$datos['rol']}");
        }
    }

    // alfanumérico (letras y dígitos), 8 caracteres
    private function passwordAleatoria(int $longitud = 8): string
    {
        $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $password = '';
        $total = strlen($caracteres);

        for ($i = 0; $i < $longitud; $i++) {
            $password .= $caracteres[random_int(0, $total - 1)];
        }

        return $password;
    }
}
