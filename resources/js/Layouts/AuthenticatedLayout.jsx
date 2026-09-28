import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashBanner from '@/Components/FlashBanner';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

function CartLink({ count, ...props }) {
    return (
        <NavLink href="/cart" {...props}>
            Cart
            {count > 0 && (
                <span className="ms-1.5 rounded-full bg-primary px-1.5 py-0.5 text-xs font-semibold text-white">
                    {count}
                </span>
            )}
        </NavLink>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const { cartCount } = usePage().props;
    const [showingNav, setShowingNav] = useState(false);

    return (
        // The nav/header below are fully dark-mode aware. The content area
        // intentionally stays on a fixed light background: pages rendered inside
        // <main> (admin tables, profile, dashboard stat cards) still use
        // hardcoded white cards and unset text colors, so letting this
        // background go dark would make that text illegible until those pages
        // get their own token pass.
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-stroke bg-surface">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-1">
                        <Link href="/" className="me-4 flex items-center">
                            <ApplicationLogo className="h-8 w-auto" />
                        </Link>
                        <div className="hidden items-center gap-1 sm:flex">
                            {/* <NavLink href={route('dashboard')} active={route().current('dashboard')}>
                                Dashboard
                            </NavLink> */}
                            {user.is_admin && (
                                <NavLink href="/admin" active={route().current('admin.*')}>
                                    Dashboard
                                </NavLink>
                            )}
                            <NavLink href="/" active={route().current('shop.index')}>
                                Shop
                            </NavLink>
                            <CartLink count={cartCount} active={route().current('cart.index')} />
                            <NavLink href="/orders" active={route().current('orders.*')}>
                                My Orders
                            </NavLink>
                            
                        </div>
                    </div>

                    <div className="hidden items-center gap-2 sm:flex">
                        <ThemeToggle />
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-ink-muted transition hover:bg-primary-light hover:text-ink"
                                >
                                    {user.name}
                                    <svg className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
                                    </svg>
                                </button>
                            </Dropdown.Trigger>
                            <Dropdown.Content>
                                <Dropdown.Link href={route('profile.edit')}>Profile</Dropdown.Link>
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    Log out
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>

                    <div className="flex items-center gap-1 sm:hidden">
                        <ThemeToggle />
                        <button
                            onClick={() => setShowingNav((prev) => !prev)}
                            className="inline-flex items-center justify-center rounded-lg p-2 text-ink-muted transition hover:bg-primary-light hover:text-ink"
                            aria-label="Toggle navigation menu"
                        >
                            <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path
                                    className={!showingNav ? 'inline-flex' : 'hidden'}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    className={showingNav ? 'inline-flex' : 'hidden'}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <div className={(showingNav ? 'block' : 'hidden') + ' border-t border-stroke sm:hidden'}>
                    <div className="space-y-1 py-2">
                        <ResponsiveNavLink href={route('dashboard')} active={route().current('dashboard')}>
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href="/" active={route().current('shop.index')}>
                            Shop
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href="/cart" active={route().current('cart.index')}>
                            Cart {cartCount > 0 && `(${cartCount})`}
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href="/orders" active={route().current('orders.*')}>
                            My Orders
                        </ResponsiveNavLink>
                        {user.is_admin && (
                            <ResponsiveNavLink href="/admin" active={route().current('admin.*')}>
                                Admin
                            </ResponsiveNavLink>
                        )}
                    </div>
                    <div className="border-t border-stroke py-2">
                        <div className="px-3 py-2 text-sm font-medium text-ink-muted">{user.name}</div>
                        <ResponsiveNavLink href={route('profile.edit')}>Profile</ResponsiveNavLink>
                        <ResponsiveNavLink href={route('logout')} method="post" as="button">
                            Log out
                        </ResponsiveNavLink>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="border-b border-stroke bg-surface">
                    <div className="mx-auto max-w-7xl px-4 py-6 text-ink sm:px-6 lg:px-8">{header}</div>
                </header>
            )}

            <FlashBanner />
            <main>{children}</main>
        </div>
    );
}
