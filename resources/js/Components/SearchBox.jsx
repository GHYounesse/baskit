import { formatMoney } from '@/utils/money';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react';

const DEBOUNCE_MS = 180;
const MIN_LENGTH = 2;
const RECENT_KEY = 'baskit:recent-searches';
const RECENT_MAX = 5;

function SearchIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.75" aria-hidden="true" {...props}>
      <circle cx="9" cy="9" r="6" />
      <path d="M17 17l-4-4" strokeLinecap="round" />
    </svg>
  );
}

function ClockIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true" {...props}>
      <circle cx="10" cy="10" r="7" />
      <path d="M10 6v4l2.500 1.500" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function TagIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true" {...props}>
      <path d="M3 12V4a1 1 0 011-1h8l9 9-9 9-9-9z" strokeLinejoin="round" />
      <circle cx="8" cy="8" r="1.25" />
    </svg>
  );
}

function BasketIcon(props) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" aria-hidden="true" {...props}>
      <path d="M4 9h16l-1.5 10.5a2 2 0 01-2 1.5H7.5a2 2 0 01-2-1.5L4 9z" strokeLinejoin="round" />
      <path d="M8 9V7a4 4 0 018 0v2" strokeLinecap="round" />
    </svg>
  );
}

// Accents are dropped (precomposed letters become one plain letter, so string
// positions still line up) to match how the server searches.
const fold = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '');

/**
 * Bold the start of every word that matches what was typed, mirroring the
 * server's "words, matched from their start" search. Built from text nodes,
 * so product names are never read as markup.
 */
function Highlight({ text, query }) {
  const terms = (fold(query).toLowerCase().match(/[a-z0-9]+/g) ?? []).map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

  if (terms.length === 0) return text;

  const pattern = new RegExp(`\\b(?:${terms.join('|')})`, 'gi');
  const folded = fold(text);
  const parts = [];
  let last = 0;

  for (const match of folded.matchAll(pattern)) {
    if (match.index > last) parts.push(text.slice(last, match.index));
    parts.push(
      <mark key={match.index} className="bg-transparent font-semibold text-ink">
        {text.slice(match.index, match.index + match[0].length)}
      </mark>,
    );
    last = match.index + match[0].length;
  }

  parts.push(text.slice(last));

  return parts;
}

function loadRecent() {
  try {
    const stored = JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]');

    return Array.isArray(stored) ? stored.filter((s) => typeof s === 'string').slice(0, RECENT_MAX) : [];
  } catch {
    return [];
  }
}

function storeRecent(list) {
  try {
    localStorage.setItem(RECENT_KEY, JSON.stringify(list));
  } catch {
    // Private mode or storage blocked: recent searches just won't persist.
  }
}

/**
 * Search field with live suggestions: matching products and categories as
 * you type, recent searches when it's empty, a "did you mean" fix when
 * nothing matches. Follows the ARIA combobox pattern, so it works with a
 * keyboard (arrows, Enter, Escape) and screen readers.
 */
export default function SearchBox({ initialValue = '', onSearch, onPickCategory }) {
  const listId = useId();
  const wrapperRef = useRef(null);
  const inputRef = useRef(null);
  const abortRef = useRef(null);
  const cacheRef = useRef(new Map());

  const [value, setValue] = useState(initialValue);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const [status, setStatus] = useState('idle'); // idle | loading | done | error
  const [data, setData] = useState(null);
  const [recent, setRecent] = useState([]);

  const query = value.trim();
  const searching = query.length >= MIN_LENGTH;

  // Keep the field in step when the page's search changes underneath it (back button, chips).
  useEffect(() => setValue(initialValue), [initialValue]);
  useEffect(() => setRecent(loadRecent()), []);

  // Close when clicking or tapping outside.
  useEffect(() => {
    const close = (event) => {
      if (!wrapperRef.current?.contains(event.target)) setOpen(false);
    };
    document.addEventListener('pointerdown', close);

    return () => document.removeEventListener('pointerdown', close);
  }, []);

  // Fetch suggestions after a short pause in typing. A new keystroke cancels
  // the request still in flight, so a slow answer can never overwrite a newer one.
  useEffect(() => {
    abortRef.current?.abort();

    if (!searching) {
      setData(null);
      setStatus('idle');
      return undefined;
    }

    const key = query.toLowerCase();
    const cached = cacheRef.current.get(key);

    if (cached) {
      setData(cached);
      setStatus('done');
      return undefined;
    }

    setStatus('loading');
    const controller = new AbortController();
    abortRef.current = controller;

    const timer = setTimeout(() => {
      window.axios
        .get('/search/suggest', { params: { q: query }, signal: controller.signal })
        .then((response) => {
          cacheRef.current.set(key, response.data);
          setData(response.data);
          setStatus('done');
        })
        .catch((error) => {
          if (error.code !== 'ERR_CANCELED') setStatus('error');
        });
    }, DEBOUNCE_MS);

    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [query, searching]);

  // One flat list of everything selectable, in the order it is drawn.
  const options = useMemo(() => {
    if (!searching) return recent.map((term) => ({ type: 'recent', term }));
    if (!data || data.query.trim() !== query) return [];

    const list = [
      ...data.products.map((product) => ({ type: 'product', product })),
      ...data.categories.map((category) => ({ type: 'category', category })),
    ];

    if (data.products.length > 0) list.push({ type: 'all', total: data.total });
    if (data.correction) list.push({ type: 'correction', term: data.correction });

    return list;
  }, [searching, recent, data, query]);

  useEffect(() => setActive(-1), [options]);

  const remember = useCallback((term) => {
    const clean = term.trim();
    if (!clean) return;

    setRecent((current) => {
      const next = [clean, ...current.filter((t) => t.toLowerCase() !== clean.toLowerCase())].slice(0, RECENT_MAX);
      storeRecent(next);

      return next;
    });
  }, []);

  const submit = (term) => {
    const clean = term.trim();
    remember(clean);
    setValue(clean);
    setOpen(false);
    onSearch(clean);
  };

  const choose = (option) => {
    if (option.type === 'product') {
      remember(query);
      setOpen(false);
      router.visit(`/products/${option.product.slug}`);
    } else if (option.type === 'category') {
      setOpen(false);
      onPickCategory(option.category.slug);
    } else if (option.type === 'all') {
      submit(query);
    } else {
      submit(option.term);
    }
  };

  const onKeyDown = (event) => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (!open) return setOpen(true);
      if (options.length === 0) return undefined;

      const step = event.key === 'ArrowDown' ? 1 : -1;
      const count = options.length;

      // From "nothing highlighted", Down goes to the first row and Up to the last; both wrap.
      return setActive((current) => (current === -1 ? (step === 1 ? 0 : count - 1) : (current + step + count) % count));
    }

    if (event.key === 'Enter') {
      event.preventDefault();

      return open && active >= 0 && options[active] ? choose(options[active]) : submit(value);
    }

    if (event.key === 'Escape') return setOpen(false);

    return undefined;
  };

  const showPanel = open && (searching ? status !== 'idle' : recent.length > 0);
  const optionId = (index) => `${listId}-option-${index}`;

  let announcement = '';
  if (open && searching && status === 'done') {
    if (data.products.length > 0) {
      announcement = `${data.total} matching ${data.total === 1 ? 'product' : 'products'}`;
    } else {
      announcement = data.correction ? `No matching products. Did you mean ${data.correction}?` : 'No matching products';
    }
  }

  return (
    <div ref={wrapperRef} className="relative flex-1">
      <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" />

      <input
        ref={inputRef}
        type="text"
        role="combobox"
        value={value}
        onChange={(e) => {
          setValue(e.target.value);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        onClick={() => setOpen(true)}
        onKeyDown={onKeyDown}
        aria-label="Search products"
        aria-expanded={showPanel}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={showPanel && active >= 0 ? optionId(active) : undefined}
        autoComplete="off"
        autoCorrect="off"
        spellCheck="false"
        enterKeyHint="search"
        placeholder="Search products..."
        className="w-full rounded-lg border-stroke bg-surface py-2.5 pl-9 pr-10 text-base text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:py-2 sm:text-sm"
      />

      {value && (
        <button
          type="button"
          aria-label="Clear search"
          onClick={() => {
            setValue('');
            setOpen(false);
            inputRef.current?.focus();
            if (initialValue) onSearch('');
          }}
          className="absolute right-1 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-md text-ink-muted transition hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
        >
          <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.75" className="h-4 w-4" aria-hidden="true">
            <path d="M5 5l10 10M15 5L5 15" strokeLinecap="round" />
          </svg>
        </button>
      )}

      <div role="status" aria-live="polite" className="sr-only">
        {announcement}
      </div>

      {showPanel && (
        <div className="absolute z-20 mt-1.5 max-h-[min(70vh,28rem)] w-full overflow-auto rounded-lg border border-stroke bg-surface shadow-lg">
          {!searching && (
            <div className="flex items-center justify-between px-3 pb-1 pt-2.5 text-xs font-medium text-ink-muted">
              Recent searches
              <button
                type="button"
                onClick={() => {
                  setRecent([]);
                  storeRecent([]);
                }}
                className="rounded px-1 py-1 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
              >
                Clear
              </button>
            </div>
          )}

          {searching && status === 'loading' && options.length === 0 && (
            <p className="px-3 py-3 text-sm text-ink-muted">Searching…</p>
          )}

          {searching && status === 'error' && (
            <p className="px-3 py-3 text-sm text-ink-muted">Suggestions aren't available right now. Press Enter to search.</p>
          )}

          {searching && status === 'done' && data && options.length === 0 && (
            <p className="px-3 py-3 text-sm text-ink-muted">No products match “{query}”.</p>
          )}

          {searching && status === 'done' && data && data.products.length === 0 && options.length > 0 && (
            <p className="px-3 pt-3 text-sm text-ink-muted">No products match “{query}”.</p>
          )}

          <ul id={listId} role="listbox" aria-label="Suggestions" className="py-1">
            {options.map((option, index) => (
              <li
                key={`${option.type}-${option.product?.id ?? option.category?.slug ?? option.term ?? 'all'}`}
                id={optionId(index)}
                role="option"
                aria-selected={index === active}
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => choose(option)}
                onMouseEnter={() => setActive(index)}
                className={`flex cursor-pointer items-center gap-3 px-3 py-2 ${index === active ? 'bg-primary-light' : ''}`}
              >
                {option.type === 'product' && (
                  <>
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-canvas text-ink-muted/50">
                      {option.product.image_url ? (
                        <img src={option.product.image_url} alt="" className="h-full w-full object-cover" />
                      ) : (
                        <BasketIcon className="h-5 w-5" />
                      )}
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm text-ink-muted">
                        <Highlight text={option.product.name} query={query} />
                      </span>
                      <span className="block truncate text-xs text-ink-muted">
                        {option.product.category ? `${option.product.category} · ` : ''}
                        {formatMoney(option.product.price_cents)}
                      </span>
                    </span>
                  </>
                )}

                {option.type === 'category' && (
                  <>
                    <TagIcon className="h-5 w-5 shrink-0 text-ink-muted" />
                    <span className="min-w-0 flex-1 truncate text-sm text-ink-muted">
                      Browse <span className="font-medium text-ink">{option.category.name}</span>
                    </span>
                  </>
                )}

                {option.type === 'all' && (
                  <>
                    <SearchIcon className="h-5 w-5 shrink-0 text-ink-muted" />
                    <span className="min-w-0 flex-1 truncate text-sm text-ink">
                      See all {option.total} {option.total === 1 ? 'result' : 'results'} for “{query}”
                    </span>
                  </>
                )}

                {option.type === 'correction' && (
                  <>
                    <SearchIcon className="h-5 w-5 shrink-0 text-ink-muted" />
                    <span className="min-w-0 flex-1 truncate text-sm text-ink-muted">
                      Did you mean <span className="font-medium text-ink">{option.term}</span>?
                    </span>
                  </>
                )}

                {option.type === 'recent' && (
                  <>
                    <ClockIcon className="h-5 w-5 shrink-0 text-ink-muted" />
                    <span className="min-w-0 flex-1 truncate text-sm text-ink">{option.term}</span>
                  </>
                )}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
