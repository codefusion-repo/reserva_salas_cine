# Claude Instructions - Reserva Salas Cine

## Purpose

This file defines Claude-specific execution guidance for
`codefusion-repo/reserva_salas_cine`.

`AGENTS.md` remains the canonical repository-wide instruction file for
terminal-agent and repository-modifying work. Claude must follow `AGENTS.md`
first. This file only adds Claude-specific reminders and must stay aligned with
`AGENTS.md`.

## Scope

Use this file only when Claude Code or a Claude-based execution surface works in
this repository.

Do not treat this file as issue-specific context, Project Instructions, a
handoff packet, or GitHub write authorization. Current issue scope, explicit PM
decisions, target repo evidence, validation evidence, and `AGENTS.md` control
execution.

## Core Rules

- Keep the project XAMPP-first and dependency-light.
- Preserve the Project OS reference boundary: Project OS is workflow/process
  reference only and does not define target features, business rules, runtime
  behavior, validation results, or readiness.
- Use the target source hierarchy in `AGENTS.md` when sources conflict.
- Default to `review-only` unless the current PM task explicitly authorizes a
  different execution mode.
- Do not perform GitHub writes unless explicitly authorized by the PM in the
  current task.
- Do not create labels, boards, releases, tags, automation, CI/CD, context
  packs, or handoff folders unless explicitly scoped by the PM.
- Do not claim readiness without final Windows + XAMPP validation evidence.

## Before Editing

Before any local implementation edit, run and report the branch preflight from
`AGENTS.md`:

- `pwd`
- `git remote -v`
- `git branch --show-current`
- `git status --short --branch`
- `git log -1 --oneline`

Use a `work/*` branch when authorized and possible. If branch state, write
authorization, or scope is unclear, stop and report `needs_context`,
`needs_pm_decision`, `needs_pm_branch`, or `blocked` as appropriate.

## Validation

For documentation-only changes, run:

- `git diff --check`
- required file existence checks
- targeted grep checks when relevant

For feature work, WSL validation can support development, but final readiness
requires Windows + XAMPP validation.

## Reporting

Claude completion reports must include:

- issue or task reference
- repository and branch
- preflight result
- source files consulted
- files changed
- validation commands and evidence
- manual validation when applicable
- risks or follow-up work
- commit, push, PR, and GitHub write status

Agent reports are evidence for review. They are not review approval, closure,
merge approval, or readiness.
