<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'sistema:create-admin {email : Correo del administrador} {--name= : Nombre completo} {--branch= : ID de sucursal asignada}';
    protected $description = 'Crea o actualiza de forma interactiva el primer administrador';

    public function handle(): int
    {
        $role = Rol::query()->where('nombre', 'Administrador')->where('estado', true)->first();
        if (! $role) { $this->error('Ejecute primero: php artisan db:seed --class=SecuritySeeder'); return self::FAILURE; }
        $branchId = $this->option('branch') ?: $this->ask('ID de sucursal');
        if (! Sucursal::query()->whereKey($branchId)->where('estado', true)->exists()) { $this->error('La sucursal indicada no existe o está inactiva.'); return self::FAILURE; }
        $password = $this->secret('Contraseña');
        if (strlen((string) $password) < 12) { $this->error('La contraseña debe tener al menos 12 caracteres.'); return self::FAILURE; }

        User::query()->updateOrCreate(['email' => $this->argument('email')], [
            'nombres' => $this->option('name') ?: $this->ask('Nombres completos'),
            'id_rol' => $role->id_rol, 'id_sucursal' => $branchId, 'password' => $password, 'estado' => true,
        ]);
        $this->info('Administrador creado o actualizado.');
        return self::SUCCESS;
    }
}
