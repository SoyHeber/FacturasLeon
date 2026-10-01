# FacturasLeon

- Laravel 10
- PHP 8.1+
- MySQL
- Blade + Bootstrap
- Todo el dominio y nombre de tablas en español.
- No usar Route::resource.
- No eliminar físicamente registros maestros.
- Usar estado para activar/inactivar.
- Los comentarios de código deben ser simples y naturales.
- Usar DB::transaction cuando se modifiquen varias tablas.
- Rutas URL con guion y nombres de rutas con guion bajo.
- Revisar CLAUDE.md antes de realizar cambios.
- No ejecutar php artisan test mientras phpunit.xml use la base MySQL real.
- No modificar .env ni archivos con credenciales.

## Controladores

- Mantener los métodos:
  index
  create
  store
  show
  edit
  update
  cambiarEstado

- Validar con $request->validate().
- Usar redirect()->with('success', ...).
- No agregar destroy salvo aprobación explícita.

## Permisos

- Todos los CRUD deben usar el middleware permiso.
- Los botones deben usar @can cuando corresponda.
- Las nuevas opciones deben registrarse en SeguridadSeeder.
- Los permisos siguen la estructura:
  modulo -> opcion -> accion.
- Las acciones son:
  ver
  crear
  modificar
  eliminar.

## Compras

- Compras funciona como maestro-detalle.
- compras es la cabecera.
- detalles_compra contiene las líneas.
- numero_linea se genera automáticamente.
- Los cálculos deben usar CalculadoraDteService.
- No confiar en importes calculados enviados desde el navegador.
- Cantidad se maneja operativamente con 5 decimales.
- Precio con 6 decimales.
- Porcentaje de descuento con 4 decimales.
- La base de datos permite hasta 10 decimales en cantidades y precios.
- Los importes monetarios se guardan con 2 decimales.

## Inventario

- No automatizar movimientos de inventario sin revisar previamente el impacto.
- No permitir stock negativo.
- Las operaciones que afecten stock deben usar transacciones.
- Mantener trazabilidad de entradas, salidas y ajustes.

## Cambios de arquitectura

- No hacer cambios grandes sin explicar primero:
  qué se modifica,
  qué archivos se afectan,
  por qué se realiza el cambio.