# Contributing to OeParts

Thank you for taking the time to contribute.

## Before You Start

- Read [ARCHITECTURE.md](../ARCHITECTURE.md) for patterns and folder structure
- Read [CLAUDE.md](../CLAUDE.md) for absolute rules (bcmath, guards, cache, mail, settings)
- Check open issues and pull requests before starting work on something new

## Development Setup

```bash
git clone https://github.com/YOUR_USERNAME/oeparts.git
cd oeparts
composer install
cp .env.testing .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install && npm run build
php artisan test
```

## Rules — Non-Negotiable

These rules exist to prevent production incidents. PRs that violate them will not be merged.

| Rule | Detail |
|---|---|
| **bcmath only** | Never use `+`, `-`, `*`, `/` on monetary values. Use `bcadd`, `bcmul`, `bcdiv`, `bcsub`. |
| **Two auth guards** | `auth()->user()` in frontend. `auth('admin')->user()` in admin. Never mix. |
| **No Cache::flush()** | Destroys all sessions. Use `Cache::forget('specific.key')` instead. |
| **Queue mail** | `dispatch(new SendXxxEmail(...))` — never `Mail::send()` or `Mail::queue()`. |
| **No hardcoded values** | VAT rates, OTP length, rate limits → always `settings('group.key', $default)`. |
| **OEM normalization** | Always search `normalized_oem` via `OemNormalizerService::normalize()`. |
| **Migrations append-only** | Never edit an existing migration. New column = new file. |
| **Server-side SEO** | All meta/hreflang/JSON-LD in Blade PHP. Never JavaScript. |
| **No Node on server** | `npm run build` runs locally. Commit `public/build/`. |
| **DECIMAL columns** | Prices and amounts: `DECIMAL(10,2)`. Never `FLOAT` or `DOUBLE`. |

## Code Style

- PHP: follow PSR-12, Laravel conventions
- Controllers are thin: validate → service → respond
- Business logic belongs in `app/Services/`
- JSON API responses always use the standard format from CLAUDE.md
- Use `#[Test]` attribute (PHP 8), not `/** @test */` doc-comments
- All tests use `RefreshDatabase` where they touch the DB

## Pull Request Process

1. Fork the repo and create a branch from `main` (`feature/your-feature` or `fix/your-bug`)
2. Write tests for new behaviour
3. Run `php artisan test` — all must pass
4. Run `php artisan view:cache && php artisan view:clear` — no Blade errors
5. Open a PR against `main` and fill in the template

## Reporting Security Issues

Do **not** open a public GitHub issue for security vulnerabilities. Use GitHub Security Advisories (private disclosure) instead.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
