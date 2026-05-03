# Checklist final de validacion XAMPP

## Limites

Este checklist es una guia manual para una futura validacion en Windows +
XAMPP. El checklist no ejecuta la validacion por si mismo, no declara entrega
lista y no reemplaza la evidencia revisable.

Los comandos o pruebas en WSL son solo apoyo de desarrollo. La entrega final
requiere ejecucion real en Windows + XAMPP y evidencia.

Use un resultado por cada item:

- Pass: ejecutado en Windows + XAMPP y con evidencia capturada.
- Fail: ejecutado y no cumple el resultado esperado.
- N/A: la funcionalidad, ruta, tabla o control no esta implementado.

Cada item marcado como Pass, Fail o N/A debe incluir evidencia o una nota breve
que justifique el resultado.

## Encabezado de evidencia

- Validador:
- Fecha:
- Branch o commit:
- Version de Windows:
- Version de XAMPP:
- Version de PHP:
- Version de MySQL o MariaDB:
- Navegador:
- Ubicacion de evidencia:

## 1. Entorno XAMPP

- [ ] La validacion se ejecuta en Windows + XAMPP.
- [ ] El proyecto esta ubicado en `C:\xampp\htdocs\reserva_salas_cine`.
- [ ] Apache inicia desde el panel de control de XAMPP.
- [ ] MySQL inicia desde el panel de control de XAMPP.
- [ ] phpMyAdmin abre desde XAMPP.
- [ ] La aplicacion abre en `http://localhost/reserva_salas_cine/public/`.
- [ ] La pagina carga sin warnings, notices, errores fatales ni trazas PHP
  visibles.

## 2. Comandos utiles obligatorios

Ejecute estos comandos desde la raiz del proyecto cuando correspondan al
entorno. Los comandos en WSL son solo apoyo de desarrollo y no reemplazan la
validacion final en Windows + XAMPP.

Comandos para registrar estado del repositorio y calidad documental:

```bash
git status --short --branch
git diff --check
```

Comando de lint PHP de apoyo. En Windows puede ejecutarse desde Git Bash,
WSL, o una terminal que tenga disponibles `find`, `xargs` y `php`:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
```

Comando de respuesta HTTP. En Windows + XAMPP debe apuntar a la URL oficial:

```bash
curl -I http://localhost/reserva_salas_cine/public/
```

Registrar como evidencia:

- [ ] `git status --short --branch` fue ejecutado y guardado.
- [ ] `git diff --check` fue ejecutado y no reporto errores.
- [ ] Lint PHP fue ejecutado como apoyo y los errores, si existen, fueron
  documentados.
- [ ] `curl -I http://localhost/reserva_salas_cine/public/` fue ejecutado en
  el entorno aplicable y la respuesta fue documentada.
- [ ] Se distingue claramente que WSL, si se usa, es apoyo de desarrollo y no
  evidencia final de entrega.

## 3. Base de datos

- [ ] La base de datos `reserva_salas_cine` existe.
- [ ] El host de base de datos es `localhost`.
- [ ] El usuario de base de datos es `root`.
- [ ] La password de base de datos esta vacia.
- [ ] `database/schema.sql` importa desde phpMyAdmin sin errores.
- [ ] `database/seed.sql` importa desde phpMyAdmin sin errores.
- [ ] La tabla `users` existe.
- [ ] La tabla `rooms` existe.
- [ ] La tabla `movies` existe.
- [ ] La tabla `showtimes` existe.
- [ ] La tabla `reservations` existe.
- [ ] La tabla `reservation_seats` existe.
- [ ] La tabla opcional `payments` existe solo si pagos esta implementado.
- [ ] La tabla opcional `payment_items` existe solo si pagos esta implementado.
- [ ] La tabla opcional `coupons` existe solo si cupones esta implementado.

## 4. Verificacion SQL obligatoria

Ejecute estas consultas en phpMyAdmin o en el cliente MySQL/MariaDB conectado a
`reserva_salas_cine`. Guarde los resultados como evidencia.

```sql
SHOW TABLES;

SELECT COUNT(*) AS users_count FROM users;

SELECT COUNT(*) AS active_movies FROM movies WHERE is_active = 1;

SELECT COUNT(*) AS rooms_count FROM rooms;

SELECT COUNT(*) AS showtimes_count FROM showtimes;

SELECT status, COUNT(*) AS total
FROM reservations
GROUP BY status
ORDER BY status;
```

- [ ] `SHOW TABLES;` muestra las tablas obligatorias esperadas.
- [ ] `users_count` queda registrado.
- [ ] `active_movies` queda registrado.
- [ ] `rooms_count` queda registrado.
- [ ] `showtimes_count` queda registrado.
- [ ] El conteo de `reservations` por `status` queda registrado.
- [ ] Cualquier tabla opcional ausente queda marcada como N/A si su
  funcionalidad no esta implementada.

## 5. Autenticacion

Use usuarios demo locales desde los datos semilla cuando esten disponibles:

- Admin: `admin@reservacine.local` / `AdminDemo123!`
- Usuario: `usuario@reservacine.local` / `UsuarioDemo123!`

- [ ] Registrar un usuario normal nuevo.
- [ ] Iniciar sesion con el usuario normal demo.
- [ ] Iniciar sesion con el administrador demo.
- [ ] Intentar login invalido y confirmar rechazo con error controlado.
- [ ] Cerrar sesion y confirmar que la sesion termina.
- [ ] Un usuario normal no puede acceder a paginas ni acciones admin.
- [ ] Un administrador puede acceder a paginas admin.

## 6. Cartelera y peliculas

- [ ] La cartelera carga.
- [ ] Los posters de peliculas cargan.
- [ ] El detalle de pelicula carga.
- [ ] Las funciones activas aparecen en el detalle de pelicula.
- [ ] Las rutas o registros no disponibles muestran error controlado o 404 si
  ese comportamiento esta implementado.

## 7. Reservas

- [ ] Elegir una funcion.
- [ ] Elegir cantidad de entradas.
- [ ] Seleccionar butacas.
- [ ] Crear una reserva.
- [ ] Se bloquea una butaca activa duplicada para la misma funcion.
- [ ] Las reservas confirmadas bloquean sus butacas seleccionadas.
- [ ] Las reservas canceladas liberan sus butacas seleccionadas.
- [ ] El estado pendiente se prueba solo si existe checkout simulado.
- [ ] La confirmacion muestra pelicula, funcion, sala, butacas, total y datos
  de reserva correctos.
- [ ] Mis reservas muestra solo reservas del usuario autenticado.
- [ ] Cancelar reserva.
- [ ] Un usuario no puede cancelar la reserva de otro usuario.

## 8. Administracion

- [ ] El administrador puede abrir la gestion de salas.
- [ ] El administrador puede abrir la gestion de funciones.
- [ ] El administrador puede crear una sala.
- [ ] El administrador puede editar una sala.
- [ ] El administrador puede desactivar una sala.
- [ ] El administrador puede crear una funcion.
- [ ] El administrador puede editar una funcion.
- [ ] El administrador puede activar una funcion.
- [ ] El administrador puede desactivar una funcion.
- [ ] Se bloquea el traslape de funciones activas en la misma sala.
- [ ] Una funcion inactiva no bloquea la validacion de traslape.
- [ ] Vista admin de todas las reservas se prueba solo si esta implementada.
- [ ] Gestion admin de pagos se prueba solo si esta implementada.
- [ ] Gestion admin de cupones se prueba solo si esta implementada.

## 9. Funcionalidades opcionales condicionadas

Marque N/A cuando la funcionalidad no este implementada. Si esta implementada,
cada item debe tener evidencia Pass o Fail.

- [ ] Visual de ticket abre si esta implementado.
- [ ] Visual de ticket muestra pelicula, funcion, sala, butacas, usuario y
  reserva correctos si esta implementado.
- [ ] Visual de ticket puede imprimirse si esa accion esta implementada.
- [ ] Codigo visual de reserva aparece correctamente si esta implementado.
- [ ] Comprobante o invoice indica que es simulado, no tributario/no legal, y
  que no ocurrio pago real si esta implementado.
- [ ] Confiteria abre si esta implementada.
- [ ] Carrito funciona si esta implementado.
- [ ] Socios abre si esta implementado.
- [ ] Membresia demo funciona si esta implementada.
- [ ] Checkout simulado funciona si esta implementado.
- [ ] No se solicitan datos reales de tarjeta.
- [ ] No se almacenan datos reales de tarjeta.
- [ ] No se usan APIs externas.
- [ ] Cupones demo funcionan si estan implementados.
- [ ] Pagos funcionan solo como simulacion si estan implementados.
- [ ] Gestion admin de pagos se prueba solo si esta implementada.
- [ ] Gestion admin de cupones se prueba solo si esta implementada.
- [ ] Perfil abre y muestra datos correctos solo si esta implementado.

## 10. Seguridad y calidad

- [ ] No aparecen warnings, notices, trazas, errores SQL ni errores fatales PHP
  visibles en el navegador.
- [ ] Logs de Apache/XAMPP fueron revisados despues de la prueba.
- [ ] No hay credenciales reales versionadas.
- [ ] `config/database.local.php` no esta versionado.
- [ ] No hay datos sensibles en Git.
- [ ] Los formularios POST usan CSRF si el issue #35 esta implementado.
- [ ] La autorizacion de POST admin falla cerrada si el issue #55 esta
  implementado.
- [ ] El acceso a base de datos usa PDO.
- [ ] Las lecturas y escrituras SQL usan prepared statements.
- [ ] La salida HTML dinamica se escapa con `e()` o `htmlspecialchars`.

## 11. Evidencia final requerida

Adjunte o referencie evidencia para:

- Entorno y URL.
- Comandos utiles obligatorios.
- Importacion de base de datos y lista de tablas.
- Verificacion SQL obligatoria.
- Resultados de autenticacion.
- Cartelera y detalle de pelicula.
- Creacion de reserva, bloqueo de butaca duplicada, cancelacion y controles de
  pertenencia.
- Checks admin de salas y funciones.
- Funcionalidades opcionales marcadas como Pass, Fail o N/A.
- Checks de seguridad y calidad.
- Cualquier item N/A con razon breve.

Este checklist queda completo solo cuando cada item aplicable tiene Pass, Fail
o N/A mas evidencia. El checklist no declara entrega lista por si mismo.
