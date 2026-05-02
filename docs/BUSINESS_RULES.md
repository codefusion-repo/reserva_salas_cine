# Business Rules - Sistema Web de Reserva de Salas

## Objetivo General

Desarrollar una aplicación web que permita gestionar la reserva de salas en una
institución educativa o empresa, usando autenticación de usuarios, base de
datos y control de disponibilidad de horarios.

## Reglas Funcionales Obligatorias

### Autenticación

- Registro de usuario con nombre, correo y contraseña.
- Inicio de sesión.
- Control de sesión activa.
- Roles de usuario: administrador y usuario normal.

### Gestión De Salas

- Visualización de salas disponibles.
- Creación, edición y eliminación de salas solo por administrador.
- Los usuarios normales solo pueden ver las salas.
- Cada sala debe mostrar nombre, ubicación, capacidad y botón de reservar.

### Reservas

- Crear reserva seleccionando sala, fecha y horario.
- El usuario debe elegir fecha.
- El usuario debe elegir hora de inicio y fin.
- Visualizar reservas del usuario.
- Cancelar reservas.
- Validar conflictos de horario en una misma sala.
- No permitir reservas de una misma sala en horarios que se traslapen.
- No se puede reservar una sala si existe otra reserva que coincida en fecha y
  horario.
- Si no hay conflicto, la reserva se guarda en la base de datos.
- Al cancelar, el estado de la reserva cambia o se elimina.

### Administración

- Debe existir un usuario administrador encargado de gestionar las salas.
- El administrador puede ver todas las reservas del sistema.
- El administrador puede gestionar salas.
- El administrador puede supervisar el uso del sistema.

## Base De Datos Mínima

Debe almacenar como mínimo:

- Usuarios.
- Salas.
- Reservas.

Tablas recomendadas:

Usuarios:

- id
- nombre
- email
- contraseña
- rol

Salas:

- id
- nombre
- ubicación
- capacidad

Reservas:

- id
- id_usuario
- id_sala
- fecha
- hora_inicio
- hora_fin
- estado

## Interfaz Requerida

- Pantalla de login y registro.
- Panel principal con salas disponibles.
- Sección de reservas del usuario.
- Panel de administración para salas.
- Interfaz clara y funcional.

## Requisitos No Funcionales

- Código organizado en frontend y backend.
- Separación de frontend, backend y base de datos.
- Base de datos MySQL.
- Validación de formularios.
- Manejo básico de errores.
- Campos obligatorios.
- Evitar reservas vacías.
- Evitar horarios inválidos.
- Mensajes de error claros.

## Pruebas Requeridas

Se debe probar:

- Registro de usuario.
- Inicio de sesión.
- Creación de salas.
- Creación de reservas.
- Validación de conflictos.
- Cancelación de reservas.

## Requisitos Opcionales

- Filtro de salas por disponibilidad.
- Calendario visual de reservas.
- Diseño responsive.
- Mensajes de confirmación y error más elaborados.
- Filtros por fecha o sala.
- Confirmaciones de acción.
