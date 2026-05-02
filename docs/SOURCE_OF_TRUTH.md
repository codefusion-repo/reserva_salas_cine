# Source Of Truth - Reserva Salas Cine

## Purpose

This document separates workflow truth, functional truth, visual truth, and
implemented state for `codefusion-repo/reserva_salas_cine`.

Project OS from `codefusion-repo/project-os` is the workflow, process, and
operations reference. It does not replace functional requirements from
`docs/functional/Trabajo 3 web.pdf`.

## Source Hierarchy

Use this order when deciding scope, implementation, validation, or readiness:

1. Explicit PM decisions.
2. Current target repository evidence.
3. `docs/functional/Trabajo 3 web.pdf` for functional/business rules.
4. `docs/mockups/approved/` for visual UI.
5. Target issues, PRs, and validation evidence.
6. `README.md` and target docs.
7. Project OS reference for workflow/operations.
8. Historical chat, routing, or handoff context.

If sources conflict, report the conflict and use the safer target-specific
source until the PM clarifies.

## Source Roles

- Workflow and agent discipline: Project OS, adapted into `AGENTS.md`,
  `docs/SOURCE_OF_TRUTH.md`, `docs/SCOPE.md`, and `docs/VALIDATION.md`.
- Functional and business rules: `docs/functional/Trabajo 3 web.pdf`.
- Functional summary for implementation: `docs/BUSINESS_RULES.md`.
- Visual UI: approved mockups in `docs/mockups/approved/`.
- Implemented state: repository files, commits, issues, PRs, and validation
  output.

## Durable Decisions

- Target repository: `codefusion-repo/reserva_salas_cine`.
- Project OS reference repository: `codefusion-repo/project-os`.
- Official target is Windows + XAMPP.
- Official path: `C:\xampp\htdocs\reserva_salas_cine`.
- Official URL: `http://localhost/reserva_salas_cine/public/`.
- Official DB: `reserva_salas_cine`.
- DB defaults: `localhost`, `root`, empty password.
- WSL is local development equivalent only.
- Final readiness requires XAMPP validation.
- Approved mockups in `docs/mockups/approved/` are visual source of truth.
- Future UI must match approved mockups as closely as possible.
- Logo may remain placeholder until the PM provides the final asset.
- Browser chats are read-only for GitHub writes unless the PM explicitly
  authorizes a scoped action.

## Project OS Boundary

Project OS guidance may be used for:

- Branch preflight.
- Execution modes.
- PM/write boundaries.
- Evidence expectations.
- Review-before-closure discipline.
- Source hierarchy discipline.

Project OS guidance must not import Project OS Lab assumptions into this
project. Do not add labels, release gates, automation, CI/CD, boards, context
packs, handoff folders, internal operating state, or target-irrelevant templates
unless a future PM decision explicitly scopes them.

## Evidence Rule

No issue, PR, merge, release, readiness claim, or closure is valid without
reviewable evidence. WSL evidence can support development, but final readiness
requires XAMPP evidence.
