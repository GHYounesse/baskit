import { Link } from '@inertiajs/react';

/**
 * Renders Laravel's paginator `links` array (Prev / page numbers / Next,
 * each already carrying the current query string via ->withQueryString()).
 */
export default function Pagination({ links }) {
    if (!links || links.length <= 3) return null;

    return (
        <nav aria-label="Pagination" className="mt-10 flex items-center justify-center gap-1">
            {links.map((link, i) =>
                link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        preserveScroll
                        aria-current={link.active ? 'page' : undefined}
                        className={`min-w-9 rounded-lg px-3 py-2 text-center text-sm font-medium transition ${
                            link.active
                                ? 'bg-primary text-white'
                                : 'text-ink-muted hover:bg-primary-light hover:text-ink'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={i}
                        className="min-w-9 rounded-lg px-3 py-2 text-center text-sm font-medium text-ink-muted/40"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
