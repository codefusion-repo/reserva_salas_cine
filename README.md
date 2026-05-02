# Reserva Salas Cine

Sistema web académico para gestionar reservas de salas de cine, desarrollado con PHP, JavaScript, HTML, CSS y MySQL/MariaDB.

El proyecto adapta el enunciado de reserva de salas a un flujo tipo cine: cartelera, detalle de película, selección de función, selección de butacas y reserva.

## Estado del proyecto

MVP académico en desarrollo.

Target oficial de entrega:

- Windows + XAMPP
- PHP vanilla
- MySQL/MariaDB
- Apache
- phpMyAdmin

WSL Ubuntu puede usarse como entorno equivalente de desarrollo, pero la entrega final debe funcionar en XAMPP.

## Stack

- PHP vanilla
- MySQL/MariaDB
- JavaScript
- HTML
- CSS
- XAMPP

## Target oficial

El proyecto debe poder ejecutarse en XAMPP usando:

- Ruta del proyecto:

      C:\xampp\htdocs\reserva_salas_cine

- URL local:

      http://localhost/reserva_salas_cine/public/

- Base de datos:

      reserva_salas_cine

- Usuario local esperado:

      root

- Password local esperado:

      vacío

## Entorno equivalente de desarrollo

Durante el desarrollo se puede usar WSL Ubuntu con Apache, PHP y MariaDB.

Ruta equivalente actual:

    /var/www/html/reserva_salas_cine

URL equivalente:

    http://localhost/reserva_salas_cine/public/

Importante: WSL no reemplaza la validación final en XAMPP. No se deben agregar dependencias, rutas, usuarios de base de datos o configuraciones que rompan la ejecución en XAMPP.

## Alcance MVP

El sistema debe permitir:

- Registro e inicio de sesión de usuarios.
- Control de sesión activa.
- Roles de usuario: administrador y usuario normal.
- Visualización de cartelera.
- Detalle de película con horarios disponibles.
- Selección de cantidad de entradas.
- Selección de butacas.
- Creación de reservas.
- Visualización de reservas del usuario.
- Cancelación de reservas.
- Administración básica de salas, películas y funciones.
- Validación para evitar reservar dos veces la misma butaca en la misma función.
- Validación para evitar funciones traslapadas en la misma sala.
- Pantallas “Próximamente” para confitería, socios y pago.

## Fuera de alcance

Este MVP no incluye:

- Pago real.
- Pasarela de pago.
- APIs externas.
- Frameworks como Laravel, React o Next.js.
- Docker.
- Deploy en la nube.
- CI/CD.
- Compra real de productos de confitería.
- Membresías reales.
- Sistema avanzado de reportes.

## Estructura esperada

    reserva_salas_cine/
    ├── app/
    │   ├── controllers/
    │   ├── helpers/
    │   ├── middleware/
    │   ├── models/
    │   └── views/
    ├── config/
    │   ├── database.php
    │   └── database.local.php
    ├── database/
    │   ├── schema.sql
    │   └── seed.sql
    ├── docs/
    │   └── PROJECT_BRIEF.md
    ├── public/
    │   ├── assets/
    │   │   ├── css/
    │   │   ├── img/
    │   │   └── js/
    │   ├── uploads/
    │   │   └── .gitkeep
    │   └── index.php
    ├── .gitignore
    └── README.md

## Configuración de base de datos

Los valores por defecto deben ser compatibles con XAMPP:

    DB_HOST=localhost
    DB_NAME=reserva_salas_cine
    DB_USER=root
    DB_PASS=

Si se necesita una configuración local distinta en WSL u otro entorno, debe usarse un archivo local ignorado por Git, por ejemplo:

    config/database.local.php

No se deben versionar contraseñas reales ni credenciales personales.

## Instalación en XAMPP

1.  Copiar o clonar el proyecto dentro de:

    C:\xampp\htdocs\reserva_salas_cine

2.  Iniciar XAMPP.

3.  Activar:
    - Apache
    - MySQL

4.  Abrir phpMyAdmin:

    http://localhost/phpmyadmin

5.  Crear la base de datos:

        reserva_salas_cine

    Con cotejamiento recomendado:

        utf8mb4_unicode_ci

6.  Importar el esquema:

    database/schema.sql

7.  Importar datos iniciales:

    database/seed.sql

8.  Abrir el proyecto:

    http://localhost/reserva_salas_cine/public/

## Instalación equivalente en WSL

1. Ubicar el proyecto en:

   /var/www/html/reserva_salas_cine

2. Verificar Apache:

   sudo systemctl status apache2

3. Verificar MariaDB:

   sudo systemctl status mariadb

4. Crear la base de datos:

   sudo mysql -e "CREATE DATABASE IF NOT EXISTS reserva_salas_cine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

5. Importar esquema:

   sudo mysql reserva_salas_cine < database/schema.sql

6. Importar datos iniciales:

   sudo mysql reserva_salas_cine < database/seed.sql

7. Abrir:

   http://localhost/reserva_salas_cine/public/

## Modelo de datos inicial

Tablas mínimas esperadas:

- users
- rooms
- movies
- showtimes
- reservations
- reservation_seats

Responsabilidades principales:

- users: usuarios, credenciales y roles.
- rooms: salas disponibles.
- movies: películas de cartelera.
- showtimes: funciones por película, sala y horario.
- reservations: reservas realizadas por usuarios.
- reservation_seats: butacas asociadas a cada reserva.

## Reglas técnicas

- Usar PHP vanilla.
- Usar PDO para conexión a MySQL/MariaDB.
- Usar prepared statements en consultas SQL.
- Usar password_hash para registrar contraseñas.
- Usar password_verify para iniciar sesión.
- No guardar contraseñas en texto plano.
- Validar formularios en PHP.
- Usar JavaScript solo como mejora de experiencia, no como única validación.
- Escapar salida HTML con htmlspecialchars.
- Proteger rutas administrativas por rol.
- Mantener el código simple y entendible.
- No agregar dependencias obligatorias que compliquen XAMPP.

## Flujo principal del usuario

1. Usuario se registra o inicia sesión.
2. Ve la cartelera.
3. Selecciona una película.
4. Selecciona día, horario y cantidad de entradas.
5. Selecciona butacas disponibles.
6. Confirma la reserva.
7. Puede ver y cancelar sus reservas.

## Flujo principal del administrador

1. Administrador inicia sesión.
2. Gestiona salas.
3. Gestiona películas.
4. Gestiona funciones.
5. Revisa reservas del sistema.
6. Evita crear funciones traslapadas en la misma sala.

## Validaciones obligatorias

### Autenticación

- No permitir registro con campos vacíos.
- No permitir correos duplicados.
- No permitir login con credenciales incorrectas.
- Mantener sesión activa.
- Permitir cerrar sesión.

### Reservas

- No permitir reservas sin usuario autenticado.
- No permitir reservas sin función seleccionada.
- No permitir cantidad de entradas menor a 1.
- No permitir seleccionar más butacas que entradas.
- No permitir reservar una butaca ya ocupada.
- No permitir reservar funciones inexistentes o inactivas.
- Al cancelar una reserva, liberar o invalidar correctamente las butacas asociadas.

### Administración

- Solo administradores pueden acceder al panel admin.
- No permitir crear salas con campos vacíos.
- No permitir crear funciones con horario inválido.
- No permitir funciones traslapadas en la misma sala.

## Validación manual final

Antes de entregar, comprobar en XAMPP:

- [ ] Apache inicia correctamente.
- [ ] MySQL inicia correctamente.
- [ ] El proyecto está en C:\xampp\htdocs\reserva_salas_cine.
- [ ] La URL abre: http://localhost/reserva_salas_cine/public/.
- [ ] La base reserva_salas_cine existe.
- [ ] database/schema.sql importa sin errores.
- [ ] database/seed.sql importa sin errores.
- [ ] Registro de usuario probado.
- [ ] Login probado.
- [ ] Logout probado.
- [ ] Rol usuario probado.
- [ ] Rol administrador probado.
- [ ] Cartelera probada.
- [ ] Detalle de película probado.
- [ ] Selección de horario probada.
- [ ] Selección de entradas probada.
- [ ] Selección de butacas probada.
- [ ] Reserva creada correctamente.
- [ ] Conflicto de butaca probado.
- [ ] Cancelación de reserva probada.
- [ ] Panel admin probado.
- [ ] Conflicto de funciones traslapadas probado.
- [ ] Errores PHP revisados en navegador o logs de Apache.
- [ ] No hay contraseñas reales ni datos sensibles en Git.

## Gestión del trabajo

El desarrollo se organiza mediante issues y PRs en GitHub.

Regla general:

- No cerrar issues sin evidencia.
- No aprobar PRs solo por claims.
- Cada PR debe incluir validación ejecutada.
- La compatibilidad XAMPP es obligatoria para la entrega final.

## Licencia

Proyecto académico para uso educativo.
