# CLAUDE.md

Baskit is a Laravel 12 + Inertia v2 + React 18 (plain JSX, no TypeScript) e-commerce app: Stripe checkout (test mode), a rule-based shopping assistant, an admin panel, Docker deployment. SQLite for tests and local dev, PostgreSQL 16 in production. Controllers return Inertia pages; there is no separate API. The README covers features and the assistant, search and inventory design.

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

./vendor/bin/pint           # code style
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

**SEO.** Crawlers do not run JavaScript, so tags are written on the server by `resources/views/partials/seo.blade.php`. Canonical and sitemap addresses come from `APP_URL`, never the request host. `/robots.txt` and `/sitemap.xml` are routes (no session middleware, no static file), and robots blocks everything unless `APP_ENV=production`. See the README's SEO section.

**Routes and frontend.** `/` is the home page (`home`); the catalogue is `/shop` (`shop.index`), so a link meaning "the shop" must not be `/`. Admin routes use the `admin` middleware alias plus policies. Pages take their layout from `Page.layout = ...` and use Ziggy's global `route()`. Tailwind color names (`primary`, `ink`, `surface`, `stroke`, `panel`) map to the CSS variables in `tailwind.config.js`; use them, not hex values. Headings use Baloo 2 (`font-display`), text uses Figtree.
