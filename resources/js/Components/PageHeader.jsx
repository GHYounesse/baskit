/**
 * The title block every page opens with: one h1 in the display face, an
 * optional line of context, and optional actions (a button, a status badge)
 * aligned to the right. Pages place it first inside their width container so
 * the title always lines up with the content below it.
 */
export default function PageHeader({ title, description, children }) {
    return (
        <div className="mb-6 flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
            <div className="min-w-0">
                <h1 className="font-display text-2xl font-bold text-ink">{title}</h1>
                {description && <p className="mt-1 text-sm text-ink-muted">{description}</p>}
            </div>
            {children && <div className="flex flex-wrap items-center gap-3">{children}</div>}
        </div>
    );
}
