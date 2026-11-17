import { test, expect } from '@playwright/test';
import { LAMP } from './helpers';

// Crawlers (Google, WhatsApp, LinkedIn) do not run JavaScript. These tests read the raw HTML the
// server sends, with the `request` fixture instead of a browser, which is exactly what they see.

const BASE = 'http://127.0.0.1:8001';

test.describe('what a crawler receives', () => {
  test('the home page asks to be listed and describes itself', async ({ request }) => {
    const html = await (await request.get('/')).text();

    expect(html).toContain('<meta name="robots" content="index, follow">');
    expect(html).toContain('<title inertia>Home - Baskit</title>');
    expect(html).toContain(`<link rel="canonical" href="${BASE}/">`);
    expect(html).toContain('<meta property="og:site_name" content="Baskit">');
  });

  test('a product page carries its title, address, preview and structured data', async ({ request }) => {
    const html = await (await request.get(`/products/${LAMP.slug}`)).text();

    expect(html).toContain(`<title inertia>${LAMP.name} - Baskit</title>`);
    expect(html).toContain('<meta name="robots" content="index, follow">');
    expect(html).toContain(`<link rel="canonical" href="${BASE}/products/${LAMP.slug}">`);
    expect(html).toContain('<meta property="og:type" content="product">');

    const jsonLd = html.match(/<script type="application\/ld\+json">(.*?)<\/script>/s);
    expect(jsonLd, 'the page has structured data').not.toBeNull();

    const data = JSON.parse(jsonLd![1]);
    expect(data['@type']).toBe('Product');
    expect(data.name).toBe(LAMP.name);
    expect(data.offers.price).toBe('20.00');
    expect(data.offers.priceCurrency).toBe('USD');
    expect(data.offers.availability).toBe('https://schema.org/InStock');
  });

  test('a search is not listed, but its links may be followed', async ({ request }) => {
    const html = await (await request.get('/shop?search=lamp')).text();

    expect(html).toContain('<meta name="robots" content="noindex, follow">');
    expect(html).not.toContain('rel="canonical"');
  });

  test('a sorted shop points back to the plain address', async ({ request }) => {
    const html = await (await request.get('/shop?sort=price_asc')).text();

    expect(html).toContain('<meta name="robots" content="index, follow">');
    expect(html).toContain(`<link rel="canonical" href="${BASE}/shop">`);
  });

  for (const path of ['/cart', '/login', '/register']) {
    test(`${path} is not listed`, async ({ request }) => {
      const html = await (await request.get(path)).text();

      expect(html).toContain('<meta name="robots" content="noindex, nofollow">');
    });
  }

  test('the title seen in the browser matches the one in the HTML', async ({ page }) => {
    await page.goto(`/products/${LAMP.slug}`);

    await expect(page).toHaveTitle(`${LAMP.name} - Baskit`);
  });
});

test.describe('robots.txt and sitemap.xml', () => {
  test('robots.txt blocks everything outside production', async ({ request }) => {
    // The test server runs with APP_ENV=testing; the production rules are covered by the Pest tests.
    const response = await request.get('/robots.txt');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/plain');
    expect(await response.text()).toBe('User-agent: *\nDisallow: /\n');
  });

  test('sitemap.xml lists the home page, the shop and the products, as valid XML', async ({ request }) => {
    const response = await request.get('/sitemap.xml');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/xml');

    const xml = await response.text();
    expect(xml).toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">');
    expect(xml).toContain(`<loc>${BASE}/</loc>`);
    expect(xml).toContain(`<loc>${BASE}/shop</loc>`);
    expect(xml).toContain(`<loc>${BASE}/products/${LAMP.slug}</loc>`);
    expect(xml).toMatch(/<loc>[^<]*\/shop\?category=[a-z-]+<\/loc>/);
  });

  test('sitemap.xml never lists searches, sorted pages or private areas', async ({ request }) => {
    const xml = await (await request.get('/sitemap.xml')).text();

    for (const forbidden of ['search=', 'sort=', 'page=', '/cart', '/admin', '/login', '/orders']) {
      expect(xml, forbidden).not.toContain(forbidden);
    }
  });

  test('neither file sets a cookie', async ({ request }) => {
    for (const path of ['/robots.txt', '/sitemap.xml']) {
      const response = await request.get(path);

      expect(response.headers()['set-cookie'], path).toBeUndefined();
    }
  });
});
