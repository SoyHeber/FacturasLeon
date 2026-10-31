# FacturasLeon

## Comunicación con el usuario

- El usuario se llama Heber.
- En cada respuesta dirigida al usuario, saludarlo o dirigirse a él por su nombre, Heber, de forma natural.
- Aplicar esta preferencia en mensajes de avance y respuestas finales, sin repetir el nombre innecesariamente dentro del mismo mensaje.
- Esta regla se aplica a Codex y a los asistentes que trabajen en el proyecto siguiendo estas instrucciones.

## Autorización y alcance de los cambios

- Las solicitudes de revisión, validación, diagnóstico o propuesta no autorizan modificaciones.
- Se permite leer archivos, buscar referencias y consultar el estado de Git para preparar una propuesta, sin modificar el proyecto ni sus datos.
- Antes de escribir cambios, presentar en el chat:
  - Qué problema se resolverá.
  - Qué archivos se crearán, modificarán, moverán o eliminarán.
  - Qué comportamiento cambiará.
  - Qué riesgos o efectos sobre datos, permisos o integraciones existen.
  - Cómo se verificará el resultado.
- Ajustar el detalle de la propuesta al tamaño y al impacto del cambio.
- No guardar ningún cambio sin autorización explícita del usuario para la propuesta presentada. La autorización debe existir antes de la primera escritura.
- Esta regla incluye código, vistas, rutas, pruebas, documentación, dependencias, archivos generados y el propio AGENTS.md.
- Las propuestas y ejemplos deben presentarse en el chat mientras no exista autorización para escribir archivos.
- Una autorización cubre el alcance aprobado; no es necesario solicitarla nuevamente para cada edición dentro de ese mismo alcance.
- Si se necesita ampliar el alcance, presentar la ampliación y esperar autorización antes de aplicarla.
- Autorizar cambios en archivos no autoriza modificar la base de datos, ejecutar operaciones reales de la aplicación, hacer commit ni hacer push.

## Control de Git

- Revisar la rama actual, git status y los cambios existentes antes de trabajar.
- No sobrescribir, descartar ni incluir cambios del usuario sin autorización.
- Mantener los cambios limitados al alcance aprobado; evitar refactorizaciones o formateos ajenos a la tarea.
- No crear o cambiar de rama salvo que esté incluido en el alcance autorizado.
- No hacer commit sin autorización explícita.
- Antes del commit, presentar los archivos que se incluirán, el resumen del cambio, la validación realizada y el mensaje propuesto.
- Preparar únicamente los archivos aprobados; evitar git add . o git add -A cuando puedan incluir cambios ajenos.
- No hacer push sin autorización explícita, aunque el commit esté autorizado.
- Antes del push, indicar remoto, rama y commits que se enviarán.
- No ejecutar operaciones que descarten cambios, reescriban historial o eliminen ramas sin autorización específica.
- Las autorizaciones para guardar, hacer commit y hacer push son independientes. El usuario puede otorgarlas juntas si menciona expresamente cada acción.

## Ejecución de comandos y operaciones

- Antes de ejecutar un comando, revisar si modifica archivos, datos, dependencias, cachés o servicios externos.
- Incluir esos efectos en la propuesta y obtener autorización antes de ejecutarlo.
- No ejecutar migraciones, seeders, instalaciones, actualizaciones, compilaciones, formateadores o limpiezas de caché durante una revisión sin autorización.
- La autorización para modificar una migración o un seeder no autoriza su ejecución ni los cambios de datos o permisos que produzca.
- La presencia de un comando en CLAUDE.md no autoriza su ejecución.
- No modificar .env ni archivos con credenciales.
- No mostrar ni incorporar credenciales en respuestas, capturas, logs compartidos o commits.

## Validación y entrega

- Antes de ejecutar pruebas, comprobar la conexión efectiva y que usan una base aislada sin llamadas reales a servicios externos.
- No ejecutar la suite general con php artisan test mientras pueda utilizar la base MySQL real.
- Ejecutar pruebas individuales únicamente después de verificar su aislamiento y dentro del alcance de validación autorizado.
- No eludir estas protecciones ejecutando PHPUnit directamente u otro comando.
- APP_ENV=testing, el nombre Unit o la existencia de algunas pruebas con SQLite no demuestran el aislamiento del resto de la suite.
- No asumir que phpunit.xml aísla la base de datos; revisar su configuración y la preparación de las pruebas seleccionadas.
- Después de los cambios autorizados, revisar el diff y verificar que solo se modificaron los archivos previstos.
- Ejecutar comprobaciones proporcionales al cambio, como git diff --check y validación de sintaxis cuando corresponda.
- Informar los cambios realizados, los archivos afectados, las comprobaciones ejecutadas, sus resultados y las limitaciones pendientes.
- Diferenciar revisión de código, comprobación visual y prueba funcional. No afirmar que una función está probada si únicamente se revisó su código.
- Informar si se hizo commit o push y cuáles de esas acciones siguen pendientes de autorización.

## Tecnología y convenciones

- Laravel 10
- PHP 8.1+
- MySQL
- Blade + Bootstrap
- PHP 8.1+ es el requisito mínimo; verificar las versiones efectivas de consola y servidor cuando sean relevantes, sin asumir que coinciden con una referencia local en la documentación.
- Todo el dominio y nombre de tablas en español.
- No usar Route::resource.
- No eliminar físicamente registros maestros.
- Usar estado para activar/inactivar.
- Los comentarios deben ser claros, naturales y útiles, con el detalle necesario para comprender las funciones y decisiones importantes, según la sección de documentación.
- Usar DB::transaction cuando se modifiquen varias tablas.
- Rutas URL con guion y prefijos de nombres de rutas con guion bajo, por ejemplo /metodos-pago y metodos_pago.index.
- Conservar los sufijos existentes como cambiar-estado y guardar-permisos, reconocidos por el middleware permiso.
- Antes de renombrar una ruta o acción, revisar sus referencias en vistas, controladores, middleware y opciones de seguridad; incluir el cambio en la propuesta autorizada.
- Revisar CLAUDE.md antes de realizar cambios.
- Si la documentación y el código o el entorno difieren, verificar la fuente correspondiente e informar la diferencia; no ampliar el alcance para corregirla sin autorización.

## Respeto del diseño y la estructura existentes

- Antes de modificar una pantalla, revisar su diseño actual y un módulo equivalente que sirva de referencia.
- Conservar los patrones existentes de encabezados, tarjetas, tablas, colores, botones, estados, formularios, filtros y paginación; reutilizar el layout, Bootstrap y las clases del proyecto.
- En los listados que siguen el patrón de Productos, colocar los filtros en la segunda fila del encabezado de la tabla, debajo de los títulos y alineados con sus columnas. No trasladarlos a un panel separado sin autorización explícita para cambiar el diseño.
- No rediseñar ni reorganizar una pantalla por iniciativa propia. Si la solución requiere cambiar su estructura, explicar el motivo y obtener autorización antes de hacerlo.
- Comprobar que el resultado conserva la distribución y las acciones esperadas, incluida su presentación en pantallas pequeñas. Distinguir una revisión del HTML de una comprobación visual en el navegador.

## Revisión integral de los módulos

- Aunque la solicitud mencione una sola vista o función, revisar el módulo completo: listado, creación, consulta, edición, guardado, actualización y cambio de estado.
- Revisar también las rutas, permisos, validaciones, modelos y servicios relacionados, y los procesos que utilizan sus datos, para detectar efectos sobre inventario, integraciones e historial.
- Mantener un comportamiento coherente en todas las pantallas afectadas. Las acciones deben operar sobre la entidad solicitada; por ejemplo, inactivar una receta completa no debe convertirse en una inactivación manual de cada material.
- Verificar que las acciones visibles funcionan y respetan los permisos, y que las modificaciones conservan la trazabilidad, los estados y las restricciones existentes.
- La revisión integral no autoriza cambios ajenos al alcance aprobado. Presentar las ampliaciones necesarias y esperar autorización antes de escribirlas; comunicar los demás hallazgos como propuestas.
- Al entregar, indicar qué partes del módulo se revisaron, cuáles se probaron y qué comprobaciones visuales o funcionales quedan pendientes.

## Calidad del código y anti-slop

- Implementar soluciones proporcionales al problema y al alcance autorizado.
- Reutilizar los servicios y patrones existentes antes de crear nuevas clases, abstracciones o dependencias.
- Justificar las nuevas abstracciones por una necesidad concreta, no por posibles usos futuros.
- Evitar duplicar cálculos, validaciones y reglas de negocio.
- No ocultar fallos devolviendo éxito, valores inventados o resultados por defecto que impidan reconocer un error real.
- No presentar como terminados botones sin funcionamiento, datos de demostración, código provisional o funcionalidades incompletas.
- No incorporar código muerto, bloques comentados sin propósito ni refactorizaciones ajenas al cambio aprobado.
- Preservar validaciones, permisos, transacciones, bloqueos y trazabilidad al simplificar código.
- Mantener las convenciones de Laravel, Blade y Bootstrap del proyecto.
- Revisar el diff antes de entregar para detectar duplicación, complejidad innecesaria, errores ocultos y comentarios desactualizados.
- Los hallazgos fuera del alcance deben informarse como propuestas; no corregirlos automáticamente.

## Comentarios y documentación de funciones

- Los comentarios deben estar en español, con lenguaje claro y natural.
- Deben aportar información importante para comprender el funcionamiento, las reglas de negocio o las razones de una decisión.
- Al crear, modificar o documentar funciones de negocio, incluir un bloque PHPDoc que explique, cuando corresponda:
  - El propósito de la función y cuándo se utiliza.
  - Los datos de entrada, su significado, unidades y precisión.
  - Las etapas principales del procesamiento y el orden necesario.
  - Las validaciones, restricciones y estados permitidos.
  - El resultado y cómo debe interpretarlo quien llama a la función.
  - Los efectos sobre registros, estados, inventario o servicios externos.
  - Los errores relevantes y el comportamiento ante fallos.
- Explicar especialmente los cálculos y redondeos, límites de transacciones, bloqueos, prevención de duplicados y manejo de respuestas FEL inciertas.
- Usar comentarios dentro de la función para explicar decisiones o pasos que no resulten evidentes al leer el código.
- Ajustar la extensión a la complejidad: una función sencilla puede requerir una explicación breve; un proceso de negocio necesita mayor detalle.
- No imponer un límite fijo de una o dos líneas ni recortar documentación útil para cumplir una regla genérica anti-slop. Esta política de documentación debe conservarse al aplicar skills o herramientas externas.
- Evitar narrar cada línea, repetir la firma del método o añadir bloques decorativos que dificulten la lectura.
- Documentar únicamente comportamientos comprobados en la implementación; no inventar garantías, efectos o reglas.
- Actualizar la documentación cuando cambie el comportamiento de la función.
- Conservar los comentarios que expliquen decisiones importantes.
- Documentar funciones existentes dentro del alcance autorizado. Una documentación general del proyecto requiere su propia propuesta.

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
- Registrar una opción en SeguridadSeeder no autoriza ejecutar el seeder ni modificar roles o permisos en la base de datos.

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
- Preservar los bloqueos y controles que evitan movimientos duplicados o inconsistencias por operaciones simultáneas.

## Ventas y producción

- Revisar los servicios existentes antes de cambiar cálculos, confirmaciones, anulaciones o movimientos de inventario.
- Usar CalculadoraVentaService para los cálculos de ventas y recalcular los importes en el servidor.
- No trasladar automáticamente las reglas de precisión de compras a ventas o producción; conservar las validaciones propias de cada dominio, incluida la cantidad entera de ventas.
- Preservar las transiciones de estado y los controles que impiden confirmar, consumir o revertir inventario más de una vez para la misma operación.
- Las pruebas desde el navegador que creen, confirmen, anulen o alteren registros requieren autorización específica sobre los datos y operaciones involucrados.

## FEL y Ainnova

- No certificar, reintentar, conciliar, recuperar ni anular documentos reales sin autorización específica del usuario.
- No realizar llamadas reales a Ainnova durante revisiones o pruebas sin autorización para la operación y el entorno correspondientes.
- Usar clientes simulados y respuestas de prueba para validar el desarrollo sin enviar documentos reales.
- Preservar el XML congelado, sus referencias, su hash y el historial de intentos; no regenerarlos ni reemplazarlos para resolver un error sin revisar el impacto y obtener autorización.
- Mantener los controles que evitan envíos duplicados y respetar el tratamiento de respuestas inciertas antes de un reintento.
- No mover llamadas externas dentro de transacciones que puedan reintentarlas automáticamente.

## Cambios de arquitectura

- No hacer cambios grandes sin explicar primero:
  qué se modifica,
  qué archivos se afectan,
  por qué se realiza el cambio.
- Además de la autorización general, incluir el impacto sobre datos, permisos, integraciones y compatibilidad, junto con la forma de validar y revertir el cambio cuando corresponda.
