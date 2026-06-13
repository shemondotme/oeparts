# OeParts — Commit Message Convention

OeParts uses **Conventional Commits** (`conventionalcommits.org/en/v1.0.0/`).

## Format

```
<type>(<scope>): <short description>

[optional body]

[optional footer(s)]
```

The first line must be ≤ 72 characters.

---

## Types

| Type | When to use |
|------|-------------|
| `feat` | A new feature |
| `fix` | A bug fix |
| `perf` | Performance improvement (no behaviour change) |
| `refactor` | Code restructure without behaviour change or bug fix |
| `test` | Adding or updating tests |
| `docs` | Documentation only |
| `chore` | Maintenance — dependency updates, CI config, build scripts |
| `security` | Security fix or hardening |
| `revert` | Reverts a previous commit |

---

## Scopes (common)

Use the area of the codebase most affected:

| Scope | Covers |
|-------|--------|
| `search` | OEM search — `SearchService`, `SearchController`, search views |
| `cart` | Cart — `CartService`, `CartController`, cart views |
| `checkout` | Checkout flow |
| `orders` | Order management — `OrderService`, `OrderResource` |
| `payments` | Airwallex integration — `PaymentService`, webhooks |
| `admin` | Filament admin panel (general) |
| `auth` | Authentication — both web and admin guards |
| `email` | Mail jobs, Mailable classes, email templates |
| `queue` | Queue jobs, workers, priorities |
| `migrations` | Database migrations |
| `models` | Eloquent models |
| `api` | API routes and controllers |
| `seo` | SEO meta, sitemaps, robots.txt |
| `cms` | CMS sections, pages, blog |
| `deps` | Composer or npm dependency changes |
| `ci` | GitHub Actions workflows |
| `git` | `.gitignore`, `.gitattributes` |
| `docs` | Documentation files |

Omit the scope when the change is truly cross-cutting.

---

## Examples

```
feat(search): add fuzzy match for OEM numbers with common typos

fix(checkout): resolve race condition when two tabs submit simultaneously

security(public): remove publicly accessible debug scripts

perf(search): cache OEM normalization results in Redis for 1 hour

refactor(cart): extract quantity validation into CartService

test(coupon): add coverage for usage-limit and per-user-limit rejection

chore(deps): update filament/filament to 5.7.0

docs(contributing): add commit convention reference

fix(migrations): resolve duplicate timestamp collisions on 2026_06_08
```

---

## Breaking Changes

Add `BREAKING CHANGE:` in the footer (not the type line):

```
feat(auth): switch admin sessions to database driver

BREAKING CHANGE: existing admin sessions are invalidated. All admin users
must re-login after deploying this change.
```

Or use `!` after the type for shorthand:

```
feat(auth)!: switch admin sessions to database driver
```

---

## Rules

- Use **imperative mood** in the description: "add", "fix", "remove" — not "added", "fixed", "removed"
- Do **not** end the description with a period
- Reference GitHub Issues in the footer: `Closes #42` or `Refs #17`
- One logical change per commit; avoid "WIP" or "misc changes" commits
- Squash fixup commits before opening a PR

---

## Branch Naming

Branches should mirror the commit type:

```
feature/oem-search-fuzzy-match
fix/cart-coupon-race-condition
hotfix/security-debug-scripts
chore/update-filament-5.7
docs/commit-convention
release/v1.2.0
```
