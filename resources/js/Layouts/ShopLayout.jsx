import ApplicationLogo from '@/Components/ApplicationLogo';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function FlashBanner() {
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
            className={`mx-auto mt-4 max-w-6xl rounded-md px-4 py-3 text-sm ${
                flash?.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`}
        >
            {message}
        </div>
    );
}

export default function ShopLayout({ children }) {
    const { auth, cartCount } = usePage().props;
    const user = auth.user;

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="border-b border-gray-100 bg-white">
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-6">
                        <Link href="/">
                            <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800" />
                        </Link>
                        <Link href="/" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                            Shop
                        </Link>
                        <Link href="/cart" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                            Cart {cartCount > 0 && `(${cartCount})`}
                        </Link>
                        {user && (
                            <Link href="/orders" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                                My Orders
                            </Link>
                        )}
                        {user?.is_admin && (
                            <Link href="/admin/products" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                                Admin
                            </Link>
                        )}
                    </div>

                    <div className="flex items-center gap-4 text-sm">
                        <ThemeToggle />
                        {user ? (
                            <>
                                <Link href={route('profile.edit')} className="text-gray-700 hover:text-gray-900">
                                    {user.name}
                                </Link>
                                <Link href={route('logout')} method="post" as="button" className="text-gray-500 hover:text-gray-700">
                                    Log Out
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className="text-gray-700 hover:text-gray-900">
                                    Log in
                                </Link>
                                <Link href={route('register')} className="text-gray-700 hover:text-gray-900">
                                    Register
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </nav>

            <FlashBanner />
            <main>{children}</main>
        </div>
    );
}
