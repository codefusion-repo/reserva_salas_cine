# Scope - Reserva Salas Cine

## Functional Base

The functional base is the room-reservation assignment in
`docs/functional/Trabajo 3 web.pdf`.

Mandatory academic scope:

- Register users with name, email, and password.
- Log in users.
- Maintain active session control.
- Support administrator and normal user roles.
- Show available rooms.
- Let administrators create, edit, and delete rooms.
- Let users create reservations by selecting room, date, and time.
- Let users view their reservations.
- Let users cancel reservations.
- Prevent time conflicts for the same room.
- Store at least users, rooms, and reservations.
- Provide login/register screens, main room panel, user reservations section,
  and room administration panel.
- Keep code organized between frontend, backend, and database.
- Validate forms, handle basic errors, and provide a clear functional
  interface.

## PM-Approved Cinema Adaptation

The PM approved a cinema-style adaptation for interface and product framing.

This means the user experience may present the room-reservation assignment as a
cinema reservation flow:

1. User logs in or registers.
2. User views a cinema-style listing.
3. User selects an available room or showtime framing.
4. User chooses date and time.
5. User creates a reservation.
6. User views or cancels reservations.

The adaptation must not erase the academic functional base: rooms, users,
reservations, dates, times, roles, and conflict validation remain mandatory.
In the current cinema adaptation, room/date/time selection is represented
through showtimes/functions tied to a movie and room.

## Visual Scope

Approved mockups in `docs/mockups/approved/` are the visual UI source of truth.
Future UI must match them as closely as possible.

The logo may remain placeholder until the PM provides the final asset.

## Technical Scope

- PHP vanilla.
- MySQL/MariaDB.
- XAMPP-first execution.
- PDO prepared statements.
- `password_hash` and `password_verify`.
- `htmlspecialchars` for dynamic HTML escaping.
- PHP form validation.
- Admin route protection by role.

## Out Of Scope

- Real payments.
- Payment gateway.
- Functional concessions.
- Real memberships.
- External APIs.
- Premium accounts.
- Laravel.
- Composer requirement.
- React or Next.
- Docker.
- Cloud deployment.
- CI/CD.
- Automation, boards, labels, releases, tags, context packs, handoff folders,
  or complex governance.

## Readiness Boundary

WSL Ubuntu may be used only as a local development equivalent. Final readiness
requires validation in Windows + XAMPP:

- Path: `C:\xampp\htdocs\reserva_salas_cine`
- URL: `http://localhost/reserva_salas_cine/public/`
- DB: `reserva_salas_cine`
- DB defaults: host `localhost`, user `root`, empty password

Documentation changes alone do not declare final delivery readiness.
