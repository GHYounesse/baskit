import ApplicationLogo from '@/Components/ApplicationLogo';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link } from '@inertiajs/react';

/**
 * The basket mark's "speed lines" as a loose, repeating diagonal motif —
 * the one recurring decorative device across the brand panel, echoing the
 * logo itself rather than a generic gradient or blob.
 */
function SpeedLines() {
    const rows = 6;
    const cols = 5;

    return (
        <svg
            className="pointer-events-none absolute inset-0 h-full w-full"
            preserveAspectRatio="xMidYMid slice"
            viewBox="0 0 400 600"
            aria-hidden="true"
        >
            {Array.from({ length: rows }).map((_, row) =>
                Array.from({ length: cols }).map((_, col) => {
                    const x = col * 90 - (row % 2 === 0 ? 0 : 45) - 20;
                    const y = row * 105 + 40;
                    return (
                        <rect
                            key={`${row}-${col}`}
                            x={x}
                            y={y}
                            width="46"
                            height="10"
                            rx="5"
                            fill="currentColor"
                            opacity="0.07"
                            transform={`rotate(-14 ${x + 23} ${y + 5})`}
                        />
                    );
                }),
            )}
        </svg>
    );
}

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen bg-canvas">
            {/* Brand panel — hidden on small screens in favour of a compact top bar */}
            <div className="relative hidden w-[42%] max-w-md shrink-0 flex-col justify-between overflow-hidden bg-panel px-10 py-10 text-white lg:flex">
                <div className="text-primary">
                    <SpeedLines />
                </div>

                <Link href="/" className="relative">
                    <ApplicationLogo variant="mark" />
                </Link>

                <div className="relative">
                    <h1 className="font-display text-4xl font-bold leading-tight text-white">
                        Fresh picks,
                        <br />
                        straight to your door.
                    </h1>
                    <p className="mt-4 max-w-xs text-sm leading-relaxed text-white/70">
                        Baskit gets your everyday groceries packed and moving fast —
                        browse, add to your basket, and check out in minutes.
                    </p>
                </div>

                <p className="relative text-xs text-white/50">
                    &copy; {new Date().getFullYear()} Baskit
                </p>
            </div>

            {/* Form panel */}
            <div className="flex flex-1 flex-col">
                <div className="flex items-center justify-between px-6 py-6 sm:px-10 lg:justify-end">
                    <Link href="/" className="lg:hidden">
                        <ApplicationLogo className="h-8 w-auto" />
                    </Link>
                    <ThemeToggle />
                </div>

                <div className="flex flex-1 items-center justify-center px-6 pb-16">
                    <div className="w-full max-w-sm">{children}</div>
                </div>
            </div>
        </div>
    );
}
