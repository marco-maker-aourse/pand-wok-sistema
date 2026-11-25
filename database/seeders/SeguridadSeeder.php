<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Permisos, roles y el único administrador inicial (SEED_ADMIN_EMAIL / SEED_ADMIN_PASSWORD).
 * Es repetible: no duplica permisos ni roles, no cambia los permisos de un rol existente
 * y no crea un segundo administrador.
 */
class SeguridadSeeder extends Seeder
{
    /** Marca para "todos los permisos". */
    private const TODOS = '*';

    private const PERMISOS = [
        ['codigo' => 'dashboard.ver', 'nombre' => 'Ver dashboard', 'modulo' => 'dashboard'],
        ['codigo' => 'ventas.registrar', 'nombre' => 'Registrar ventas', 'modulo' => 'ventas'],
        ['codigo' => 'ventas.ver', 'nombre' => 'Ver ventas', 'modulo' => 'ventas'],
        ['codigo' => 'ingresos.registrar', 'nombre' => 'Registrar ingresos', 'modulo' => 'ingresos'],
        ['codigo' => 'inventario.ver', 'nombre' => 'Ver inventario', 'modulo' => 'inventario'],
        ['codigo' => 'kardex.ver', 'nombre' => 'Ver kardex', 'modulo' => 'inventario'],
        ['codigo' => 'inventario.gestionar', 'nombre' => 'Ajustar existencias y precios', 'modulo' => 'inventario'],
        ['codigo' => 'productos.ver', 'nombre' => 'Ver productos', 'modulo' => 'productos'],
        ['codigo' => 'productos.gestionar', 'nombre' => 'Gestionar productos', 'modulo' => 'productos'],
        ['codigo' => 'clientes.ver', 'nombre' => 'Ver clientes', 'modulo' => 'clientes'],
        ['codigo' => 'clientes.gestionar', 'nombre' => 'Gestionar clientes', 'modulo' => 'clientes'],
        ['codigo' => 'proveedores.ver', 'nombre' => 'Ver proveedores', 'modulo' => 'proveedores'],
        ['codigo' => 'proveedores.gestionar', 'nombre' => 'Gestionar proveedores', 'modulo' => 'proveedores'],
        ['codigo' => 'clientes.editar_documento', 'nombre' => 'Editar documento de cliente', 'modulo' => 'clientes'],
        ['codigo' => 'proveedores.editar_documento', 'nombre' => 'Editar RUC de proveedor', 'modulo' => 'proveedores'],
        ['codigo' => 'metodos_pago.gestionar', 'nombre' => 'Gestionar métodos de pago', 'modulo' => 'configuracion'],
        ['codigo' => 'tipos_comprobante.ver', 'nombre' => 'Ver tipos de comprobante', 'modulo' => 'configuracion'],
        ['codigo' => 'tipos_comprobante.gestionar', 'nombre' => 'Gestionar tipos de comprobante', 'modulo' => 'configuracion'],
        ['codigo' => 'tipos_documento.gestionar', 'nombre' => 'Gestionar tipos de documento', 'modulo' => 'configuracion'],
        ['codigo' => 'usuarios.ver', 'nombre' => 'Ver usuarios', 'modulo' => 'seguridad'],
        ['codigo' => 'usuarios.gestionar', 'nombre' => 'Gestionar usuarios y roles', 'modulo' => 'seguridad'],
        ['codigo' => 'reportes.ver', 'nombre' => 'Ver reportes', 'modulo' => 'reportes'],
        // Restaurante.
        ['codigo' => 'mesas.ver', 'nombre' => 'Ver salón y mesas', 'modulo' => 'restaurante'],
        ['codigo' => 'mesas.gestionar', 'nombre' => 'Configurar áreas y mesas', 'modulo' => 'restaurante'],
        ['codigo' => 'pedidos.registrar', 'nombre' => 'Abrir pedidos y tomar órdenes', 'modulo' => 'restaurante'],
        ['codigo' => 'pedidos.transferir', 'nombre' => 'Transferir, unir y separar cuentas', 'modulo' => 'restaurante'],
        ['codigo' => 'pedidos.anular', 'nombre' => 'Anular pedidos e ítems enviados a cocina', 'modulo' => 'restaurante'],
        ['codigo' => 'carta.gestionar', 'nombre' => 'Gestionar carta, modificadores y agotados', 'modulo' => 'restaurante'],
        // Cocina.
        ['codigo' => 'cocina.ver', 'nombre' => 'Ver pantalla de cocina', 'modulo' => 'restaurante'],
        ['codigo' => 'cocina.gestionar', 'nombre' => 'Cambiar estado de comandas', 'modulo' => 'restaurante'],
        ['codigo' => 'cocina.configurar', 'nombre' => 'Configurar estaciones de cocina', 'modulo' => 'restaurante'],
        ['codigo' => 'comandas.enviar', 'nombre' => 'Enviar pedidos a cocina', 'modulo' => 'restaurante'],
        ['codigo' => 'pos.descuentos', 'nombre' => 'Aplicar descuentos al cobrar pedidos', 'modulo' => 'restaurante'],
        ['codigo' => 'recetas.gestionar', 'nombre' => 'Configurar recetas y consultar sus costos estimados', 'modulo' => 'restaurante'],
        ['codigo' => 'mermas.registrar', 'nombre' => 'Registrar mermas de insumos y productos', 'modulo' => 'restaurante'],
        ['codigo' => 'reportes.restaurante', 'nombre' => 'Consultar reportes del restaurante', 'modulo' => 'restaurante'],
    ];

    /** Permisos de cada rol (solo se asignan al crear el rol; luego se editan en pantalla). */
    private const ROLES = [
        'Administrador General' => self::TODOS,
        'Administrador' => self::TODOS,
        'Cajero' => [
            'mesas.ver',
            'dashboard.ver',
            'ventas.registrar',
            'clientes.ver',
            'clientes.gestionar',
            'productos.ver',
        ],
        'Vendedor' => [
            'dashboard.ver',
            'ventas.registrar',
            'clientes.ver',
            'clientes.gestionar',
            'productos.ver',
            'kardex.ver',
            'inventario.ver',
        ],
        // Toma pedidos en el salón; transferir y anular quedan para supervisión.
        'Mozo' => [
            'dashboard.ver',
            'mesas.ver',
            'pedidos.registrar',
            'comandas.enviar',
            'productos.ver',
            'clientes.ver',
        ],
        // Pantalla de cocina (KDS): ve comandas y cambia su estado.
        'Cocina' => [
            'cocina.ver',
            'cocina.gestionar',
        ],
        'Almacenero' => [
            'mermas.registrar',
            'dashboard.ver',
            'ingresos.registrar',
            'kardex.ver',
            'inventario.ver',
            'inventario.gestionar',
            'productos.ver',
            'productos.gestionar',
            'proveedores.ver',
            'proveedores.gestionar',
        ],
    ];

    public function run(): void
    {
        $permisoIds = collect(self::PERMISOS)->mapWithKeys(function (array $permiso): array {
            $registro = Permiso::query()->firstOrCreate(
                ['codigo' => $permiso['codigo']],
                ['nombre' => $permiso['nombre'], 'modulo' => $permiso['modulo'], 'estado' => true],
            );

            return [$registro->codigo => $registro->id_permiso];
        });

        $roles = [];
        foreach (self::ROLES as $nombreRol => $codigos) {
            $rol = Rol::query()->firstOrCreate(['nombre' => $nombreRol], ['descripcion' => "Rol de {$nombreRol}", 'estado' => true]);
            // Solo en roles nuevos: se conservan las personalizaciones existentes.
            if ($rol->wasRecentlyCreated) {
                $rol->permisos()->sync($codigos === self::TODOS ? $permisoIds->values()->all() : $permisoIds->only($codigos)->values()->all());
            }
            $roles[$nombreRol] = $rol;
        }

        $this->crearAdministrador($roles['Administrador General']);
    }

    private function crearAdministrador(Rol $rolAdministrador): void
    {
        // Si ya existe un administrador general, no se crea otro ni se tocan sus credenciales.
        if (User::query()->where('id_rol', $rolAdministrador->id_rol)->exists()) {
            return;
        }

        $email = trim((string) env('SEED_ADMIN_EMAIL', ''));
        $password = (string) env('SEED_ADMIN_PASSWORD', '');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configura SEED_ADMIN_EMAIL con un correo válido.');
        }
        if (strlen($password) < 16) {
            throw new RuntimeException('Configura SEED_ADMIN_PASSWORD con mínimo 16 caracteres.');
        }
        // Evita tomar el control de una cuenta existente con el mismo correo.
        if (User::query()->where('email', $email)->exists()) {
            throw new RuntimeException('El correo configurado ya pertenece a otro usuario.');
        }

        User::query()->create([
            'email' => $email,
            'nombres' => 'Administrador General',
            'id_rol' => $rolAdministrador->id_rol,
            'id_sucursal' => Sucursal::query()->orderBy('id_sucursal')->value('id_sucursal'),
            'id_tipo_documento' => TipoDocumento::query()->where('codigo_sunat', TipoDocumento::DNI)->value('id_tipo_documento'),
            'num_documento' => '11111111',
            'password' => Hash::make($password),
            'estado' => true,
        ]);
    }
}
