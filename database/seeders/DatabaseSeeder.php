<?php

namespace Database\Seeders;

use App\Models\DatosGen;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {

            // 1. UBIGEO
            // Insertar solo si no existe.
            DB::table('inventario.ubigeo')->insertOrIgnore([
                'id_ubigeo' => '150101',
                'departamento' => 'Lima',
                'provincia' => 'Lima',
                'distrito' => 'Cercado',
            ]);

            // 2. SUCURSAL INICIAL
            $sucursal = Sucursal::query()->firstOrCreate(
                ['nombre' => 'Sede Principal'],
                [
                    'id_ubigeo' => '150101',
                    'direccion' => 'Av. Principal 123',
                    'telefono' => '999999999',
                    'responsable' => 'Administración General',
                    'estado' => true,
                ]
            );

            // 3. DATOS EMPRESARIALES
            // Nunca reiniciar correlativos existentes.
            DatosGen::query()->firstOrCreate(
                ['id_datos_gen' => 1],
                [
                    'id_sucursal' => $sucursal->id_sucursal,
                    'ruc_empresa' => '20689941333',
                    'razon_social' => 'Empresa sin configurar',
                    'nombre_comercial' => 'Sistema comercial',
                    'direccion_fiscal' => 'Av. Principal 123',
                    'igv_porcentaje' => 18.00,
                    'uit_valor' => 5150.00,
                    'serie_boleta' => 'B001',
                    'ult_nro_boleta' => 0,
                    'serie_factura' => 'F001',
                    'ult_nro_factura' => 0,
                    'serie_guia' => 'T001',
                    'ult_nro_guia' => 0,
                    'pie_ticket' => 'Gracias por su compra',
                    'ult_nro_entrada_interna' => 0,
                    'ult_nro_salida_interna' => 0,
                ]
            );

            // 4. TIPOS DE DOCUMENTO
            $tiposDocumento = collect([
                ['codigo_sunat' => '1', 'nombre' => 'DNI', 'longitud_exacta' => 8, 'tipo_persona' => 'NATURAL'],
                ['codigo_sunat' => '4', 'nombre' => 'Carnet de extranjería', 'longitud_exacta' => null, 'tipo_persona' => 'NATURAL'],
                ['codigo_sunat' => '6', 'nombre' => 'RUC', 'longitud_exacta' => 11, 'tipo_persona' => null],
                ['codigo_sunat' => '0', 'nombre' => 'Otros documentos', 'longitud_exacta' => null, 'tipo_persona' => null],
            ])->mapWithKeys(function (array $tipo): array {
                $documento = TipoDocumento::query()->firstOrCreate(
                    ['codigo_sunat' => $tipo['codigo_sunat']],
                    [...$tipo, 'estado' => true]
                );

                return [$tipo['codigo_sunat'] => $documento];
            });

            // 5. PERMISOS DEL SISTEMA
            $permisos = [
                ['codigo' => 'dashboard.ver', 'nombre' => 'Ver dashboard', 'modulo' => 'dashboard'],
                ['codigo' => 'ventas.registrar', 'nombre' => 'Registrar ventas', 'modulo' => 'ventas'],
                ['codigo' => 'ventas.ver', 'nombre' => 'Ver ventas', 'modulo' => 'ventas'],
                ['codigo' => 'ingresos.registrar', 'nombre' => 'Registrar ingresos', 'modulo' => 'ingresos'],
                ['codigo' => 'salidas.registrar', 'nombre' => 'Registrar salidas', 'modulo' => 'salidas'],
                ['codigo' => 'salidas.ver', 'nombre' => 'Ver salidas', 'modulo' => 'salidas'],
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
                // Restaurante (también los crea la migración 2026_11_20_000400 en bases existentes).
                ['codigo' => 'mesas.ver', 'nombre' => 'Ver salón y mesas', 'modulo' => 'restaurante'],
                ['codigo' => 'mesas.gestionar', 'nombre' => 'Configurar áreas y mesas', 'modulo' => 'restaurante'],
                ['codigo' => 'pedidos.registrar', 'nombre' => 'Abrir pedidos y tomar órdenes', 'modulo' => 'restaurante'],
                ['codigo' => 'pedidos.transferir', 'nombre' => 'Transferir, unir y separar cuentas', 'modulo' => 'restaurante'],
                ['codigo' => 'pedidos.anular', 'nombre' => 'Anular pedidos e ítems enviados a cocina', 'modulo' => 'restaurante'],
                ['codigo' => 'carta.gestionar', 'nombre' => 'Gestionar carta, modificadores y agotados', 'modulo' => 'restaurante'],
                // Cocina (también los crea la migración 2026_11_21_000100 en bases existentes).
                ['codigo' => 'cocina.ver', 'nombre' => 'Ver pantalla de cocina', 'modulo' => 'restaurante'],
                ['codigo' => 'cocina.gestionar', 'nombre' => 'Cambiar estado de comandas', 'modulo' => 'restaurante'],
                ['codigo' => 'cocina.configurar', 'nombre' => 'Configurar estaciones de cocina', 'modulo' => 'restaurante'],
                ['codigo' => 'comandas.enviar', 'nombre' => 'Enviar pedidos a cocina', 'modulo' => 'restaurante'],
                ['codigo' => 'pos.descuentos', 'nombre' => 'Aplicar descuentos al cobrar pedidos', 'modulo' => 'restaurante'],
                ['codigo' => 'recetas.gestionar', 'nombre' => 'Configurar recetas y consultar sus costos estimados', 'modulo' => 'restaurante'],
                ['codigo' => 'mermas.registrar', 'nombre' => 'Registrar mermas de insumos', 'modulo' => 'restaurante'],
                ['codigo' => 'reportes.restaurante', 'nombre' => 'Consultar reportes del restaurante', 'modulo' => 'restaurante'],
            ];

            $permisoIds = collect($permisos)->mapWithKeys(
                function (array $permiso): array {
                    $registro = Permiso::query()->firstOrCreate(
                        ['codigo' => $permiso['codigo']],
                        [
                            'nombre' => $permiso['nombre'],
                            'modulo' => $permiso['modulo'],
                            'estado' => true,
                        ]
                    );

                    return [$registro->codigo => $registro->id_permiso];
                }
            );

            // 6. ROLES DEL SISTEMA
            // Los roles no generan usuarios automáticamente.
            $todos = array_keys($permisoIds->toArray());

            $rolesConfig = [
                'Administrador General' => $todos,
                'Administrador' => $todos,
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
                    'salidas.registrar',
                    'salidas.ver',
                    'kardex.ver',
                    'inventario.ver',
                    'inventario.gestionar',
                    'productos.ver',
                    'productos.gestionar',
                    'proveedores.ver',
                    'proveedores.gestionar',
                ],
            ];

            $roles = [];

            foreach ($rolesConfig as $nombreRol => $codigos) {
                $rol = Rol::query()->firstOrCreate(
                    ['nombre' => $nombreRol],
                    [
                        'descripcion' => "Rol de $nombreRol",
                        'estado' => true,
                    ]
                );

                // Configurar permisos solo en roles nuevos.
                // Conservar personalizaciones existentes.
                if ($rol->wasRecentlyCreated) {
                    $ids = collect($codigos)
                        ->map(fn (string $codigo) => $permisoIds[$codigo])
                        ->all();

                    $rol->permisos()->sync($ids);
                }

                $roles[$nombreRol] = $rol;
            }

            // 7. ÚNICO ADMINISTRADOR INICIAL
            $rolAdministrador = $roles['Administrador General'];

            // Si ya existe un administrador general, no
            // crear otro ni modificar sus credenciales.
            $existeAdministrador = User::query()
                ->where('id_rol', $rolAdministrador->id_rol)
                ->exists();

            if ($existeAdministrador) {
                return;
            }

            $email = trim((string) env('SEED_ADMIN_EMAIL', ''));
            $password = (string) env('SEED_ADMIN_PASSWORD', '');

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'Configura SEED_ADMIN_EMAIL con un correo válido.'
                );
            }

            if (strlen($password) < 16) {
                throw new RuntimeException(
                    'Configura SEED_ADMIN_PASSWORD con mínimo 16 caracteres.'
                );
            }

            // Evitar asumir el control de una cuenta
            // preexistente con el mismo correo.
            if (User::query()->where('email', $email)->exists()) {
                throw new RuntimeException(
                    'El correo configurado ya pertenece a otro usuario.'
                );
            }

            $docDniId = $tiposDocumento->get('1')->id_tipo_documento;

            User::query()->create([
                'email' => $email,
                'nombres' => 'Administrador General',
                'id_rol' => $rolAdministrador->id_rol,
                'id_sucursal' => $sucursal->id_sucursal,
                'id_tipo_documento' => $docDniId,
                'id_ubigeo' => '150101',
                'num_documento' => '11111111',
                'password' => Hash::make($password),
                'estado' => true,
            ]);
        });
    }
}
