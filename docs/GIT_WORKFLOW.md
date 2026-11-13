# Git workflow

Guidelines for version control on the single-store Laravel project.

## Branch model

- **`main`** is the stable branch. It should always build and pass the test suite for the work merged into it.
- Create **feature branches** for domain milestones and features (for example `feature/catalog-models`, `feature/checkout-strategy-c`).
- Open pull requests or merge only after local verification when collaborating.

## Before you commit

1. Run relevant tests (`php artisan test`, or a focused filter for the area you changed).
2. Review `git status` and `git diff` — no accidental files.
3. Run Laravel Pint on changed PHP files when you touched application code: `vendor/bin/pint --dirty` (requires Git).

## What must never be committed

- **`.env`** and other environment files with secrets (use **`.env.example`** as the template only).
- **`vendor/`**, **`node_modules/`**, compiled **`public/build`**, and caches.
- **Logs**, **`storage/*.key`**, local SQLite databases, SQL dumps, or customer/order data.
- **`auth.json`** (Composer credentials), private keys (`.pem`, `.key`), or API tokens.

If unsure whether a file is sensitive, do not stage it; ask or add it to `.gitignore` first.

## Commits

- Prefer **small, verifiable milestones** (e.g. migrations only, Store models only) with clear messages.
- Use conventional prefixes where helpful: `feat:`, `fix:`, `chore:`, `docs:`, `test:`.
- Do not amend or force-push **`main`** on a shared remote without team agreement.

## History and remotes

- Do **not rewrite shared history** (`push --force` to `main`, aggressive rebase on published branches).
- Do **not push** to a remote until a remote is configured and pushing is explicitly approved.
- Never commit real credentials even on private remotes.

## Local setup checklist

```bash
git clone <repository-url>
cd modular-online-store
cp .env.example .env
php artisan key:generate
composer install
php artisan migrate --seed   # local dev only
php artisan test
```

## Related documents

- `AGENTS.md` — architecture and testing rules
- `.gitignore` — ignored paths
