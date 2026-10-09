import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import FlashBanner from '@/Components/FlashBanner';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Shell for the admin area. It carries only admin destinations plus a way
 * back to the storefront; shopper links (cart, wishlist, orders) live in
 * ShopLayout so the two areas never show each other's navigation.
 */
export default function AuthenticatedLayout({ children }) {
    const user = usePage().props.auth.user;
    const [showingNav, setShowingNav] = useState(false);

    return (
        <div className="min-h-screen bg-canvas text-ink">
            <nav className="border-b border-stroke bg-surface">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-1">
                        <Link href="/" className="me-4 flex items-center">
                            <ApplicationLogo className="h-8 w-auto" />
                        </Link>
                        <div className="hidden items-center gap-1 sm:flex">
                            <NavLink href={route('admin.dashboard')} active={route().current('admin.dashboard')}>
                                Dashboard
                            </NavLink>
                            <NavLink href={route('admin.products.index')} active={route().current('admin.products.*')}>
                                Products
                            </NavLink>
                            <NavLink href={route('admin.orders.index')} active={route().current('admin.orders.*')}>
                                Orders
                            </NavLink>
                            <NavLink href={route('admin.analytics.index')} active={route().current('admin.analytics.*')}>
                                Analytics
                            </NavLink>
                            <NavLink href={route('admin.inventory.index')} active={route().current('admin.inventory.*')}>
                                Inventory
                            </NavLink>
                            <NavLink href={route('admin.coupons.index')} active={route().current('admin.coupons.*')}>
                                Coupons
                            </NavLink>
                            <NavLink href="/">Shop</NavLink>
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
                            className="inline-flex h-11 w-11 items-center justify-center rounded-lg text-ink-muted transition hover:bg-primary-light hover:text-ink"
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
                        <ResponsiveNavLink href={route('admin.dashboard')} active={route().current('admin.dashboard')}>
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('admin.products.index')} active={route().current('admin.products.*')}>
                            Products
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('admin.orders.index')} active={route().current('admin.orders.*')}>
                            Orders
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('admin.analytics.index')} active={route().current('admin.analytics.*')}>
                            Analytics
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('admin.inventory.index')} active={route().current('admin.inventory.*')}>
                            Inventory
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route('admin.coupons.index')} active={route().current('admin.coupons.*')}>
                            Coupons
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href="/">Shop</ResponsiveNavLink>
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

            <FlashBanner />
            <main>{children}</main>
        </div>
    );
}
