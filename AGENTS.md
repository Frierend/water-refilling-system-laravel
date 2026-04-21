# AGENTS.md

## Documentation authority

Codex must create and maintain a single primary documentation file:
`MI_GAIL_MASTER_SOURCE_OF_TRUTH.md`

Do not create overlapping audit, checklist, security, or overview documents unless explicitly requested.
If smaller docs are needed, derive them from the master file.

## Documentation basis

The master file must be based on:

* the Project Criteria rubric
* the Project Documentation template
* the actual verified codebase
* existing repo documentation, merged carefully

## Verification rules

* Never invent features.
* Never mark a feature as implemented unless verified in code, routes, config, migrations, and tests.
* If docs and code conflict, prefer the verified codebase and record the mismatch.

## Audit logging rule

The project must document and, when applicable, implement two audit-log categories:

* `security` logs
* `system` logs

### security logs

Authentication, failed logins, lockouts, password/account lifecycle events, forced password changes, unauthorized-access attempts, and other security-sensitive actions.

### system logs

Order activity, inventory changes, delivery updates, customer-management operational changes, and other routine business/system events.

## Terminology

Use these terms consistently:

* Project: Mi-Gail Water System
* Administrative authority: owner
* Operational roles: delivery, helper

If legacy `admin` references exist in code or docs, explain them as legacy references rather than the primary role model.

## Redundant documentation

Consolidate overlapping documentation into the master file.
Mark old overlapping docs as deprecated or archive-ready after the master file is created.
