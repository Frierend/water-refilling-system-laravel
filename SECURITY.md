# Security Policy

## Supported Version
Security fixes are applied to the active default branch of this repository.

## Reporting a Vulnerability
Please report vulnerabilities privately to the project maintainer.

When reporting, include:
- affected endpoint/module
- reproduction steps
- expected vs actual behavior
- screenshots/logs if available
- impact assessment (data exposure, privilege abuse, integrity risk)

Please do not open public issues for undisclosed vulnerabilities.

## Response Process
1. Acknowledge report receipt.
2. Reproduce and triage severity.
3. Prepare and test a minimal-risk patch.
4. Release fix and document mitigation.
5. Credit reporter when appropriate.

## Current Security Baseline
- Authentication and role middleware enforced on protected modules.
- Input validation and parameter whitelisting on dashboard/report filters.
- Transaction rollback guards on early-return stock validation paths.
- Structured audit logging enabled via `audit` log channel.
- Security response headers applied globally.
- CORS allowlist and methods controlled via environment variables.
- Dependency audit command available through Composer scripts.

## Operational Checks
Run these before deployment:
- `composer audit:composer`
- `composer audit:security`
- `composer audit:phase2`

Review logs:
- `storage/logs/laravel.log`
- `storage/logs/audit-*.log`
