# Reserva Salas Cine

Sistema web académico para gestionar reservas de salas de cine, desarrollado con PHP, JavaScript, HTML, CSS y MySQL, ejecutado localmente con XAMPP.

El proyecto adapta el enunciado de reserva de salas a un flujo tipo cine: cartelera, detalle de película, selección de función, selección de butacas y reserva.

## Stack

- PHP vanilla
- MySQL
- JavaScript
- HTML
- CSS
- XAMPP

## Alcance MVP

El sistema debe permitir:

- Registro e inicio de sesión de usuarios.
- Roles de usuario: administrador y usuario normal.
- Visualización de cartelera.
- Detalle de película con horarios disponibles.
- Selección de entradas.
- Selección de butacas.
- Creación de reservas.
- Visualización de reservas del usuario.
- Cancelación de reservas.
- Administración básica de salas, películas y funciones.
- Validación para evitar reservas duplicadas de la misma butaca en la misma función.
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
- Sistema avanzado de reportes.
- Compra real de productos de confitería.
- Membresías reales.

## Estructura sugerida

```text
reserva-salas-cine/
├── app/
│   ├── controllers/
│   ├── models/
│   ├── views/
│   ├── helpers/
│   └── middleware/
├── config/
│   ├── config.example.php
│   └── database.php
├── database/
│   ├── schema.sql
│   └── seed.sql
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── img/
│   └── uploads/
├── docs/
│   └── PROJECT_BRIEF.md
├── README.md
└── .gitignore
```
