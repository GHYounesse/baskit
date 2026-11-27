import ProductCard from '@/Components/ProductCard';
import SpeedLines from '@/Components/SpeedLines';
import ShopLayout from '@/Layouts/ShopLayout';
import { ASK_EVENT } from '@/utils/assistant';
import { formatMoney } from '@/utils/money';
import { Head, Link, usePage } from '@inertiajs/react';

const container = 'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8';

// Buttons that sit on the deep-green panel. The panel is the same in both themes, so these use
// fixed colours (the dark theme's brand green) instead of the tokens that flip with the theme.
const onPanelPrimary =
  'inline-flex h-12 items-center justify-center rounded-lg bg-[#12C982] px-6 font-semibold text-[#04231B] transition hover:bg-[#20D991] focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-panel';
const onPanelSecondary =
  'inline-flex h-12 items-center justify-center rounded-lg px-6 font-semibold text-white ring-1 ring-inset ring-white/35 transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white';

// Questions the assistant answers well from the store's own data; each one opens the chat and asks it.
const EXAMPLE_QUESTIONS = ['Headphones under $150', 'Something for the kitchen', "What's best rated?"];

const askChip =
  'rounded-full px-4 py-2 text-sm font-medium text-white ring-1 ring-inset ring-white/35 transition hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white';

function AskAssistant() {
  const ask = (message) => window.dispatchEvent(new CustomEvent(ASK_EVENT, { detail: { message } }));

  return (
    <div className="mt-10">
      <p className="text-sm text-white/70">Not sure what to pick? Ask the shop assistant.</p>
      <div className="mt-3 flex flex-wrap gap-2">
        {EXAMPLE_QUESTIONS.map((question) => (
          <button key={question} type="button" onClick={() => ask(question)} className={askChip}>
            {question}
          </button>
        ))}
      </div>
    </div>
  );
}

// Where each hero photo lands inside its box, how it is tilted, and when it starts to move.
// The tilt follows the brand's speed lines.
const SLOTS = [
  { box: 'left-[36%] top-0 w-[46%]', tilt: '-3deg', delay: '0ms' },
  { box: 'left-0 top-[34%] w-[35%]', tilt: '4deg', delay: '140ms' },
  { box: 'right-0 top-[50%] w-[36%]', tilt: '-5deg', delay: '280ms' },
  { box: 'left-[37%] bottom-0 w-[28%]', tilt: '3deg', delay: '420ms' },
];

function HeroPhotos({ products }) {
  return (
    <div className="relative mx-auto aspect-[5/6] w-full max-w-md lg:max-w-none">
      {products.slice(0, SLOTS.length).map((product, i) => {
        const slot = SLOTS[i];

        return (
          <Link
            key={product.id}
            href={`/products/${product.slug}`}
            className={`hero-tile absolute block rounded-xl bg-surface p-2 text-ink shadow-xl shadow-black/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-white ${slot.box}`}
            style={{ '--tilt': slot.tilt, '--delay': slot.delay }}
          >
            <span className="hero-trail pointer-events-none absolute right-full top-1/2 hidden -translate-y-1/2 flex-col items-end gap-2 pr-3 sm:flex" aria-hidden="true">
              <span className="h-1.5 w-10 rounded-full bg-[#12C982]/60" />
              <span className="h-1.5 w-6 rounded-full bg-[#12C982]/60" />
              <span className="h-1.5 w-8 rounded-full bg-[#12C982]/60" />
            </span>
            <span className="block aspect-square overflow-hidden rounded-lg bg-canvas">
              <img src={product.image_url} alt="" className="h-full w-full object-cover" />
            </span>
            <span className="mt-2 block truncate px-1 text-sm font-semibold">{product.name}</span>
            <span className="block px-1 pb-1 text-sm text-ink-muted">{formatMoney(product.price_cents)}</span>
          </Link>
        );
      })}
    </div>
  );
}

function Hero({ hero, signedIn }) {
  const { assistant } = usePage().props;

  return (
    <section className="relative overflow-hidden bg-panel text-white">
      <div className="text-[#12C982]">
        <SpeedLines />
      </div>

      <div className={`${container} relative grid items-center gap-12 py-14 sm:py-20 lg:grid-cols-[1.05fr_0.95fr] lg:gap-8 lg:py-24`}>
        <div>
          <h1 className="font-display text-[clamp(2.5rem,6vw,4.75rem)] font-extrabold leading-[0.98] tracking-tight">
            <span className="block">Fill your basket.</span>
            <span className="block">We&rsquo;ll handle</span>
            <span className="block">the rest.</span>
          </h1>
          <p className="mt-6 max-w-[34rem] text-lg leading-relaxed text-white/75">
            Browse the catalogue, add what you like and check out in a few minutes. Cancel any order before it ships and
            the money goes straight back to your card.
          </p>
          <div className="mt-9 flex flex-wrap gap-3">
            <Link href="/shop" className={onPanelPrimary}>
              Start shopping
            </Link>
            {signedIn ? (
              <Link href="/orders" className={onPanelSecondary}>
                See my orders
              </Link>
            ) : (
              <Link href="/register" className={onPanelSecondary}>
                Create an account
              </Link>
            )}
          </div>
          {assistant?.enabled && <AskAssistant />}
        </div>

        {hero.length > 0 && <HeroPhotos products={hero} />}
      </div>
    </section>
  );
}

function Categories({ categories }) {
  return (
    <section className={`${container} pt-16 sm:pt-24`}>
      <h2 className="font-display text-3xl font-bold text-ink sm:text-4xl">Shop by category</h2>
      <p className="mt-2 max-w-xl text-ink-muted">What is on the shelves right now, one category at a time.</p>

      <ul className="mt-8 divide-y divide-stroke border-y border-stroke">
        {categories.map((category) => (
          <li key={category.id}>
            <Link
              href={`/shop?category=${category.slug}`}
              className="group -mx-3 flex items-center justify-between gap-4 rounded-lg px-3 py-5 transition hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:py-6"
            >
              <span className="min-w-0">
                <span className="block font-display text-2xl font-bold text-ink transition group-hover:text-primary sm:text-4xl">
                  {category.name}
                </span>
                <span className="mt-1 block text-sm text-ink-muted">
                  {category.products_count} {category.products_count === 1 ? 'product' : 'products'}
                </span>
              </span>

              <span className="flex shrink-0 -space-x-3" aria-hidden="true">
                {category.thumbs.map((thumb) => (
                  <img
                    key={thumb.id}
                    src={thumb.image_url}
                    alt=""
                    className="h-12 w-12 rounded-full object-cover ring-2 ring-canvas group-hover:ring-surface sm:h-16 sm:w-16"
                  />
                ))}
              </span>
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}

function JustIn({ products }) {
  return (
    <section className={`${container} pt-16 sm:pt-24`}>
      <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
        <h2 className="font-display text-3xl font-bold text-ink sm:text-4xl">Just in</h2>
        <Link href="/shop" className="text-sm font-medium text-primary hover:text-primary-hover">
          See the whole catalogue
        </Link>
      </div>

      <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        {products.map((product) => (
          <ProductCard key={product.id} product={product} />
        ))}
      </div>
    </section>
  );
}

const PROMISES = [
  {
    title: 'Pay by card, safely',
    body: 'Payments go through Stripe. Your card number never touches our servers.',
  },
  {
    title: 'Cancel until it ships',
    body: 'Changed your mind? Cancel from your orders page and the refund goes back to your card.',
  },
  {
    title: 'Follow every order',
    body: 'Each order has a timeline from placed to delivered, with the tracking number once it ships.',
  },
  {
    title: 'Ask the assistant',
    body: 'Not sure what to pick? The chat button in the corner finds products and checks stock or your orders.',
  },
];

function Promises() {
  return (
    <section className={`${container} pt-16 sm:pt-24`}>
      <h2 className="font-display text-3xl font-bold text-ink sm:text-4xl">What you can count on</h2>
      <dl className="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0">
        {PROMISES.map((promise) => (
          <div key={promise.title} className="border-l-2 border-primary ps-5 lg:pe-6">
            <dt className="font-display text-xl font-bold text-ink">{promise.title}</dt>
            <dd className="mt-2 text-sm leading-relaxed text-ink-muted">{promise.body}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

function ClosingBand() {
  return (
    <section className="mt-16 bg-panel text-white sm:mt-24">
      <div className={`${container} flex flex-wrap items-center justify-between gap-6 py-12 sm:py-16`}>
        <h2 className="font-display text-3xl font-bold sm:text-5xl">Your basket is waiting.</h2>
        <Link href="/shop" className={onPanelPrimary}>
          Start shopping
        </Link>
      </div>
    </section>
  );
}

export default function Home({ hero, categories, justIn }) {
  const { auth } = usePage().props;

  return (
    <div className="bg-canvas text-ink">
      <Head title="Home" />

      <Hero hero={hero} signedIn={Boolean(auth.user)} />
      {categories.length > 0 && <Categories categories={categories} />}
      {justIn.length > 0 && <JustIn products={justIn} />}
      <Promises />
      <ClosingBand />
    </div>
  );
}

Home.layout = (page) => <ShopLayout>{page}</ShopLayout>;
