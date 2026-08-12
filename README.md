# Baskit

Baskit is a full-stack ecommerce demo built with Laravel, Inertia.js, and React — a product catalog, cart, Stripe-powered checkout (test mode), order history, and an admin panel for managing products and orders.

## Tech stack

- Laravel 11
- Inertia.js
- React 18
- Tailwind CSS
- Stripe (test mode)
- SQLite (default) or MySQL

## Features

- Browse products by category, with search
- Cart with quantity updates
- Stripe Elements checkout
- Order history for logged-in users
- Admin panel for managing products and order status
- Auth (register/login/profile) via Laravel Breeze

## Getting started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- SQLite (bundled with PHP) or MySQL

### Setup

```bash
git clone https://github.com/your-username/baskit.git
cd baskit

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed
```

Run the app (two terminals):

```bash
php artisan serve
npm run dev
```

Visit `http://127.0.0.1:8000`.

### Default accounts

Seeding creates an admin account:

- **Admin:** `admin@example.com` / `password`

Or register a new account through the UI — it won't have admin access.

### Running tests

```bash
php artisan test
```

### License

MIT — see [LICENSE](LICENSE).
