# Code review guide

Read this when reviewing a change. It lists which files need a close look and what to check in each. The rules themselves (cents, stock, `whereLike`, SEO opt-in, and so on) are in the Architecture section of `CLAUDE.md`; this file does not repeat them.

Open every file in the diff that falls in a high-scrutiny area below. Also open the files a change should have touched but did not (see "Files that change together").

## High scrutiny (money, stock, auth, data)

| Area | Files | Check |
| --- | --- | --- |
| Payments and orders | `Services/OrderService`, `Services/CartService`, `Controllers/CheckoutController`, `StripeWebhookController`, `Services/Payments/*` | Amounts are server-side cents. `createFromPaymentIntent` stays idempotent. Stock and coupon failures refund and create no order. Refund idempotency key kept. |
| Order lifecycle | `Services/OrderLifecycleService`, `Models/Order`, `OrderEvent`, `Exceptions/InvalidOrderTransitionException`, `Mail/*` | Row lock, an `OrderEvent` per change, invalid transitions rejected. |
| Stock | `Services/InventoryService`, `InventoryAudit`, `VariantService`, `Models/Product`, `ProductVariant`, `StockMovement` | Only `InventoryService` changes stock. Lock order product then variant, never below zero, ledger row in the same transaction. No raw SQL on stock. |
| Models and migrations | `app/Models/*`, `database/migrations/*`, `database/factories/*` | Fillable fields and casts. Money columns are integer cents. Foreign keys, unique indexes and cascades are intended. Runs on SQLite and PostgreSQL. A migration already run in production is never edited; add a new one. Check side effects in model `booted()` hooks (`Product`, `Order`, `Category`). |
| Authorization | `Policies/*`, `Middleware/EnsureUserIsAdmin`, `routes/web.php`, `routes/auth.php` | Admin routes sit in the `admin` group and use a policy. Users reach only their own orders, cart and wishlist. Public write routes are throttled. |
| Input validation | `Http/Requests/**` | Every write has a form request. No price, total or user id comes from the browser. |
| Search | `Services/Search/*`, `Console/Commands/ReindexProductSearch` | `whereLike`; same results on FTS5 and PostgreSQL; the index follows product changes. |
| Assistant and AI | `Services/Assistant/*`, `Services/Ai/*`, `config/assistant.php`, `config/ai.php` | No other customer's data in replies. LLM output is untrusted and validated. A missing `GEMINI_API_KEY` disables the feature without breaking the page. |
| SEO | `Support/StoreSeo`, `PageSeo`, `Sitemap`, `SeoController`, `partials/seo.blade.php` | Public pages opt in; private, search and empty pages stay `noindex`; canonical URLs from `APP_URL`. |
| Cart merge | `Listeners/MergeGuestCart`, `CartService` | A guest cart merges once on login with no duplicate lines or lost quantities. |
| Config and deploy | `bootstrap/app.php`, `config/*`, `Dockerfile`, `compose*.yaml`, `deploy.sh`, `docker/*`, `.env*.example`, `.github/workflows/*` | No secrets committed. No migrations at container start. `TRUSTED_PROXIES` and the `composer.json` platform pin untouched. |

## Normal scrutiny

- Controllers: logic in a service, input in a form request, access in a policy.
- Frontend (`resources/js/**`): theme colors not hex, money through `utils/money.js`, shop links use `route('shop.index')`, forms show validation errors.
- Seeders and factories: demo data only, never run in production.
- Docs (`README.md`, `CLAUDE.md`, `docs/*`): updated when a command, route, env variable or rule changes.

## Files that change together

- **Cart total** (shipping, tax, discount): `CartService::pricing`, the amount check in `OrderService::createFromPaymentIntent`, the checkout page, `tests/Feature/Critical/CheckoutPaymentTest.php`.
- **Order status or transition:** `OrderLifecycleService`, `OrderPolicy`, `OrderStatusBadge.jsx`, `OrderTimeline.jsx`, the admin order page, `OrderLifecycleTest`.
- **Stock rule:** `InventoryService`, `InventoryAudit`, `StockTest`.
- **Column used by search:** the model, a new migration, `product_search` index handling, `ReindexProductSearch`.
- **Public page:** its controller, a `seo` prop from `StoreSeo`, `Sitemap` if indexed, a test in `PublicPagesSeoTest` or `RobotsAndSitemapTest`.
- **Route or env variable:** `routes/web.php`, the policy or middleware, `.env.example`, `.env.production.example`, the README.
- **Model:** migration, factory, policy if users reach it, a test.

## Before approving

- `./vendor/bin/pint --test` and `php artisan test` pass; `npm run build` for frontend changes; the suite also run on PostgreSQL when a query may differ per engine.
- New behavior has a test; payment, stock and role changes have a Pest test in `tests/Feature/Critical`.
- Commits follow the Conventions in `CLAUDE.md`.
