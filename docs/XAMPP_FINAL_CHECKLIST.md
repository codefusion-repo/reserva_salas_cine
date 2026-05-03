# XAMPP Final Validation Checklist

## Boundary

This checklist is the future manual validation script for Windows + XAMPP. It
does not execute final validation, does not declare readiness, and does not
replace reviewable evidence.

WSL checks are development-supporting only. Final readiness requires actual
Windows + XAMPP execution and evidence.

Use one result per item:

- Pass: executed in Windows + XAMPP and evidence captured.
- Fail: executed and did not meet the expected result.
- N/A: feature, route, table, or control is not implemented.

## Evidence Header

- Validator:
- Date:
- Branch or commit:
- Windows version:
- XAMPP version:
- PHP version:
- MySQL or MariaDB version:
- Browser:
- Evidence location:

## 1. XAMPP Environment

- [ ] Windows + XAMPP is the environment used for this run.
- [ ] Project is located at `C:\xampp\htdocs\reserva_salas_cine`.
- [ ] Apache starts from the XAMPP Control Panel.
- [ ] MySQL starts from the XAMPP Control Panel.
- [ ] phpMyAdmin opens from XAMPP.
- [ ] Application opens at `http://localhost/reserva_salas_cine/public/`.
- [ ] The page loads without visible PHP warnings, notices, or fatal errors.

## 2. Database

- [ ] Database `reserva_salas_cine` exists.
- [ ] Database host is `localhost`.
- [ ] Database user is `root`.
- [ ] Database password is empty.
- [ ] `database/schema.sql` imports through phpMyAdmin without errors.
- [ ] `database/seed.sql` imports through phpMyAdmin without errors.
- [ ] Table `users` exists.
- [ ] Table `rooms` exists.
- [ ] Table `movies` exists.
- [ ] Table `showtimes` exists.
- [ ] Table `reservations` exists.
- [ ] Table `reservation_seats` exists.
- [ ] Optional table `payments` exists only if payments are implemented.
- [ ] Optional table `payment_items` exists only if payments are implemented.
- [ ] Optional table `coupons` exists only if coupons are implemented.

## 3. Authentication

Use the local demo users from the project seed data when available:

- Admin: `admin@reservacine.local` / `AdminDemo123!`
- User: `usuario@reservacine.local` / `UsuarioDemo123!`

- [ ] Register a new normal user.
- [ ] Log in with the demo normal user.
- [ ] Log in with the demo admin user.
- [ ] Attempt invalid login and confirm it is rejected with a controlled error.
- [ ] Log out and confirm the session ends.
- [ ] Normal user cannot access admin pages or admin actions.
- [ ] Admin user can access admin pages.

## 4. Cartelera And Movie Flow

- [ ] Cartelera page loads.
- [ ] Movie posters load.
- [ ] Movie detail page loads.
- [ ] Active showtimes appear on the movie detail page.
- [ ] Unavailable routes or records show a controlled error or 404 if that
  behavior is implemented.

## 5. Reservations

- [ ] Choose a showtime.
- [ ] Choose ticket quantity.
- [ ] Select seats.
- [ ] Create a reservation.
- [ ] Duplicate active seat selection for the same showtime is blocked.
- [ ] Confirmed reservations block their selected seats.
- [ ] Cancelled reservations release their selected seats.
- [ ] Pending status is tested only if simulated checkout exists.
- [ ] Confirmation displays the correct movie, showtime, room, seats, total, and
  reservation data.
- [ ] My reservations page shows only the signed-in user's reservations.
- [ ] Cancel reservation.
- [ ] User cannot cancel another user's reservation.

## 6. Admin

- [ ] Admin can open room management.
- [ ] Admin can open showtime management.
- [ ] Admin can create a room.
- [ ] Admin can edit a room.
- [ ] Admin can deactivate a room.
- [ ] Admin can create a showtime.
- [ ] Admin can edit a showtime.
- [ ] Admin can activate a showtime.
- [ ] Admin can deactivate a showtime.
- [ ] Active showtime overlap in the same room is blocked.
- [ ] Inactive showtime does not block overlap validation.
- [ ] Admin all-reservations view is tested only if implemented.
- [ ] Admin payments management is tested only if implemented.
- [ ] Admin coupons management is tested only if implemented.

## 7. Optional Features

Mark N/A when the feature is not implemented.

- [ ] Ticket visual.
- [ ] Reservation visual code.
- [ ] Confiteria.
- [ ] Socios.
- [ ] Simulated checkout.
- [ ] Payments.
- [ ] Coupons.
- [ ] Profile.

## 8. Security And Quality

- [ ] No visible PHP warnings, notices, stack traces, SQL errors, or fatal
  errors appear in the browser.
- [ ] Apache/XAMPP logs were checked after the run.
- [ ] No real credentials are versioned.
- [ ] `config/database.local.php` is not versioned.
- [ ] No sensitive data is present in Git.
- [ ] POST forms use CSRF tokens if issue #35 is implemented.
- [ ] Admin POST authorization fails closed if issue #55 is implemented.
- [ ] Database access uses PDO.
- [ ] SQL writes and reads use prepared statements.
- [ ] Dynamic HTML output is escaped with `e()` or `htmlspecialchars`.

## 9. Final Evidence

Attach or reference evidence for:

- Environment and URL.
- Database imports and table list.
- Authentication results.
- Cartelera and movie detail.
- Reservation creation, duplicate-seat block, cancellation, and ownership
  checks.
- Admin room and showtime checks.
- Optional features marked Pass or Fail.
- Security and quality checks.
- Any N/A item with a short reason.

This checklist is complete only when every applicable item has Pass, Fail, or
N/A plus evidence. It still does not declare final readiness by itself.
