<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DistribucionNavegacionSeeder extends Seeder
{
    /**
     * Aplica la distribución del menú a las opciones ya registradas, sin crear permisos.
     * Bloquea módulos y opciones en orden de ID y guarda juntos los nombres, posiciones y
     * agrupaciones. Conserva IDs, rutas, iconos personalizados, estados y asignaciones a roles.
     * Los módulos faltantes se crean activos; las opciones ausentes quedan a cargo del
     * seeder general de instalación. No se modifican catálogos ni registros operativos.
     * Rechaza mover una opción activa entre módulos con distinta disponibilidad, porque
     * eso cambiaría sus permisos efectivos. Cualquier conflicto revierte la distribución.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $rutas = [];
            foreach (SeguridadSeeder::MODULOS as [$icono, $opciones]) {
                $rutas = array_merge($rutas, array_keys($opciones));
            }
            $modulos = DB::table('modulos')->orderBy('id')->lockForUpdate()->get()->keyBy('nombre');
            $porId = $modulos->keyBy('id');
            $opciones = DB::table('opciones')->whereIn('ruta', $rutas)->orderBy('id')->lockForUpdate()->get()->keyBy('ruta');
            $ahora = now();
            $ordenModulo = 1;

            foreach (SeguridadSeeder::MODULOS as $nombreModulo => [$icono, $definiciones]) {
                $modulo = $modulos->get($nombreModulo);
                if (! $modulo) {
                    $id = DB::table('modulos')->insertGetId([
                        'nombre' => $nombreModulo, 'icono' => $icono, 'orden' => $ordenModulo,
                        'estado' => true, 'created_at' => $ahora, 'updated_at' => $ahora,
                    ]);
                    $modulo = DB::table('modulos')->where('id', $id)->first();
                    $porId->put($id, $modulo);
                } elseif ((int) $modulo->orden !== $ordenModulo) {
                    DB::table('modulos')->where('id', $modulo->id)->update(['orden' => $ordenModulo, 'updated_at' => $ahora]);
                }

                $ordenOpcion = 1;
                foreach ($definiciones as $ruta => [$nombreOpcion, $iconoOpcion]) {
                    $opcion = $opciones->get($ruta);
                    if ($opcion) {
                        $origen = $porId->get($opcion->modulo_id);
                        if (! $origen || ($opcion->estado && (bool) $origen->estado !== (bool) $modulo->estado)) {
                            throw new RuntimeException('La opción '.$ruta.' cambiaría su acceso efectivo al moverla. Revise el estado de sus módulos antes de reorganizar.');
                        }
                        if ((int) $opcion->modulo_id !== (int) $modulo->id || $opcion->nombre !== $nombreOpcion || (int) $opcion->orden !== $ordenOpcion) {
                            DB::table('opciones')->where('id', $opcion->id)->update([
                                'modulo_id' => $modulo->id, 'nombre' => $nombreOpcion,
                                'orden' => $ordenOpcion, 'updated_at' => $ahora,
                            ]);
                        }
                    }
                    $ordenOpcion++;
                }
                $ordenModulo++;
            }
        }, 3);
    }
}
