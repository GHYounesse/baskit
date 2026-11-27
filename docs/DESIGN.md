# Design notes

The deeper details behind Baskit, moved out of the README to keep it short.

## Shopping assistant

The assistant is built in, rule-based and deterministic: the same message always gets the same answer, every rule is tested, and it never makes up a price, a stock level or a policy. A message goes through three steps in `App\Services\Assistant`:

1. `IntentClassifier` decides what is being asked (searching, availability, order status, cancelling, store questions, small talk). `EntityExtractor` pulls out the details: a price limit, a size, a colour, a category, an order number, and the product being talked about. It reuses the search spell-correction, so "headphnes" still works.
2. `ProductReplies` and `OrderReplies` answer from the real data (`AssistantCatalog`, the customer's own orders), and wording about how the store works lives in `config/assistant.php`, so it matches what the app actually does.
3. A small `AssistantContext` in the visitor's server-side session remembers the last search and product, which is what makes "what about in blue?" or "the second one" work. It holds only references, not personal details, and "new chat" clears it.

It is read-only (it can point to the page where an order is cancelled, but never cancels anything), only looks at the signed-in customer's own orders, answers the same way for an order that doesn't exist and one that belongs to someone else, and renders everything as plain text, so nothing typed into it can become markup. Messages are rate limited (`ASSISTANT_RATE_LIMIT`, 30 a minute by default). Set `ASSISTANT_ENABLED=false` to turn it off.

Everything goes through the `ConversationEngine` interface. To add a language model later (for example a free hosted one such as Groq or Gemini, or a local one through Ollama), write an engine that implements it and calls `AssistantCatalog` and the order lookups as its tools, then bind it in `AppServiceProvider`. Nothing else has to change.

## Search

Product search runs on SQLite's built-in full-text engine (FTS5), so it needs no extra service. A `product_search` index is created by a migration and kept in step automatically when products or categories change. Matches are ranked with BM25, weighting the name 10x, the category 4x and the description 1x.

- `php artisan search:reindex` rebuilds the index from scratch (for example after importing products directly into the database).
- On other databases (MySQL, Postgres) the app falls back to a portable `LIKE` search with the same rules, chosen automatically. There is nothing to index.
- Both engines implement `App\Services\Search\ProductSearch`. To use Meilisearch, Typesense or Laravel Scout instead, write one more class with its four methods and bind it in `AppServiceProvider`.
- The suggestions endpoint is `GET /search/suggest?q=...` (rate limited, JSON). Queries are reduced to plain words before they reach the engine, so search syntax and SQL can't be injected through the box.

## Inventory integrity

Each product's stock history starts from zero, so its changes must always add up to the stock on hand. `php artisan inventory:verify` checks that (and that every entry's running balance follows from the one before it), prints any mismatch and exits non-zero, so it works in CI or a cron job. The admin Inventory page shows the same check as a banner, and the scheduler runs it daily and logs an error on failure.

Stock is changed through `App\Services\InventoryService`, which locks the product, refuses to go below zero and writes the ledger entry in the same transaction. A change made some other way (a seeder, tinker, a plain `update()`) is still recorded as a manual adjustment by a safety net on the `Product` model; only raw SQL bypasses the log, and the integrity check catches that.

## Scheduled tasks

Abandoned guest carts are deleted daily by `php artisan carts:prune-guests` (default: older than 30 days, change with `--days`). In production, add the standard Laravel scheduler cron entry:

```
* * * * * cd /path/to/baskit && php artisan schedule:run >> /dev/null 2>&1
```

Locally, `php artisan schedule:work` runs it.

## SEO

Search engines and link previews (WhatsApp, LinkedIn, X) do not run JavaScript, so Baskit writes its SEO tags **on the server**, in the HTML `<head>`.

| What | Where |
|---|---|
| The facts about a page (title, description, image, official address, whether it may be listed) | `App\Support\PageSeo` |
| The rules for the public pages: home, shop, category, product | `App\Support\StoreSeo` |
| The `<head>` tags | `resources/views/partials/seo.blade.php`, included by `app.blade.php` |
| `/robots.txt` and `/sitemap.xml` | `SeoController` and `App\Support\Sitemap` |

**Safe by default.** A page that sets nothing is sent with `noindex, nofollow`, so the cart, checkout, orders and admin can never show up in search results by accident. A public page opts in by passing its own `seo` prop:

```php
return Inertia::render('Shop/Show', [
    'seo' => StoreSeo::product($product, $ratings)->toArray(),
    // ...
]);
```

**What is listed, and how**
- The home page, the shop, each category with products, and every active product. A product page also carries structured data (price, stock, average rating) so Google can show them in its results.
- Searches are not listed (`noindex, follow`), pages 2 and up have their own address and title, and sorting points back to the plain address.
- `/sitemap.xml` follows the same rules. `/robots.txt` blocks only areas that must never be visited; pages that carry a `noindex` tag stay open so robots can read it.
- **Outside production, `robots.txt` blocks everything** (`APP_ENV` is not `production`), so a test copy of the site is never indexed. Production needs `APP_ENV=production`.
- Every address is built from `APP_URL`, never from the request's `Host` header, so it must be the real public address (`https://shop.younesghalloum.dev`).

**After the first deploy:** add the site in [Google Search Console](https://search.google.com/search-console), verify the domain with a DNS record in Cloudflare, then submit `https://shop.younesghalloum.dev/sitemap.xml`. Indexing takes days to weeks.

## Docker and deployment

The app ships as one image (PHP 8.3-FPM, built from the multi-stage [Dockerfile](Dockerfile)) that runs behind the host's Nginx on a single VPS. [compose.yaml](compose.yaml) starts five containers:

| Service | Role |
|---|---|
| `app` | PHP-FPM; also copies the built assets to a volume for `web` |
| `web` | Nginx, published only on `127.0.0.1:8081` (the host's Nginx proxies to it) |
| `queue` | `queue:work`, sends the queued e-mails |
| `scheduler` | `schedule:work`, runs the daily jobs |
| `db` | PostgreSQL 16 on a named volume, never published |

**Deploy flow:** every push to `main` runs [docker-deploy.yml](.github/workflows/docker-deploy.yml): tests, then the image is built and pushed to GHCR tagged with the git SHA and `latest`, then the workflow connects over SSH and runs [deploy.sh](deploy.sh) (pull, start, `migrate --force` in a one-off container, health check). If a migration or the health check fails, `deploy.sh` goes back to the previous tag by itself; if the public `/up` smoke test fails, the workflow rolls back. Migrations are never run at container start, and a rollback does not undo them.

**Configuration:** the server's `.env` (never in git) is built from [.env.production.example](.env.production.example). `RUN_OPTIMIZE=true` caches config, routes and views when a container starts. `TRUSTED_PROXIES=*` makes Laravel trust the host Nginx's `X-Forwarded-Proto`, so URLs and secure cookies use https. Stripe runs in test mode only.

**One-time server setup** (Docker, deploy user, host Nginx, HTTPS, GitHub secrets): see [docs/SERVER-SETUP.md](docs/SERVER-SETUP.md).

Try the production stack locally (needs Docker). It runs from a git-ignored `.docker-local/` folder, so your development `.env` stays untouched. `WITH_DEV=1` keeps Faker in this trial image only, so the demo seeders work; the production build never sets it.

```bash
docker build --build-arg WITH_DEV=1 -t ghcr.io/ghyounesse/baskit:local-demo .
mkdir -p .docker-local/docker/nginx && cp compose.yaml .docker-local/ && cp docker/nginx/default.conf .docker-local/docker/nginx/
cp .env.production.example .docker-local/.env
```

In `.docker-local/.env` set `APP_KEY` (`php artisan key:generate --show`), `DB_PASSWORD`, `IMAGE_TAG=local-demo`, `APP_URL=http://127.0.0.1:8081`, `SESSION_SECURE_COOKIE=false` and `MAIL_MAILER=log`. Then:

```bash
cd .docker-local
docker compose up -d
docker compose run --rm --no-deps -T app php artisan migrate --force
docker compose run --rm --no-deps -T app php artisan db:seed --force
```

Open http://127.0.0.1:8081 (`admin@example.com` / `password`). Card payments need your Stripe **test** keys in that `.env`. Stop it with `docker compose down` (add `-v` to also delete the data).

For local development, [compose.dev.yaml](compose.dev.yaml) provides PostgreSQL and the Vite dev server (`make up`, `make down`, `make logs`, `make test`).
