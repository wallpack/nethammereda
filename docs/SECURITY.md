# Security

## Secret Management
- All secrets are environment-based (`.env`) and excluded from Git.
- Example values live in `.env.example` with empty/safe placeholders.

## Repository Hygiene
- `.gitignore` excludes local databases, dependencies, logs, cache, backups, and key files.
- CI is configured to run without production secrets.

## Pre-Push Checks
- Verify staged files before commit (`git diff --cached --name-only`).
- Ensure no `.env`, local DB, key material, dumps, or backup archives are staged.

## Recommended Enhancements
- Enable branch protection for `main`.
- Require pull request checks before merge.
- Add periodic secret scanning in CI if desired.
