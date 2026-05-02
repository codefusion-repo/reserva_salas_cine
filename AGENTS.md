# Agent Rules - Reserva Salas Cine

## Purpose

These rules apply to agents working in `codefusion-repo/reserva_salas_cine`.
They keep the academic PHP/XAMPP project scoped, simple, and reviewable.

Project OS from `codefusion-repo/project-os` is the workflow, process, and
operations reference for this target. It does not define application features or
business rules.

## Repository Identity

- Target repository: `codefusion-repo/reserva_salas_cine`.
- Project OS reference repository: `codefusion-repo/project-os`.
- Official environment: Windows + XAMPP.
- Official path: `C:\xampp\htdocs\reserva_salas_cine`.
- Official URL: `http://localhost/reserva_salas_cine/public/`.
- Official database: `reserva_salas_cine`.
- Official DB defaults: host `localhost`, user `root`, empty password.
- WSL Ubuntu is only a local development equivalent.

## Source Separation

- Workflow, process, and agent discipline: Project OS reference, adapted through
  this file and target docs.
- Functional and business rules: `docs/functional/Trabajo 3 web.pdf`.
- Visual UI source: `docs/mockups/approved/`.
- Implemented state: repository evidence, issues, PRs, commits, and validation
  output.

Project OS must not replace the functional requirements in
`docs/functional/Trabajo 3 web.pdf`.

## Source Hierarchy

When sources conflict, use this order:

1. Explicit PM decisions for Reserva Salas Cine.
2. Current target repository evidence.
3. `docs/functional/Trabajo 3 web.pdf` for functional/business rules.
4. `docs/mockups/approved/` for visual UI.
5. Target issues, PRs, and validation evidence.
6. `README.md` and target docs.
7. Project OS reference for workflow/operations.
8. Historical chat, routing, or handoff context.

If a conflict affects scope, validation, security, readiness, or write
authorization, stop and ask for PM clarification.

## Branch Preflight

Before local implementation edits, run and report:

- `pwd`
- `git remote -v`
- `git branch --show-current`
- `git status --short --branch`
- `git log -1 --oneline`

Use a `work/*` branch when authorized and possible. If the working tree has
unrelated changes, stop and report before editing.

## Write Boundaries

Browser chats are read-only for GitHub writes by default.

Execution modes are task-specific and must be declared in the routing packet,
issue, or PM prompt:

- `local implementation only` allows scoped local edits and validation, but no
  commit, push, PR, GitHub comment, labels, merge, issue closure, release, or
  tag.
- `full delegated execution including commit and PR` allows commit, push, and
  PR only when explicitly authorized by the PM in the current task.
- A task may explicitly authorize commit and push without authorizing PR
  creation.

No GitHub write is allowed without explicit scoped PM authorization in the
current task.

Do not modify PHP functional files, `database/schema.sql`,
`database/seed.sql`, mockup images, or `docs/functional/Trabajo 3 web.pdf`
unless a future PM decision explicitly scopes that work.

No issue, PR, merge, release, readiness claim, or closure is allowed without
reviewable evidence.

## Technical Rules

Keep the project XAMPP-first and dependency-light.

Allowed baseline:

- PHP vanilla.
- MySQL/MariaDB.
- HTML, CSS, and JavaScript.
- Apache through XAMPP for final validation.

Do not add Laravel, Composer requirements, React, Next, Docker, cloud services,
external APIs, payment gateways, CI/CD, automation, boards, labels, releases,
tags, context packs, handoff folders, or complex governance unless a future PM
decision explicitly scopes them.

Security rules:

- Use PDO.
- Use prepared statements.
- Use `password_hash` and `password_verify`.
- Escape dynamic HTML output with `htmlspecialchars`.
- Protect admin routes by role.
- Validate forms in PHP.
- Do not commit secrets, local DB credentials, dumps with personal data, XAMPP
  config, or runtime uploads.

## UI Rules

- Approved mockups in `docs/mockups/approved/` are visual source of truth.
- Future UI must match approved mockups as closely as possible.
- The logo may remain placeholder until the PM provides the final asset.
- Do not modify mockup images unless explicitly scoped.

## Validation

For documentation-only changes, run:

- `git diff --check`
- Required file existence checks.
- Targeted grep checks when requested.

For feature work, WSL validation may support development, but final delivery
readiness requires Windows + XAMPP validation.

## Report Format

Completion reports must include:

1. Preflight result.
2. Project OS reference files consulted.
3. Source files used.
4. Branch and git status.
5. Files changed.
6. Summary of changes.
7. Validation commands and evidence.
8. Confirmation of files not touched.
9. Risks or follow-up work.
10. Commit/PR/GitHub write status.
