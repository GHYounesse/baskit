import { usePage } from '@inertiajs/react';
import { useState } from 'react';

const STORAGE_KEY = 'baskit-demo-banner-hidden';

function wasHidden() {
    try {
        return sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * A thin notice on a demo site (DEMO_MODE=true): what to type at checkout and which accounts to
 * log in with. Hidden for the rest of the browser session once dismissed.
 */
export default function DemoBanner() {
    const { demo } = usePage().props;
    const [hidden, setHidden] = useState(wasHidden);
    const [showLogins, setShowLogins] = useState(false);
    const [copied, setCopied] = useState(false);

    if (!demo || hidden) return null;

    const dismiss = () => {
        setHidden(true);
        try {
            sessionStorage.setItem(STORAGE_KEY, '1');
        } catch {
            // Private mode: the banner just comes back on the next page load.
        }
    };

    const copyCard = async () => {
        try {
            await navigator.clipboard.writeText(demo.test_card);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // No clipboard access: the number is still on screen to type.
        }
    };

    const link = 'font-medium text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded';

    return (
        <div role="region" aria-label="Demo store" className="border-b border-stroke bg-primary-light text-sm text-ink">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2 sm:px-6 lg:px-8">
                <p className="min-w-0 flex-1">
                    This is a demo store and no real money moves. Pay with test card{' '}
                    <span className="font-semibold">{demo.test_card}</span>, any future date and any CVC.{' '}
                    <button type="button" onClick={copyCard} className={link}>
                        {copied ? 'Copied' : 'Copy card number'}
                    </button>
                </p>

                <button type="button" onClick={() => setShowLogins((open) => !open)} aria-expanded={showLogins} className={link}>
                    {showLogins ? 'Hide demo logins' : 'Demo logins'}
                </button>

                <button
                    type="button"
                    onClick={dismiss}
                    aria-label="Hide the demo notice"
                    className="rounded px-1 text-lg leading-none text-ink-muted hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                >
                    &times;
                </button>
            </div>

            {showLogins && (
                <dl className="mx-auto max-w-7xl space-y-1 px-4 pb-3 sm:px-6 lg:px-8">
                    {demo.accounts.map((account) => (
                        <div key={account.email} className="flex flex-wrap gap-x-2">
                            <dt className="font-medium">{account.role}:</dt>
                            <dd>{account.email}</dd>
                        </div>
                    ))}
                    <p className="text-ink-muted">
                        The password for both is <span className="font-semibold text-ink">{demo.password}</span>.
                    </p>
                </dl>
            )}
        </div>
    );
}
