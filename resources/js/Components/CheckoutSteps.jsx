const STEPS = ['Cart', 'Shipping and payment', 'Order placed'];

function CheckIcon(props) {
  return (
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" {...props}>
      <path d="M4.5 10.5l3.5 3.5 7.5-8" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

/**
 * Where the shopper is in the buying flow. `current` is 1 for the cart, 2 for checkout and 3 for
 * the confirmation. On a phone only the current step keeps its label.
 */
export default function CheckoutSteps({ current }) {
  return (
    <ol aria-label="Checkout progress" className="mb-6 flex items-center gap-2 text-sm">
      {STEPS.map((label, index) => {
        const number = index + 1;
        const done = number < current;
        const active = number === current;

        return (
          <li key={label} aria-current={active ? 'step' : undefined} className="flex items-center gap-2">
            <span
              className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                done || active ? 'bg-primary text-primary-on' : 'bg-stroke text-ink-muted'
              }`}
            >
              {done ? <CheckIcon className="h-3.5 w-3.5" aria-hidden="true" /> : number}
            </span>
            <span className={active ? 'font-medium text-ink' : 'hidden text-ink-muted sm:inline'}>{label}</span>
            {number < STEPS.length && <span className="mx-1 h-px w-6 bg-stroke sm:w-10" aria-hidden="true" />}
          </li>
        );
      })}
    </ol>
  );
}
