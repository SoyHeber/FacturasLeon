<?php

namespace Database\Seeders;

use App\Models\Accion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registra acciones, módulos y opciones del sistema y le da todos los permisos
 * al rol Administrador. Es idempotente: se puede correr cada vez que se agrega
 * una opción nueva sin duplicar datos. Conserva los metadatos configurados desde
 * la pantalla y ajusta los permisos a las operaciones disponibles.
 */
class SeguridadSeeder extends Seeder
{
    private const ACCIONES = [
        ['nombre' => 'Ver', 'clave' => 'ver', 'descripcion' => 'Listar y ver el detalle de registros.'],
        ['nombre' => 'Crear', 'clave' => 'crear', 'descripcion' => 'Crear registros nuevos.'],
        ['nombre' => 'Modificar', 'clave' => 'modificar', 'descripcion' => 'Editar registros existentes.'],
        ['nombre' => 'Eliminar', 'clave' => 'eliminar', 'descripcion' => 'Activar o inactivar registros.'],
    ];

    // Módulo => [icono, [ruta => [nombre, icono]]]
    private const MODULOS = [
        'Catálogos' => ['📚', [
            'categorias' => ['Categorías', '📂'],
            'marcas' => ['Marcas', '🏷️'],
            'productos' => ['Productos', '💍'],
            'paises' => ['Países', '🌎'],
            'departamentos' => ['Departamentos', '🗺️'],
            'municipios' => ['Municipios', '📍'],
            'tipos_documento' => ['Tipos de Documento', '📄'],
        ]],
        'Operaciones' => ['📦', [
            'inventarios' => ['Inventarios', '📦'],
            'movimientos_inventario' => ['Movimientos Inventario', '🔄'],
            'compras' => ['Compras', '🛒'],
            'detalles_compra' => ['Detalles Compra', '📋'],
            'inventarios_compra' => ['Inventarios Compra', '🧱'],
            'movimientos_inventario_compra' => ['Movimientos Inventario Compra', '🔁'],
        ]],
        'Producción' => ['🛠️', [
            'materiales_producto' => ['Materiales Producto', '🧩'],
            'producciones' => ['Producciones', '🛠️'],
            'direcciones' => ['Direcciones', '🏘️'],
            'metodos_pago' => ['Métodos de Pago', '💳'],
            'tipos_identificacion' => ['Tipos de Identificación', '🪪'],
        ]],
        'Personas' => ['👥', [
            'proveedores' => ['Proveedores', '🚚'],
            'clientes' => ['Clientes', '👥'],
        ]],
        'Seguridad' => ['🔒', [
            'users' => ['Usuarios', '👤'],
            'roles' => ['Roles', '🛡️'],
            'modulos' => ['Módulos', '🧭'],
            'opciones' => ['Opciones', '📑'],
            'acciones' => ['Acciones', '⚙️'],
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::ACCIONES as $accion) {
                Accion::firstOrCreate(['clave' => $accion['clave']], $accion);
            }

            $acciones = Accion::whereIn('clave', array_column(self::ACCIONES, 'clave'))->get()->keyBy('clave');
            $accionIds = $acciones->pluck('id')->all();

            $ordenModulo = 1;

            foreach (self::MODULOS as $nombreModulo => [$iconoModulo, $opciones]) {
                $modulo = Modulo::firstOrCreate(
                    ['nombre' => $nombreModulo],
                    ['icono' => $iconoModulo, 'orden' => $ordenModulo]
                );

                $ordenOpcion = 1;

                foreach ($opciones as $ruta => [$nombreOpcion, $iconoOpcion]) {
                    $opcion = Opcion::firstOrCreate(
                        ['ruta' => $ruta],
                        [
                            'modulo_id' => $modulo->id,
                            'nombre' => $nombreOpcion,
                            'icono' => $iconoOpcion,
                            'orden' => $ordenOpcion,
                        ]
                    );

                    if (isset(Opcion::ACCIONES_POR_RUTA[$ruta])) {
                        $admitidas = $acciones->filter(
                            fn ($accion) => in_array($accion->clave, Opcion::ACCIONES_POR_RUTA[$ruta], true)
                        )->pluck('id')->all();

                        // Retira únicamente los permisos de operaciones que ya no existen.
                        DB::table('roles_opciones_acciones')->where('opcion_id', $opcion->id)
                            ->whereNotIn('accion_id', $admitidas)->delete();
                        $opcion->acciones()->sync($admitidas);
                    } else {
                        $opcion->acciones()->syncWithoutDetaching($accionIds);
                    }

                    $ordenOpcion++;
                }

                $ordenModulo++;
            }

            // Rol Administrador con todas las combinaciones opción + acción admitidas
            $administrador = Rol::firstOrCreate(
                ['nombre' => 'Administrador'],
                ['descripcion' => 'Acceso total al sistema.']
            );

            $ahora = now();

            $filas = DB::table('opciones_acciones')
                ->get()
                ->map(fn ($fila) => [
                    'rol_id' => $administrador->id,
                    'opcion_id' => $fila->opcion_id,
                    'accion_id' => $fila->accion_id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])
                ->all();

            DB::table('roles_opciones_acciones')->insertOrIgnore($filas);

            // El primer usuario registrado queda como administrador para no perder el acceso
            $primerUsuario = User::orderBy('id')->first();

            if ($primerUsuario) {
                $primerUsuario->roles()->syncWithoutDetaching([$administrador->id]);
            }
        });
    }
}
