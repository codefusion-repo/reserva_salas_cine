# Reserva Salas Cine

Sistema web académico de reserva de salas adaptado a un flujo de cine. La aplicación permite registrarse, iniciar sesión, revisar cartelera, seleccionar funciones y butacas, crear reservas, confirmarlas mediante checkout simulado y administrar la operación base desde un panel protegido.

El proyecto se mantiene XAMPP-first y dependency-light:

- PHP vanilla.
- MySQL/MariaDB.
- HTML, CSS y JavaScript.
- Apache mediante XAMPP como entorno oficial.
- No requiere Laravel, Composer, React, Next, Docker, cloud, APIs externas, CDN ni pasarela de pagos.

## Estado del proyecto

El estado actual es un MVP académico con base funcional implementada. El tag `v0.9.0-instituto-base` existe como checkpoint base histórico; el repositorio actual incluye trabajo posterior de funcionalidad, refactor, pagos simulados y pulido responsive.

Target oficial:

- Windows + XAMPP.
- Ruta: `C:\xampp\htdocs\reserva_salas_cine`.
- URL: `http://localhost/reserva_salas_cine/public/`.
- Base de datos: `reserva_salas_cine`.
- Usuario DB local: `root`.
- Password DB local: vacío.

WSL Ubuntu o Apache local pueden usarse como apoyo de desarrollo, pero no reemplazan la validación final en Windows + XAMPP. Este README no declara entrega final; esa decisión requiere evidencia de ejecución en XAMPP real.

## Documentación relacionada

- [Reglas para agentes](AGENTS.md)
- [Alcance del proyecto](docs/SCOPE.md)
- [Reglas funcionales](docs/BUSINESS_RULES.md)
- [Validación](docs/VALIDATION.md)
- [Checklist final XAMPP](docs/XAMPP_FINAL_CHECKLIST.md)
- [Fuentes de verdad](docs/SOURCE_OF_TRUTH.md)
- Fuente funcional académica: `docs/functional/Trabajo 3 web.pdf`
- Mockups aprobados: `docs/mockups/approved/`

`Project OS` es referencia de workflow, proceso y disciplina operativa. No reemplaza la fuente funcional del proyecto ni las reglas de negocio de Reserva Salas Cine.

## Instalación en XAMPP

1. Clonar o copiar el proyecto en:

   ```text
   C:\xampp\htdocs\reserva_salas_cine
   ```

2. Abrir el panel de XAMPP e iniciar:

   - Apache.
   - MySQL.

3. Crear la base de datos `reserva_salas_cine` desde phpMyAdmin:

   ```text
   http://localhost/phpmyadmin
   ```

   Cotejamiento recomendado:

   ```text
   utf8mb4_unicode_ci
   ```

4. Importar los archivos SQL en este orden:

   ```text
   database/schema.sql
   database/seed.sql
   ```

5. Abrir la aplicación:

   ```text
   http://localhost/reserva_salas_cine/public/
   ```

## Configuración de base de datos

La configuración versionada en `config/database.php` usa defaults compatibles con XAMPP:

```text
DB_HOST=localhost
DB_NAME=reserva_salas_cine
DB_USER=root
DB_PASS=
```

Si un entorno local necesita credenciales distintas, usar un override local:

```text
config/database.local.php
```

Ese archivo está ignorado por Git y no debe usarse para versionar secretos, credenciales personales, dumps con datos reales ni configuración privada de XAMPP.

## Credenciales demo

Las credenciales demo fueron verificadas contra los hashes versionados en `database/seed.sql`. No se deben copiar ni exponer los hashes en documentación operativa.

| Rol | Correo | Password |
| --- | --- | --- |
| Administrador | `admin@reservacine.local` | `AdminDemo123!` |
| Usuario | `usuario@reservacine.local` | `UsuarioDemo123!` |

## Modelo de datos actual

`database/schema.sql` define la base académica y las extensiones demo actuales:

- `users`: usuarios, hash de contraseña y rol.
- `rooms`: salas, ubicación, capacidad y estado.
- `movies`: cartelera y metadata de películas.
- `showtimes`: funciones asociadas a película y sala.
- `reservations`: reservas con estados `pending`, `confirmed` y `cancelled`.
- `reservation_seats`: butacas asociadas a una reserva y función.
- `payments`: pagos simulados por reserva, confitería o membresía.
- `payment_items`: ítems asociados a pagos simulados.
- `coupons`: cupones demo por tipo de checkout.
- `concession_products`: catálogo demo de confitería.
- `user_memberships`: membresía demo persistida por usuario.

`database/seed.sql` carga usuarios demo, salas, películas, productos de confitería, cupones demo y funciones iniciales.

## Flujo de usuario

Estado implementado en rutas y controladores actuales:

1. Registro, login, logout y control de sesión.
2. Cartelera autenticada con filtros por texto, género y clasificación.
3. Detalle de película con funciones activas, sala, formato, idioma y disponibilidad.
4. Selección de cantidad de entradas.
5. Selección de butacas por función, con bloqueo de butacas ocupadas.
6. Creación de reserva en estado `pending`.
7. Checkout simulado de reserva para confirmar la reserva y registrar pago demo.
8. Aplicación y remoción de cupones demo cuando corresponden al tipo de checkout.
9. Vista "Mis reservas" con reservas del usuario autenticado.
10. Cancelación de reservas propias.
11. Ticket visual para reservas confirmadas o canceladas.
12. Vista "Mis pagos", detalle de pago, comprobante e impresión/descarga TXT.
13. Confitería demo con catálogo desde base de datos, carrito en sesión y checkout simulado.
14. Socios demo con membresía persistida por usuario, activación vía checkout simulado y desactivación.
15. Perfil de usuario con resumen de cuenta, reservas, pagos y estado de membresía demo.

Los pagos son simulados: no hay cobros reales, no se piden datos bancarios y no existe conexión con proveedores externos.

## Flujo administrador

El panel administrador requiere rol `admin`. El estado actual incluye:

- Acceso protegido y vista de denegación para usuarios sin rol administrador.
- Resumen administrativo.
- Gestión de salas: crear, editar, activar/desactivar y eliminar cuando aplica.
- Gestión de películas: crear, editar, activar/desactivar y eliminar cuando aplica.
- Gestión de funciones: crear, editar, activar/desactivar, eliminar cuando aplica y filtrar.
- Validación de traslape para funciones activas en la misma sala.
- Vista administrativa de reservas con filtros.
- Gestión de productos de confitería: crear, editar, activar/desactivar y eliminar.
- Gestión de cupones demo: crear, editar y activar/desactivar.
- Vista administrativa de pagos simulados con filtros.
- Detalle de pago administrativo, comprobante y descarga TXT.

Las acciones POST administrativas usan guard de rol y CSRF.

## Pendiente, demo o fuera de alcance

No está implementado ni forma parte del alcance académico actual:

- Cobros reales.
- Pasarela de pagos.
- Captura o almacenamiento de datos reales de tarjeta.
- APIs externas.
- Pedidos reales de confitería, stock, despacho o integración de caja.
- Membresías reales, puntos reales o beneficios comerciales reales.
- Deploy cloud.
- CI/CD.
- Docker.
- Frameworks frontend o backend obligatorios.

La ruta visual `pago` se mantiene como pantalla conceptual. Los checkouts demo reales se abren desde reservas, confitería y socios.

## Validación

Documentación de validación:

- [docs/VALIDATION.md](docs/VALIDATION.md)
- [docs/XAMPP_FINAL_CHECKLIST.md](docs/XAMPP_FINAL_CHECKLIST.md)

Para cambios documentales, ejecutar como mínimo:

```bash
git status --short --branch
git diff --check
```

Para apoyo técnico en desarrollo, también pueden usarse:

```bash
find . -name "*.php" -print0 | xargs -0 -n1 php -l
curl -I http://localhost/reserva_salas_cine/public/
```

Para validación final manual en Windows + XAMPP, registrar evidencia de:

- Apache y MySQL iniciados desde XAMPP.
- Proyecto ubicado en `C:\xampp\htdocs\reserva_salas_cine`.
- URL oficial abierta: `http://localhost/reserva_salas_cine/public/`.
- Base de datos `reserva_salas_cine` creada.
- Importación de `database/schema.sql` y `database/seed.sql` sin errores.
- Flujos de usuario, administrador, reserva, checkout simulado, pagos, cupones, confitería, socios y perfil probados cuando apliquen.
- Revisión de errores visibles y logs de Apache/XAMPP.

WSL puede aportar evidencia de apoyo, pero la entrega final requiere ejecución real en Windows + XAMPP y evidencia revisable.

## Seguridad y calidad

Reglas implementadas o requeridas por el proyecto:

- Conexión a MySQL/MariaDB mediante PDO.
- Consultas con prepared statements.
- Contraseñas con `password_hash` y verificación con `password_verify`.
- Escape de salida dinámica con `e()` / `htmlspecialchars`.
- Validación de formularios en PHP.
- CSRF en formularios POST implementados.
- Rutas y acciones admin protegidas por rol.
- Sesión regenerada al iniciar sesión.
- Sin datos reales de tarjeta.
- Pagos simulados solamente.
- Sin secretos versionados.

## Workflow

El trabajo se organiza mediante issues, PRs, commits y evidencia revisable. No se deben cerrar issues, declarar entrega final, crear tags/releases ni afirmar cumplimiento sin evidencia.

El repositorio `codefusion-repo/project-os` funciona como referencia de workflow/proceso. Las funcionalidades y reglas de negocio de esta aplicación se validan contra el repositorio actual, `docs/functional/Trabajo 3 web.pdf`, `docs/BUSINESS_RULES.md`, `docs/SCOPE.md`, mockups aprobados y evidencia de implementación.

## Licencia

Proyecto académico para uso educativo.
