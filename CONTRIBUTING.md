# Contributing

## Code style

- PHP: run `./vendor/bin/pint` before committing (Laravel Pint, included by default).
- JS/React: keep components small and colocated under `resources/js/Pages`; match the existing formatting.

## Commit messages

This repo follows [Conventional Commits](https://www.conventionalcommits.org/): `type: subject`, e.g. `feat: add product search filter`. Common types: `feat`, `fix`, `chore`, `docs`, `refactor`, `test`, `ci`.

## Tests

Run `php artisan test` before pushing. CI runs the same checks (Pint + tests + a frontend build) on every push and pull request — see `.github/workflows/ci.yml`.
