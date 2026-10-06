import { formatDateTime } from '@/utils/date';

const DOT_STYLES = {
  placed: 'bg-primary',
  shipped: 'bg-blue-500',
  delivered: 'bg-primary',
  cancelled: 'bg-red-500',
  refunded: 'bg-red-500',
};

function Step({ title, detail, time, dot, pending = false, last = false }) {
  return (
    <li className={`relative pl-8 ${last ? '' : 'pb-6'}`}>
      {!last && <span className="absolute left-[7px] top-5 -bottom-1 w-px bg-stroke" aria-hidden="true" />}
      <span
        className={`absolute left-0 top-1 h-4 w-4 rounded-full ${
          pending ? 'border-2 border-dashed border-ink-muted/40 bg-surface' : dot
        }`}
        aria-hidden="true"
      />
      <div className={`text-sm font-medium ${pending ? 'text-ink-muted' : 'text-ink'}`}>
        {title}
        {pending && <span className="sr-only"> (not yet)</span>}
      </div>
      {time && <time className="block text-xs text-ink-muted">{formatDateTime(time)}</time>}
      {detail && <p className="mt-1 max-w-prose text-sm text-ink-muted">{detail}</p>}
    </li>
  );
}

/**
 * What has happened to an order, oldest first, followed by the steps that
 * are still to come (dashed). Takes the `timeline` prop from the server.
 */
export default function OrderTimeline({ timeline }) {
  const { events, upcoming } = timeline;
  const total = events.length + upcoming.length;

  return (
    <ol aria-label="Order timeline">
      {events.map((event, i) => (
        <Step
          key={event.id}
          title={event.title}
          detail={event.message}
          time={event.created_at}
          dot={DOT_STYLES[event.type] ?? 'bg-primary'}
          last={i === total - 1}
        />
      ))}
      {upcoming.map((title, i) => (
        <Step key={title} title={title} pending last={events.length + i === total - 1} />
      ))}
    </ol>
  );
}
