# Reserva Salas Cine - Project Brief

## Objetivo

Reserva Salas Cine es un proyecto academico PHP/MySQL para administrar una experiencia basica de cine: cartelera, funciones, seleccion de butacas y reservas.

## Entorno oficial

La validacion final del instituto debe realizarse en Windows con XAMPP:

- Ruta: `C:\xampp\htdocs\reserva_salas_cine`
- URL: `http://localhost/reserva_salas_cine/public/`
- Base de datos: `reserva_salas_cine`
- Herramienta de importacion: phpMyAdmin
- Usuario MySQL local: `root`
- Password MySQL local: vacio por defecto

WSL Ubuntu con Apache, PHP y MariaDB es valido solo como entorno equivalente de desarrollo. No reemplaza la validacion final en XAMPP.

## Base tecnica

La base del proyecto mantiene:

- Estructura `app/`, `config/`, `database/`, `docs/` y `public/`.
- Entrada publica en `public/index.php`.
- Configuracion XAMPP-first en `config/database.php`.
- Override local opcional e ignorado en `config/database.local.php`.
- Helper PDO en `app/helpers/database.php`.
- Helper de escape HTML en `app/helpers/security.php`.
- Esquema en `database/schema.sql`.
- Datos demo locales en `database/seed.sql`.

## Modelo inicial

Tablas incluidas:

- `users`: usuarios, correo, hash de password y rol.
- `rooms`: salas disponibles y capacidad.
- `movies`: cartelera base.
- `showtimes`: funciones por pelicula, sala y horario.
- `reservations`: reservas de usuario por funcion.
- `reservation_seats`: butacas asociadas a cada reserva.

## Conflicto de butacas

La tabla `reservation_seats` incluye el indice `idx_reservation_seats_showtime_seat` para buscar rapidamente si una butaca ya esta tomada en una funcion.

No se agrega una restriccion unica global sobre `(showtime_id, seat_row, seat_number)` porque bloquearia reutilizar una butaca cuando una reserva fue cancelada. El estado de cancelacion vive en `reservations`, por lo que una regla activa-only requiere validacion en PHP consultando reservas con estado `pending` o `confirmed`, o una estructura mas avanzada. Para este bootstrap se mantiene una estructura conservadora y compatible con XAMPP.

## Credenciales demo locales

Estas credenciales son solo para desarrollo local:

- Admin: `admin@reservacine.local` / `AdminDemo123!`
- Usuario: `usuario@reservacine.local` / `UsuarioDemo123!`

Los valores almacenados en `database/seed.sql` son hashes compatibles con `password_hash` y `password_verify`; no se insertan passwords en texto plano.

## Estado funcional implementado

La evidencia actual del repositorio soporta:

- Registro, login y logout con `password_hash` y `password_verify`.
- Sesiones de usuario y redireccion por estado autenticado.
- Roles `admin` y `user`.
- Cartelera de peliculas activas.
- Detalle de pelicula con funciones activas agrupadas por fecha.
- Seleccion de funcion, cantidad de entradas y butacas.
- Creacion de reservas con registros en `reservations` y
  `reservation_seats`.
- Validacion de butacas ocupadas contra reservas `pending` o `confirmed`.
- Listado de reservas propias del usuario autenticado.
- Cancelacion logica de reservas propias y liberacion logica de butacas.
- Panel administrador para crear, editar y desactivar salas.
- Panel administrador para crear, editar, activar y desactivar funciones.
- Validacion PHP para evitar traslapes de funciones activas en la misma sala.

No hay evidencia de CRUD administrador de peliculas ni de vista administrador
de todas las reservas.

## Seguridad base

- La conexion usa PDO.
- Las consultas del helper usan prepared statements.
- La salida HTML dinamica debe pasar por `e()`, que usa `htmlspecialchars`.
- `config/database.local.php` queda ignorado por Git para credenciales locales.
- No se deben versionar secretos reales ni passwords personales.

## Pendientes, blockers y alcance futuro

- #55: autorizacion POST admin fail-closed. Debe resolverse antes de #35.
- #35: CSRF en formularios POST.
- Validacion final en Windows + XAMPP con evidencia.
- #32: vista administrador de todas las reservas del sistema.
- #22: visual 404 dedicado, si se mantiene como alcance pendiente.
- CRUD administrador de peliculas, si el PM lo prioriza.
- Checkout, pagos, cupones, confiteria y socios funcionales.
- Pago real, pasarela real, compra real de confiteria y membresias reales
  siguen fuera del MVP academico actual.

## Mockups aprobados

Los mockups aprobados se encuentran en:

docs/mockups/approved/

Estos archivos son fuente de verdad visual para la interfaz. Las pantallas futuras deben replicar los mockups lo más fielmente posible: layout, colores, espaciados, jerarquía visual, navegación y estados.

Excepción aprobada: el logo puede quedar como placeholder por ahora. El asset final será agregado después por el PM.

Los mockups son referencia de diseño y no deben usarse como assets runtime del sitio.
