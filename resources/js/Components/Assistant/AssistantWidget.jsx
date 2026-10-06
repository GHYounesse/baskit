import OrderStatusBadge from '@/Components/OrderStatusBadge';
import { StarRating } from '@/Components/StarRating';
import { formatMoney } from '@/utils/money';
import { Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useId, useRef, useState } from 'react';

const STORAGE_KEY = 'baskit:assistant';
const MAX_STORED = 40;
const MAX_LENGTH = 300;

const WELCOME = {
  role: 'assistant',
  parts: [
    {
      type: 'text',
      text: "Hi! I'm the shop assistant. I can help you find products, check stock and sizes, and track or cancel your orders. What are you looking for?",
    },
  ],
  suggestions: ['Find a gift under $50', "What's popular right now?", "Where's my order?", 'How do I use a discount code?'],
};

function ChatIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true" {...props}>
      <path d="M21 12a8 8 0 01-11.600 7.100L4 20l1-4.200A8 8 0 1121 12z" strokeLinejoin="round" />
      <path d="M8.500 11.500h.01M12 11.500h.01M15.500 11.500h.01" strokeWidth="2.25" strokeLinecap="round" />
    </svg>
  );
}

function CloseIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true" {...props}>
      <path d="M5 5l10 10M15 5L5 15" strokeLinecap="round" />
    </svg>
  );
}

function RestartIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true" {...props}>
      <path d="M16 10a6 6 0 11-1.800-4.300M16 3.500V6.500h-3" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function SendIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" {...props}>
      <path d="M3.400 2.600a.8.8 0 01.900-.1l13 7a.8.8 0 010 1.400l-13 7a.8.8 0 01-1.100-1L5.200 10 3.200 3.700a.8.8 0 01.2-1.100z" />
    </svg>
  );
}

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true" {...props}>
      <path d="M4 9h16l-1.500 10.500a2 2 0 01-2 1.500H7.500a2 2 0 01-2-1.500L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

// Links come from the server, but only same-site paths are ever followed.
const safeHref = (href) => (typeof href === 'string' && href.startsWith('/') && !href.startsWith('//') ? href : '/');

function ProductCard({ product }) {
  return (
    <Link
      href={safeHref(product.url)}
      className="flex gap-3 rounded-lg border border-stroke bg-surface p-2.5 transition hover:border-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
    >
      <span className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-md bg-canvas text-ink-muted/50">
        {product.image_url ? (
          <img src={product.image_url} alt="" className="h-full w-full object-cover" />
        ) : (
          <BasketIcon className="h-6 w-6" />
        )}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate text-sm font-medium text-ink">{product.name}</span>
        <span className="block text-sm font-semibold text-ink">{formatMoney(product.price_cents)}</span>
        <span className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-ink-muted">
          {product.reviews > 0 && (
            <span className="inline-flex items-center gap-1">
              <StarRating value={product.rating} size="h-3 w-3" />({product.reviews})
            </span>
          )}
          <span className={product.in_stock ? 'text-brand' : ''}>{product.in_stock ? 'In stock' : 'Sold out'}</span>
          {product.options && <span>{product.options}</span>}
        </span>
      </span>
    </Link>
  );
}

function OrderCard({ order }) {
  return (
    <div className="rounded-lg border border-stroke bg-surface p-3 text-sm">
      <div className="flex items-center justify-between gap-2">
        <span className="font-medium text-ink">Order #{order.id}</span>
        <OrderStatusBadge status={order.status} />
      </div>
      <div className="mt-1 text-xs text-ink-muted">
        Placed {order.placed} · {formatMoney(order.total_cents)}
      </div>

      <ul className="mt-2 space-y-0.5 text-ink">
        {order.items.slice(0, 3).map((item, i) => (
          <li key={i} className="truncate">
            {item.name} <span className="text-ink-muted">× {item.quantity}</span>
          </li>
        ))}
        {order.items.length > 3 && <li className="text-xs text-ink-muted">and {order.items.length - 3} more</li>}
      </ul>

      {order.tracking && (
        <div className="mt-2 text-xs text-ink-muted">
          {order.tracking.carrier}
          {order.tracking.number && (
            <>
              {' '}
              tracking <span className="font-medium text-ink">{order.tracking.number}</span>
            </>
          )}
        </div>
      )}

      {order.timeline?.length > 0 && (
        <ol className="mt-2 space-y-1 border-l border-stroke pl-3 text-xs">
          {order.timeline.map((event, i) => (
            <li key={i} className="text-ink-muted">
              <span className="text-ink">{event.title}</span> · {event.at}
            </li>
          ))}
        </ol>
      )}

      <Link href={safeHref(order.url)} className="mt-2 inline-block text-xs font-medium text-primary hover:text-primary-hover">
        {order.can_cancel ? 'Open order to cancel it' : 'View order'}
      </Link>
    </div>
  );
}

function Parts({ parts }) {
  return (
    <div className="space-y-2">
      {parts.map((part, i) => {
        if (part.type === 'text') {
          // Plain text only: React escapes it, so nothing typed or stored can become markup.
          return (
            <p key={i} className="whitespace-pre-line text-sm leading-relaxed text-ink">
              {part.text}
            </p>
          );
        }
        if (part.type === 'products') {
          return (
            <div key={i} className="space-y-2">
              {part.products.map((product) => (
                <ProductCard key={product.slug} product={product} />
              ))}
            </div>
          );
        }
        if (part.type === 'orders') {
          return (
            <div key={i} className="space-y-2">
              {part.orders.map((order) => (
                <OrderCard key={order.id} order={order} />
              ))}
            </div>
          );
        }
        if (part.type === 'links') {
          return (
            <div key={i} className="flex flex-wrap gap-2">
              {part.links.map((link) => (
                <Link
                  key={link.href + link.label}
                  href={safeHref(link.href)}
                  className="rounded-lg border border-primary px-3 py-2 text-sm font-medium text-primary transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                  {link.label}
                </Link>
              ))}
            </div>
          );
        }

        return null;
      })}
    </div>
  );
}

function loadStored() {
  try {
    const stored = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? 'null');

    return Array.isArray(stored?.messages) && stored.messages.length > 0 ? stored.messages : [WELCOME];
  } catch {
    return [WELCOME];
  }
}

/**
 * The shop assistant: a button in the corner that opens a chat panel. It
 * answers from the store's own data (see App\Services\Assistant) and can only
 * look things up, never change anything.
 */
export default function AssistantWidget() {
  const { assistant, auth } = usePage().props;
  const url = usePage().url;

  const panelId = useId();
  const launcherRef = useRef(null);
  const inputRef = useRef(null);
  const logRef = useRef(null);

  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState([WELCOME]);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => setMessages(loadStored()), []);

  useEffect(() => {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify({ messages: messages.slice(-MAX_STORED) }));
    } catch {
      // Storage blocked: the chat still works, it just won't survive a reload.
    }
  }, [messages]);

  // Keep the newest message in view.
  useEffect(() => {
    const log = logRef.current;
    if (log) log.scrollTop = log.scrollHeight;
  }, [messages, busy, open]);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  const close = useCallback(() => setOpen(false), []);

  // The launcher only exists while the panel is shut, so focus goes back to it once it has rendered.
  const wasOpen = useRef(false);
  useEffect(() => {
    if (wasOpen.current && !open) launcherRef.current?.focus();
    wasOpen.current = open;
  }, [open]);

  const send = async (raw) => {
    const message = raw.trim().slice(0, MAX_LENGTH);
    if (!message || busy) return;

    setMessages((prev) => [...prev, { role: 'user', text: message }]);
    setText('');
    setBusy(true);

    try {
      const { data } = await window.axios.post('/assistant/message', { message });
      setMessages((prev) => [...prev, { role: 'assistant', parts: data.parts, suggestions: data.suggestions }]);
    } catch (error) {
      const tooFast = error.response?.status === 429;

      setMessages((prev) => [
        ...prev,
        {
          role: 'assistant',
          parts: [
            {
              type: 'text',
              text: tooFast
                ? "You're sending messages quite quickly. Give it a moment and try again."
                : "Sorry, I couldn't reach the store just now. Please try again in a moment.",
            },
          ],
          suggestions: [],
          failed: message,
        },
      ]);
    } finally {
      setBusy(false);
      inputRef.current?.focus();
    }
  };

  const restart = async () => {
    setMessages([WELCOME]);
    try {
      await window.axios.post('/assistant/reset');
    } catch {
      // Nothing to tidy up if the server can't be reached.
    }
    inputRef.current?.focus();
  };

  if (!assistant?.enabled) return null;

  // Staff screens and the payment page are no place for a chat bubble.
  if (url.startsWith('/admin') || url.startsWith('/checkout')) return null;

  const last = messages[messages.length - 1];
  const chips = !busy && last?.role === 'assistant' ? (last.suggestions ?? []) : [];

  return (
    <>
      {!open && (
        <button
          ref={launcherRef}
          type="button"
          onClick={() => setOpen(true)}
          aria-label="Open shopping assistant"
          aria-expanded="false"
          aria-controls={panelId}
          className="fixed bottom-4 right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-canvas motion-safe:hover:scale-105"
        >
          <ChatIcon className="h-7 w-7" />
        </button>
      )}

      {open && (
        <section
          id={panelId}
          role="dialog"
          aria-label="Shopping assistant"
          onKeyDown={(e) => e.key === 'Escape' && close()}
          className="fixed inset-0 z-40 flex flex-col bg-surface text-ink sm:inset-auto sm:bottom-4 sm:right-4 sm:h-[min(36rem,calc(100vh-2rem))] sm:w-[24rem] sm:overflow-hidden sm:rounded-xl sm:border sm:border-stroke sm:shadow-2xl"
        >
          <header className="flex items-center justify-between gap-2 border-b border-stroke bg-primary px-4 py-3 text-white">
            <div className="min-w-0">
              <h2 className="font-display text-base font-bold leading-tight">Shopping assistant</h2>
              <p className="text-xs text-white/80">Answers from live store data</p>
            </div>
            <div className="flex shrink-0 items-center gap-1">
              <button
                type="button"
                onClick={restart}
                aria-label="Start a new chat"
                className="flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
              >
                <RestartIcon className="h-5 w-5" />
              </button>
              <button
                type="button"
                onClick={close}
                aria-label="Close assistant"
                className="flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
              >
                <CloseIcon className="h-5 w-5" />
              </button>
            </div>
          </header>

          <div ref={logRef} role="log" aria-live="polite" aria-relevant="additions" aria-busy={busy} className="flex-1 space-y-3 overflow-y-auto bg-canvas px-4 py-4">
            {messages.map((message, i) =>
              message.role === 'user' ? (
                <div key={i} className="flex justify-end">
                  <p className="max-w-[85%] whitespace-pre-line break-words rounded-2xl rounded-br-sm bg-primary px-3.5 py-2 text-sm text-white">
                    {message.text}
                  </p>
                </div>
              ) : (
                <div key={i} className="max-w-[92%]">
                  <div className="rounded-2xl rounded-bl-sm border border-stroke bg-surface px-3.5 py-2.5">
                    <Parts parts={message.parts} />
                  </div>
                  {message.failed && !busy && i === messages.length - 1 && (
                    <button
                      type="button"
                      onClick={() => send(message.failed)}
                      className="mt-1.5 rounded px-1 py-1 text-xs font-medium text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    >
                      Try again
                    </button>
                  )}
                </div>
              ),
            )}

            {busy && (
              <div className="flex items-center gap-1.5 px-1" role="status">
                <span className="sr-only">The assistant is typing</span>
                {[0, 1, 2].map((dot) => (
                  <span
                    key={dot}
                    aria-hidden="true"
                    className="h-2 w-2 rounded-full bg-ink-muted/60 motion-safe:animate-bounce"
                    style={{ animationDelay: `${dot * 120}ms` }}
                  />
                ))}
              </div>
            )}
          </div>

          {chips.length > 0 && (
            <div className="flex flex-wrap gap-2 border-t border-stroke bg-canvas px-4 py-2">
              {chips.map((chip) => (
                <button
                  key={chip}
                  type="button"
                  onClick={() => send(chip)}
                  className="rounded-full border border-stroke bg-surface px-3 py-1.5 text-xs font-medium text-ink transition hover:border-primary hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                  {chip}
                </button>
              ))}
            </div>
          )}

          <form
            onSubmit={(e) => {
              e.preventDefault();
              send(text);
            }}
            className="border-t border-stroke bg-surface p-3"
          >
            <div className="flex items-center gap-2">
              <input
                ref={inputRef}
                value={text}
                onChange={(e) => setText(e.target.value)}
                maxLength={MAX_LENGTH}
                aria-label="Message the shopping assistant"
                placeholder={auth?.user ? 'Ask about products or your orders' : 'Ask about products'}
                autoComplete="off"
                enterKeyHint="send"
                className="min-w-0 flex-1 rounded-lg border-stroke bg-canvas px-3 py-2.5 text-base text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:py-2 sm:text-sm"
              />
              <button
                type="submit"
                disabled={busy || text.trim() === ''}
                aria-label="Send message"
                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary text-white transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-50 sm:h-10 sm:w-10"
              >
                <SendIcon className="h-5 w-5" />
              </button>
            </div>
            <p className="mt-2 text-center text-[11px] text-ink-muted">
              Automated assistant. Check details on the product page before you buy.
            </p>
          </form>
        </section>
      )}
    </>
  );
}
