/**
 * Renders the Baskit logo, swapping between light/dark artwork based on the
 * `.dark` class on <html> (see hooks/useTheme). No JS theme check needed —
 * both images render and Tailwind's `dark:` variant toggles visibility.
 *
 * variant="full" (default): the basket mark + "Baskit" wordmark.
 * variant="mark": the basket mark only, for tight spaces (e.g. a favicon-ish spot).
 */
export default function ApplicationLogo({ variant = 'full', className = '', alt = 'Baskit' }) {
    const sources = variant === 'mark'
        ? {
            light: '/assets/images/logo-no-text-light mode.png',
            dark: '/assets/images/logo-no-text-dark mode.png',
        }
        : {
            light: '/assets/images/main-logo-light mode.png',
            dark: '/assets/images/main-logo-light dark.png',
        };

    return (
        <>
            <img src={sources.light} alt={alt} className={`block dark:hidden ${className}`} />
            <img src={sources.dark} alt={alt} className={`hidden dark:block ${className}`} />
        </>
    );
}
