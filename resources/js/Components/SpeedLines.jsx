/**
 * The basket mark's "speed lines" as a loose, repeating diagonal motif —
 * the one recurring decorative device on the brand's deep-green surfaces
 * (the auth panel, the home page), echoing the logo itself rather than a
 * generic gradient or blob. Colour comes from `currentColor`.
 */
export default function SpeedLines() {
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
