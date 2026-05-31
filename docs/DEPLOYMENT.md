# Deployment

## Production Model
- Target: VPS deployment at `https://nethammereda.ru`.
- Runtime: Nginx + PHP-FPM.
- Background processing: queue workers managed by `systemd`.

## Safety Rules
- Do not store production credentials in the repository.
- Do not commit `.env`, database dumps, private keys, backups, or logs.
- Do not run destructive reset commands on production.

## CI/CD Note
- This repository includes CI checks for tests and build validation.
- Deployment automation is intentionally out of scope in this repository setup.
- If deployment automation is added later, use GitHub Actions secrets.
