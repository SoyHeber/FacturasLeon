# CLAUDE.md

Guía para trabajar en este repositorio (FacturasLeon — sistema administrativo de "Joyería de León").

## Stack

- Laravel 10, PHP 8.1+ (local: PHP 8.4), MySQL.
- Autenticación con Laravel Breeze (`routes/auth.php`, `app/Http/Controllers/Auth`).
- Vistas Blade con Bootstrap (layout `layouts.app-bootstrap`), assets con Vite.
- Sin paquetes de permisos externos: roles y permisos son propios (ver "Seguridad").

## Comandos

```bash
php artisan serve              # servidor local
npm run dev                    # Vite en modo desarrollo
php artisan migrate            # correr migraciones
php artisan db:seed --class=SeguridadSeeder   # (re)generar módulos, opciones, acciones y rol Administrador
php artisan test               # pruebas (PHPUnit)
./vendor/bin/pint              # formateo de código
```

## Arquitectura y convenciones

Todo el dominio está en español (tablas, columnas, modelos, rutas, mensajes).

### Base de datos
- Tablas en plural español: `marcas`, `metodos_pago`, `detalles_compra`, `roles_usuarios`.
- Toda tabla de catálogo tiene `estado` boolean (default `true`) y `timestamps()`.
- Llaves foráneas con `foreignId('x_id')->constrained('tabla')->restrictOnDelete()`.
- Unicidad compuesta con `$table->unique([...])`.
- Migraciones con clase anónima y métodos `up()` / `down()`.

### Modelos (`app/Models`)
- Nombre singular (`Marca`, `MetodoPago`), siempre con `protected $table` y `protected $fillable` explícitos.
- Relaciones con nombres en español (`departamento()`, `municipios()`, `usuario()`).

### Controladores (`app/Http/Controllers`)
- Un controlador por entidad con los métodos `index`, `create`, `store`, `show`, `edit`, `update`, `cambiarEstado`.
- **No se elimina**: en lugar de `destroy` se usa `cambiarEstado` (activar/inactivar). Los `destroy` existentes están comentados.
- Validación en línea con `$request->validate([...])` (no hay FormRequests salvo los de Breeze).
- `estado` en store: `$request->has('estado') ? (bool) $request->estado : true`; en update: `... : false`.
- `index` usa `latest()->paginate(10)`; los selects de FK cargan solo registros con `estado = true`.
- Operaciones que tocan varias tablas van dentro de `DB::transaction`.
- Responden con `redirect()->route('x.index')->with('success', 'Mensaje.')`.

### Rutas (`routes/web.php`)
- Declaradas una por una (no `Route::resource`), agrupadas con un comentario `/*Rutas para X*/`.
- URL con guion (`/metodos-pago`), nombre con guion bajo (`metodos_pago.index`).
- Cambio de estado: `PATCH /x/{x}/estado` → `x.cambiar-estado`.

### Vistas (`resources/views/<nombre_ruta>/`)
- `index`, `create`, `edit`, `show` por entidad; extienden `layouts.app-bootstrap` con `@section('content')`.
- Tablas `table table-bordered table-hover` con `thead.table-dark`, alertas de `session('success')` y bloque de `$errors`.
- Estado como badge `bg-success` / `bg-secondary`.
- El menú lateral está en `resources/views/layouts/navigation.blade.php` y se genera dinámicamente desde la BD.

## Seguridad (usuarios, roles, módulos, opciones y acciones)

### Modelo de datos
- `roles_usuarios`: un usuario tiene N roles.
- `modulos`: agrupadores del menú (Catálogos, Operaciones, Producción, Personas, Seguridad).
- `opciones`: cada opción pertenece a **un** módulo. `opciones.ruta` = prefijo del nombre de ruta (`marcas`, `metodos_pago`).
- `acciones`: catálogo (`ver`, `crear`, `modificar`, `eliminar`); el código usa la columna `clave`, nunca el id.
- `opciones_acciones`: qué acciones admite cada opción.
- `roles_opciones_acciones`: **los permisos**. El acceso a un módulo se deduce de sus opciones (no se guarda aparte).
- Los permisos de un usuario son la suma de sus roles activos. Roles, módulos y opciones inactivos no otorgan permisos.

### Cómo se aplica
- Middleware `permiso` (`app/Http/Middleware/VerificarPermiso.php`) protege el grupo de rutas CRUD. Traduce el nombre de ruta `prefijo.metodo` a opción + acción:
  - `index`, `show` → `ver`
  - `create`, `store` → `crear`
  - `edit`, `update`, `permisos`, `guardar-permisos` → `modificar`
  - `destroy`, `cambiar-estado` → `eliminar`
- En Blade: `@can('marcas.crear') ... @endcan` (registrado con `Gate::before` en `AuthServiceProvider`).
- En código: `auth()->user()->tienePermiso('marcas', 'crear')`.

### Al agregar un CRUD nuevo
1. Crear las rutas dentro del grupo con middleware `permiso` siguiendo la convención de nombres.
2. Registrar la opción (ruta = prefijo del nombre) en `database/seeders/SeguridadSeeder.php` bajo su módulo, o crearla desde la pantalla de Opciones.
3. Correr `php artisan db:seed --class=SeguridadSeeder` (es idempotente y le da todos los permisos al rol Administrador).
4. Si no se registra la opción, la ruta responde 403 para todos.
