<?php

namespace Database\Seeders;

use App\Models\Accion;
use App\Models\Modulo;
use App\Models\Opcion;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReportesGeneralesSeeder extends Seeder
{
    /**
     * Registra exclusivamente módulo, opciones y acción admitida; no concede
     * permisos a roles compartidos. Desplaza una sola vez los grupos posteriores a
     * Producción al insertar el módulo, conservando identidades y personalización.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $modulos = Modulo::orderBy('id')->lockForUpdate()->get();
            $modulo = $modulos->firstWhere('nombre', 'Reportes Generales');
            if (! $modulo) {
                $produccion = $modulos->firstWhere('nombre', 'Producción');
                if (! $produccion) {
                    throw new RuntimeException('Debe existir el módulo Producción antes de registrar Reportes Generales.');
                }
                Modulo::where('orden', '>', $produccion->orden)->increment('orden');
                $modulo = Modulo::create(['nombre' => 'Reportes Generales', 'icono' => '📊', 'orden' => $produccion->orden + 1, 'estado' => true]);
            }
            $ver = Accion::where('clave', 'ver')->firstOrFail();
            $orden = 1;
            foreach (Opcion::REPORTES as $ruta => [$nombre, $icono]) {
                $opcion = Opcion::firstOrCreate(['ruta' => $ruta], ['modulo_id' => $modulo->id, 'nombre' => $nombre, 'icono' => $icono, 'orden' => $orden++, 'estado' => true]);
                if ((int) $opcion->modulo_id !== (int) $modulo->id) {
                    throw new RuntimeException('Una ruta de reportes ya pertenece a otro módulo. No se cambió su configuración.');
                }
                $opcion->acciones()->syncWithoutDetaching([$ver->id]);
            }
        });
    }

    /**
     * Otorga las ocho consultas y descargas a una cuenta activa por ID. Crea un
     * rol exclusivo y conserva los demás roles/permisos. Rechaza roles compartidos,
     * inactivos o con permisos ajenos. Requiere autorización administrativa explícita;
     * run() por sí solo nunca modifica accesos de usuarios.
     */
    public function otorgarAccesoAUsuario(int $usuarioId): void
    {
        DB::transaction(function () use ($usuarioId) {
            $usuario = User::whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            if (! $usuario->estado) {
                throw new RuntimeException('No se puede habilitar reportería a un usuario inactivo.');
            }
            $opciones = Opcion::with('modulo')->whereIn('ruta', array_keys(Opcion::REPORTES))->get();
            $ver = Accion::where('clave', 'ver')->where('estado', true)->firstOrFail();
            if ($opciones->count() !== count(Opcion::REPORTES) || $opciones->contains(fn ($o) => ! $o->estado || ! $o->modulo->estado)) {
                throw new RuntimeException('Las ocho opciones y su módulo deben estar registrados y activos.');
            }
            $rol = Rol::firstOrCreate(['nombre' => 'Reportes Generales · usuario '.$usuarioId],
                ['descripcion' => 'Consulta y descarga CSV de Reportes Generales para una cuenta específica.', 'estado' => true]);
            $rol = Rol::whereKey($rol->id)->lockForUpdate()->firstOrFail();
            $compartido = DB::table('roles_usuarios')->where('rol_id', $rol->id)->where('user_id', '<>', $usuarioId)->exists();
            $ajenos = DB::table('roles_opciones_acciones')->where('rol_id', $rol->id)
                ->where(fn ($q) => $q->whereNotIn('opcion_id', $opciones->pluck('id'))->orWhere('accion_id', '<>', $ver->id))->exists();
            if (! $rol->estado || $compartido || $ajenos) {
                throw new RuntimeException('El rol exclusivo tiene una configuración incompatible; no se alteraron accesos.');
            }
            $ahora = now();
            foreach ($opciones as $opcion) {
                DB::table('roles_opciones_acciones')->insertOrIgnore(['rol_id' => $rol->id, 'opcion_id' => $opcion->id,
                    'accion_id' => $ver->id, 'created_at' => $ahora, 'updated_at' => $ahora]);
            }
            $usuario->roles()->syncWithoutDetaching([$rol->id]);
        });
    }
}
