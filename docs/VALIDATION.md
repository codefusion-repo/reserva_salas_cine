# Validation - Reserva Salas Cine

## Rule

The official target is Windows + XAMPP. WSL Ubuntu is only a local development
equivalent and does not replace final delivery evidence.

Final readiness requires XAMPP validation. Use
[`docs/XAMPP_FINAL_CHECKLIST.md`](XAMPP_FINAL_CHECKLIST.md) for the detailed
manual checklist and evidence structure.

## XAMPP Final Validation

Before delivery or readiness claims, validate:

- [ ] Detailed checklist exists and is used:
  `docs/XAMPP_FINAL_CHECKLIST.md`.

- [ ] Project is located at `C:\xampp\htdocs\reserva_salas_cine`.
- [ ] Apache starts in XAMPP.
- [ ] MySQL starts in XAMPP.
- [ ] URL opens: `http://localhost/reserva_salas_cine/public/`.
- [ ] Database exists: `reserva_salas_cine`.
- [ ] DB host is `localhost`.
- [ ] DB user is `root`.
- [ ] DB password is empty.
- [ ] `database/schema.sql` imports without errors.
- [ ] `database/seed.sql` imports without errors.
- [ ] Browser output and Apache logs show no blocking PHP errors.
- [ ] No secrets, local credentials, personal data dumps, XAMPP config, or
  runtime uploads are committed.

## Manual Functional Validation

Use this section as a short development-support summary. The full final XAMPP
matrix is [`docs/XAMPP_FINAL_CHECKLIST.md`](XAMPP_FINAL_CHECKLIST.md).

For the current cinema adaptation, validate:

- [ ] User registration with name, email, and password.
- [ ] Login.
- [ ] Logout or session end.
- [ ] Active session control.
- [ ] Normal user role.
- [ ] Administrator role.
- [ ] Cartelera listing from active movies.
- [ ] Movie detail with active showtimes.
- [ ] Showtime, ticket quantity, and seat selection.
- [ ] Reservation creation with selected showtime and seats.
- [ ] Seat conflict validation for active reservations in the same showtime.
- [ ] User-only reservation list.
- [ ] Reservation cancellation by the owning user.
- [ ] Admin-only room creation, editing, and deactivation.
- [ ] Admin-only showtime creation, editing, activation, and deactivation.
- [ ] Overlap validation for active showtimes in the same room.
- [ ] Required-field validation in PHP.
- [ ] Invalid-hour validation in PHP.
- [ ] Basic error handling.
- [ ] Dynamic output escaped with `htmlspecialchars` or `e()`.

Mark these as N/A until implemented:

- [ ] Admin all-reservations view (#32).
- [ ] Dedicated visual 404 (#22).
- [ ] Checkout, pagos, cupones, confiteria, and socios.
- [ ] CSRF on POST forms (#35), sequenced after #55.
- [ ] Admin POST authorization fail-closed (#55), sequenced before #35.

## WSL Development Equivalent

WSL may be used during development only.

Expected local equivalent:

- Path: `/var/www/html/reserva_salas_cine`
- URL: `http://localhost/reserva_salas_cine/public/`
- DB: `reserva_salas_cine`
- Web server: Apache
- DB server: MariaDB

Useful WSL checks:

- `sudo systemctl status apache2`
- `sudo systemctl status mariadb`
- `sudo mysql -e "CREATE DATABASE IF NOT EXISTS reserva_salas_cine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
- `sudo mysql reserva_salas_cine < database/schema.sql`
- `sudo mysql reserva_salas_cine < database/seed.sql`

If WSL needs different credentials, use ignored local config such as
`config/database.local.php`. Do not commit local credentials.

## Issue And PR Evidence

Every issue or PR should provide reviewable evidence:

- Files changed.
- Source documents used.
- Validation commands run.
- Manual validation notes.
- Known risks or skipped checks.

No issue, PR, merge, release, readiness claim, or closure is valid without
evidence. Documentation changes alone do not declare final delivery readiness.

## Documentation Validation

For documentation-only changes, run:

- `git diff --check`
- `test -f AGENTS.md`
- `test -f docs/SOURCE_OF_TRUTH.md`
- `test -f docs/SCOPE.md`
- `test -f docs/BUSINESS_RULES.md`
- `test -f docs/VALIDATION.md`
- `test -f docs/XAMPP_FINAL_CHECKLIST.md`
- `test -f "docs/functional/Trabajo 3 web.pdf"`

Optional targeted grep:

- `grep -RIn "XAMPP\|Project OS\|codefusion-repo/project-os\|docs/functional/Trabajo 3 web.pdf\|docs/mockups/approved\|BUSINESS_RULES\|reserva_salas_cine\|read-only" AGENTS.md docs README.md`
