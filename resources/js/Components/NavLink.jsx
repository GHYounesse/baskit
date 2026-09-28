import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary ' +
                (active
                    ? 'bg-primary-light text-primary'
                    : 'text-ink-muted hover:bg-primary-light/60 hover:text-ink') +
                ' ' + className
            }
        >
            {children}
        </Link>
    );
}
