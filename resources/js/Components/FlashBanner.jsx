import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Shows the latest flash success message or the first validation error,
 * then fades itself out. Shared by ShopLayout and AuthenticatedLayout so a
 * message never silently drops just because of which layout rendered it.
 */
export default function FlashBanner() {
    const { flash, errors } = usePage().props;
    const [visible, setVisible] = useState(false);
    const errorMessage = Object.values(errors ?? {})[0];
    const message = flash?.success ?? errorMessage;

    useEffect(() => {
        if (!message) return;
        setVisible(true);
        const timer = setTimeout(() => setVisible(false), 4000);
        return () => clearTimeout(timer);
    }, [message, flash]);

    if (!message || !visible) return null;

    return (
        <div
            role="status"
            className={`mx-auto mt-4 max-w-6xl rounded-lg px-4 py-3 text-sm ${
                flash?.success
                    ? 'bg-primary-light text-primary'
                    : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300'
            }`}
        >
            {message}
        </div>
    );
}
