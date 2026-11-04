import { defineConfig, devices } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const PORT = 8001;
const BASE_URL = `http://127.0.0.1:${PORT}`;

// The tests run against their own SQLite file, never the development database.
const E2E_DB = path.resolve(process.cwd(), 'database', 'e2e.sqlite');
fs.mkdirSync(path.dirname(E2E_DB), { recursive: true });
if (!fs.existsSync(E2E_DB)) fs.writeFileSync(E2E_DB, '');

/**
 * End-to-end tests. Build the frontend first (`npm run build`); `npm run test:e2e` does both.
 * Backend logic is covered by Pest (tests/Feature/Critical); these specs check the
 * browser journeys: login, cart, access control.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list'], ['html', { open: 'never' }]],

  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
  },

  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      dependencies: ['setup'],
    },
  ],

  // Fresh, seeded database every run, then the app on its own port.
  webServer: {
    command: `php artisan migrate:fresh --seed --force && php artisan db:seed --class=E2ESeeder --force && php artisan serve --host=127.0.0.1 --port=${PORT} --no-reload`,
    url: BASE_URL,
    reuseExistingServer: false,
    timeout: 120_000,
    env: {
      APP_ENV: 'testing',
      APP_DEBUG: 'false',
      APP_URL: BASE_URL,
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: E2E_DB,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'file',
      QUEUE_CONNECTION: 'sync',
      MAIL_MAILER: 'array',
      BCRYPT_ROUNDS: '4',
      // Never talk to real Stripe from these tests.
      STRIPE_KEY: 'pk_test_e2e',
      STRIPE_SECRET: 'sk_test_e2e',
      STRIPE_WEBHOOK_SECRET: 'whsec_e2e',
    },
  },
});
