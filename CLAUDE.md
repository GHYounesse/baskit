# CLAUDE.md

Baskit is a Laravel 12 + Inertia v2 + React 18 (plain JSX, no TypeScript) e-commerce app: Stripe checkout (test mode), a rule-based shopping assistant, an admin panel, Docker deployment. SQLite for tests and local dev, PostgreSQL 16 in production. Controllers return Inertia pages; there is no separate API. The README covers features and setup; `docs/DESIGN.md` covers the assistant, search and inventory design.

## Rules that always apply

- Money is integer cents on the server; the browser never sends a price. Format it in the UI with `utils/money.js`.
- Change stock only through `InventoryService`, and order status only through `OrderLifecycleService`.
- Use `whereLike`, never `where(..., 'like', ...)` (PostgreSQL `LIKE` is case-sensitive).
- A new public page must pass its own `seo` prop (`Support\StoreSeo`) or it stays `noindex`.
- Anything that changes a cart total must change both `CartService::pricing` and the amount check in `OrderService`.
- Run `./vendor/bin/pint` before committing (CI runs `pint --test`; check its exit code, not piped output).
- Commits: Conventional Commits, see Conventions below.

## Commands

```bash
composer setup              # first install: deps, .env, key, migrate, npm install, build
composer dev                # php artisan serve + queue:listen + pail + vite, together
npm run build               # production frontend build (public/build)

composer check              # the fast loop: pint, phpstan, tests in parallel, eslint, vitest (about 90s)
composer check:full         # check + dependency audits + Playwright
composer lint / analyse / security   # pieces of it: pint --test, phpstan, composer + npm audit
npm run lint / test:unit    # eslint, vitest (utils/money.test.js)

./vendor/bin/pint           # code style (fixes; `composer lint` only checks)
php artisan test            # whole suite (also: composer test, ./vendor/bin/pest)
php artisan test --filter=test_name
./vendor/bin/pest tests/Feature/Critical/StockTest.php --filter="last unit"

npm run test:e2e            # builds the frontend, then runs Playwright (Chromium)
npx playwright test e2e/cart.spec.ts   # one spec; needs a fresh `npm run build` first

php artisan search:reindex  # rebuild the SQLite FTS5 index
php artisan inventory:verify  # stock must equal the sum of its ledger; exits non-zero on drift
php artisan schedule:work   # carts:prune-guests and inventory:verify run daily
```

Tests need a `.env` with an `APP_KEY` (`cp .env.example .env && php artisan key:generate`); `phpunit.xml` sets the rest (in-memory SQLite).

## Feedback loop

Run `composer check` before saying a change is done; it stops at the first failure.

- **Static analysis:** Larastan at level 5 (`phpstan.neon`). `phpstan-baseline.neon` holds known older findings: new code must not add to it, so fix the finding instead. Relations need a return type and a generic docblock (`@return HasMany<Review, $this>`), or the analyser cannot see them.
- **Mocks:** every test runs with `Http::preventStrayRequests()` (`tests/TestCase.php`), so a test that reaches Stripe or Gemini fails. Use `FakeStripe`, `Http::fake()`, or a fake `LlmClient` or `PaymentRefunder`.
- **Performance:** `tests/Feature/Performance/QueryBudgetTest.php` caps the queries per page and checks that adding products adds none. Fix an over-budget page with `with()` or `withCount()`; raise a budget only when the extra queries are needed.
- **Security:** `composer security` runs `composer audit` and `npm audit`; CI reports them without failing. Auth rules are in `tests/Feature/Critical/RolesTest.php`.

## Layout (non-obvious parts only)

- `app/Services/` holds the business logic: `CartService`, `OrderService`, `OrderLifecycleService`, `InventoryService`, plus `Ai/`, `Assistant/`, `Payments/`, `Search/`.
- `app/Support/` builds SEO data (`StoreSeo`, `PageSeo`, `Sitemap`). `app/Listeners/MergeGuestCart` merges the guest cart on login.
- `app/Http/Middleware/HandleInertiaRequests` shares props, including the default `noindex` `seo`. `bootstrap/app.php` sets middleware aliases and trusted proxies.
- `config/assistant.php` is the store wording; `config/ai.php` is the Gemini setup.
- `resources/js`: `Pages/` (Inertia pages), `Components/`, `Layouts/`, `utils/money.js`. Colors are CSS variables in `resources/css/app.css`.
- `tests/Feature/Critical/` are Pest tests, `tests/Support/FakeStripe` fakes Stripe, `e2e/` is Playwright, `database/seeders/E2ESeeder` holds the e2e fixtures.
- Do not edit generated or local-only folders: `vendor/`, `node_modules/`, `public/build/`, `playwright-report/`, `test-results/`, `.docker-local/`.

## Architecture

**Payments and orders** (spans the most files). The server computes every amount in cents (`price_cents`, `CartService::pricing`). `CheckoutController::createPaymentIntent` creates the Stripe PaymentIntent with the user and coupon in its metadata. `OrderService::createFromPaymentIntent` creates the order from a *succeeded* intent and is idempotent (unique `stripe_payment_intent_id`; a second call returns the existing order), because two paths call it: the browser redirect (`CheckoutController::complete`) and `StripeWebhookController`. It re-checks stock and that `amount_received` equals the cart total after the coupon; on `InsufficientStockException` or `CouponException` the payment is refunded and no order exists.

**Order lifecycle.** `OrderLifecycleService` does every later status change (ship, deliver, refund, cancel) under a row lock with an `OrderEvent` timeline entry. Refunds go through the `PaymentRefunder` interface (`StripeRefunder`, idempotency key `order-{id}-refund`); tests swap in a fake.

**Stock.** `InventoryService` locks the product *then* the variant (always in that order, to avoid deadlocks), refuses to go below zero and writes the `StockMovement` ledger in the same transaction. A product with variants holds no stock of its own: `products.stock` is the sum of its variants and the service keeps it in step. A plain `save()` on `Product` is caught by a model safety net and logged as a manual adjustment; raw SQL bypasses it and `inventory:verify` catches that.

**Carts.** `CartService` keys a guest cart on the session's `cart_token`; cart items are lines of `(product, variant)`.

**Database portability.** Search uses FTS5 on SQLite (a `product_search` virtual table from a migration) and a portable `LIKE` engine elsewhere, both behind `Search\ProductSearch`. PostgreSQL sorts `NULL` first on `DESC` (the rating sort adds `NULLS LAST`). Engine differences show up only on PostgreSQL: `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=… DB_DATABASE=… php artisan test` (exported variables win over `phpunit.xml`). The FTS5 tests fail there by design.

**Assistant and AI.** The assistant is rule-based (`RuleBasedEngine`) behind the `ConversationEngine` interface bound in `AppServiceProvider`. Separately, `Ai\LlmClient` (`GeminiClient`) powers `ProductDrafter`, which suggests a name, description and category on the admin new-product form. It is on only when `GEMINI_API_KEY` is set; tests use a fake client.

**SEO.** Crawlers do not run JavaScript, so tags are written on the server by `resources/views/partials/seo.blade.php`. Canonical and sitemap addresses come from `APP_URL`, never the request host. `/robots.txt` and `/sitemap.xml` are routes (no session middleware, no static file), and robots blocks everything unless `APP_ENV=production`. See the SEO section in `docs/DESIGN.md`.

**Routes and frontend.** `/` is the home page (`home`); the catalogue is `/shop` (`shop.index`), so a link meaning "the shop" must not be `/`. Admin routes use the `admin` middleware alias plus policies. Pages take their layout from `Page.layout = ...` and use Ziggy's global `route()`. Tailwind color names (`primary`, `ink`, `surface`, `stroke`, `panel`) map to the CSS variables in `tailwind.config.js`; use them, not hex values. Headings use Baloo 2 (`font-display`), text uses Figtree.

## Tests

- Older tests are class-based PHPUnit in `tests/Feature`; newer critical-path tests are Pest in `tests/Feature/Critical`. `tests/Pest.php` applies `TestCase` and `RefreshDatabase` to that folder only, so Pest files elsewhere must opt in. New payment, stock and role tests go in `Critical`.
- `Tests\TestCase` calls `withoutVite()`, so PHP tests need no frontend build.
- `FakeStripe::install()` / `uninstall()` replaces Stripe's HTTP client, so the real `StripeClient`, controllers and webhook signature check run with no network. Use fakes for `PaymentRefunder` and `LlmClient` too.
- Image-upload tests need the PHP GD extension; without it the two that draw an image are skipped.
- Playwright starts its own app on `127.0.0.1:8001` with `database/e2e.sqlite`, runs `migrate:fresh --seed` plus `E2ESeeder`, and logs in once per role (`e2e/auth.setup.ts`). Fixtures: product `E2E Desk Lamp` ($20, stock 5), accounts `admin@example.com` and `customer@example.com`, password `password`.

## Docker and deployment

`compose.yaml` is the production stack on one VPS (`app`, `web`, `queue`, `scheduler`, PostgreSQL); `web` listens only on `127.0.0.1:8081` and the host's Nginx terminates TLS. Migrations never run at container start: `deploy.sh` runs them in a one-off container. Set `TRUSTED_PROXIES` in the server `.env` (template `.env.production.example`). `docker build --build-arg WITH_DEV=1` keeps Faker for demo seeders; local trial images only. `compose.dev.yaml` is Postgres plus Vite. Server setup: `docs/SERVER-SETUP.md`.

## Conventions

**Commits**
- Conventional Commits: `type(scope): subject`, scope optional. One or two lines: the subject, plus at most one short body line when the why is not obvious.
- Types: `feat`, `fix`, `refactor`, `test`, `docs`, `style` (formatting only), `perf`, `build` (Composer, npm, Vite, Docker), `ci`, `chore`, `revert`. A breaking change gets `!` (`feat(api)!: ...`) and says what breaks.
- Subject: imperative, lower case, no trailing period, about 72 characters or fewer, e.g. `fix(search): sort ratings with NULLS LAST on PostgreSQL`.
- Each commit stays under 100 lines of diff (added plus removed; lockfiles and `public/build` excluded). Larger changes are split into commits that each pass tests, for example migration and model, then service, then controller and page, then tests. Check with `git diff --cached --shortstat`.
- One logical change per commit. Commits before this convention have no prefix; do not rewrite them.

**Branches:** short kebab-case names off `main` (`seo-basics`, `ai-product-drafting`); `test/...` for test-only work.

## Frontend work

Before writing or changing anything in `resources/js` or `resources/css`, invoke these skills with the Skill tool:

- `anthropic-skills:vercel-react-best-practices` for React performance and structure: waterfalls, bundle size, re-renders, rendering. This app is client-side React on Inertia and Vite, not Next.js, so skip the Next.js and server-component rules (`server-*`, `next/dynamic`, `after()`, RSC props).
- `anthropic-skills:frontend-design` for UI and visual design: layout, typography, spacing, states. Keep the existing design system: theme colors and the Baloo 2 and Figtree fonts (see Routes and frontend), and light and `.dark` mode.

When the two disagree with a rule in this file, this file wins.

## Laravel best practices

Adapted from [alexeymezenin/laravel-best-practices](https://github.com/alexeymezenin/laravel-best-practices); only what fits this repo.

- **Structure:** no logic in route files. Controllers validate (form request), call a service and return a response. A method does one thing; split long ones. Fat models only for relations, scopes and casts; business rules live in `app/Services`.
- **Eloquent first:** use Eloquent and collections over raw SQL. Raw SQL is for the cases that need it (FTS5, `COALESCE` grouping, `LOWER()` matches) and always with bound parameters.
- **N+1:** eager-load with `with()` before looping over relations; never query inside Blade or React-facing loops. Use `chunk()` or `lazy()` for large data sets (jobs, commands).
- **Mass assignment:** every model sets `$fillable`, and controllers pass `$request->validated()`, never `$request->all()`.
- **Config:** read `env()` only inside `config/*.php`; everywhere else use `config('...')`. Put store text and magic values in config or constants, not inline.
- **Container:** resolve dependencies by injection (constructor or method), not `new Service`. Bind interfaces in `AppServiceProvider`, as done for `PaymentRefunder`, `LlmClient` and `ConversationEngine`.
- **Dates:** store UTC timestamps, format at the edge (accessor, Resource or the frontend `utils/date.js`).
- **Framework first:** use the standard tools (form requests, policies, events and listeners, jobs, mail, rate limiters) and do not override framework internals. No HTML, JS or CSS in PHP classes or Blade views beyond the Inertia root.
- **Naming:** PSR-12. Controllers, models, seeders, form requests singular (`OrderController`, `UpdateProductRequest`); tables and route URIs plural; route names snake_case with dots (`admin.products.index`); columns and model properties snake_case; foreign keys `product_id`; pivot tables singular model names in alphabetical order; relations camelCase (singular for `hasOne` and `belongsTo`, plural for the rest); variables and methods camelCase; Blade views kebab-case; config files snake_case.
- **Readability:** descriptive names beat comments; comment the why, not the what. Prefer short, clear syntax (`when()`, null-safe `?->`, `??`, `fn`) when it reads better.
- **Tests:** in-memory database (already set up in `phpunit.xml`). Existing test names stay as they are (`test_snake_case` and Pest `it(...)`), so the guide's camelCase test rule does not apply.

## Code review

When asked to review, read `docs/REVIEW.md` first: it lists the files that need close review, what to check in each, and which files change together.

## Gotchas

- A stale `public/hot` (left when a Vite dev server stops) makes pages render blank because assets load from a dead dev server. Delete it, or restart Vite. Vite inside Docker needs `VITE_DEV_ORIGIN` (set in `compose.dev.yaml`, read in `vite.config.js`).
- `composer.json` pins `config.platform.php` to 8.2.0 on purpose: production runs PHP 8.3 and CI 8.2, and a newer local PHP would lock packages that need 8.4+. Keep the pin when running `composer update`.
