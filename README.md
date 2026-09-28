# Baskit

[![CI](https://github.com/GHYounesse/baskit/actions/workflows/ci.yml/badge.svg)](https://github.com/GHYounesse/baskit/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A full-stack ecommerce app built with **Laravel 12**, **Inertia.js** and **React**: product catalog, guest and user carts, Stripe checkout (test mode), order history, and an admin panel for products and orders.

<!-- Add screenshots here, e.g. ![Shop](docs/screenshots/shop.png) -->

## Features

**Shopping**
- Browse products by category, with search and pagination
- Guest cart that is merged into the user's cart on login or registration
- Stripe Elements card checkout
- Order history and order detail pages

**Payments**
- Payments are verified server-side against Stripe before an order is created
- Stripe webhook (`payment_intent.succeeded`) creates the order if the user never returns to the site
- Idempotent order creation: one order per PaymentIntent, enforced by a unique index
- Stock is checked and locked inside a database transaction; the payment is refunded if stock runs out after paying

**Admin**
- Product management (create, edit, delete, activate)
- Order list with status updates
- Admin-only routes behind an `admin` middleware

**Other**
- Authentication (register, login, password reset, profile) via Laravel Breeze
- Order access restricted to the owner or an admin (`OrderPolicy`)
- Scheduled cleanup of abandoned guest carts
- CI running Pint, tests and a frontend build

## Tech stack

| Layer | Technology |
| --- | --- |
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 18, Inertia.js 2, Tailwind CSS 3, Vite |
| Payments | Stripe (test mode) |
| Database | SQLite by default, MySQL supported |
| Tooling | Laravel Pint, PHPUnit, GitHub Actions |

## Getting started

### Prerequisites

- PHP 8.2+ and Composer
- Node.js 18+ and npm
- SQLite (bundled with PHP) or MySQL
- A free [Stripe account](https://dashboard.stripe.com/register) (test mode) and the [Stripe CLI](https://docs.stripe.com/stripe-cli)

### Installation

```bash
git clone https://github.com/GHYounesse/baskit.git
cd baskit

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed
```

### Configure Stripe

1. In the Stripe Dashboard (Test mode), open **Developers → API keys** and copy the keys into `.env`:

   ```
   STRIPE_KEY=pk_test_...
   STRIPE_SECRET=sk_test_...
   ```

2. Forward webhooks to your local app and copy the signing secret it prints:

   ```bash
   stripe listen --events payment_intent.succeeded --forward-to localhost:8000/stripe/webhook
   ```

   ```
   STRIPE_WEBHOOK_SECRET=whsec_...
   ```

3. Clear the config cache if you changed `.env` after starting the server:

   ```bash
   php artisan config:clear
   ```

### Run the app

Use three terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

```bash
stripe listen --events payment_intent.succeeded --forward-to localhost:8000/stripe/webhook
```

Visit `http://127.0.0.1:8000`.

### Demo accounts

Seeding creates these accounts (password: `password`):

| Role | Email |
| --- | --- |
| Admin | `admin@example.com` |
| Customer | `customer@example.com` |

It also creates 10 random customers, useful for the admin order views. Accounts you register through the UI are regular customers.

### Test cards

Use any future expiry, any CVC and any postal code.

| Card number | Result |
| --- | --- |
| `4242 4242 4242 4242` | Payment succeeds |
| `4000 0000 0000 0002` | Card is declined |

## Configuration

| Variable | Description |
| --- | --- |
| `STRIPE_KEY` | Stripe publishable key (`pk_test_...`) |
| `STRIPE_SECRET` | Stripe secret key (`sk_test_...`) |
| `STRIPE_WEBHOOK_SECRET` | Webhook signing secret (`whsec_...`) |
| `DB_CONNECTION` | `sqlite` (default) or `mysql` |
| `QUEUE_CONNECTION` | Queue driver (`database` by default) |

## Scheduled tasks

Abandoned guest carts are deleted daily by `php artisan carts:prune-guests` (default: older than 30 days, change with `--days`). In production, add the standard Laravel scheduler cron entry:

```
* * * * * cd /path/to/baskit && php artisan schedule:run >> /dev/null 2>&1
```

Locally, `php artisan schedule:work` runs it.

## Project structure

```
app/
  Http/Controllers/     Shop, cart, checkout, orders, Stripe webhook, admin
  Services/             CartService (guest/user carts), OrderService (order creation)
  Listeners/            MergeGuestCart (runs on login)
  Policies/             OrderPolicy
  Console/Commands/     carts:prune-guests
resources/js/
  Pages/                Inertia pages (Shop, Cart, Checkout, Orders, Admin, Auth)
  Layouts/              ShopLayout, AuthenticatedLayout
routes/web.php          All web routes
```

## Development

```bash
./vendor/bin/pint        # code style
php artisan test         # tests
npm run build            # production frontend build
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the contribution guidelines.

## Roadmap

- Product image uploads
- Shipping address form at checkout
- Order confirmation emails
- Admin dashboard with sales statistics

## License

MIT. See [LICENSE](LICENSE).
