import { useLayoutEffect, useRef, useState } from 'react';

/**
 * Tracks an element's rendered width so an SVG chart can draw at 1:1 pixels
 * (text stays a readable size instead of scaling with a viewBox). Measured
 * before the first paint, so there is no flash at a wrong size.
 */
export default function useElementWidth(initial = 320) {
  const ref = useRef(null);
  const [width, setWidth] = useState(initial);

  useLayoutEffect(() => {
    const element = ref.current;
    if (!element) return undefined;

    const measure = () => setWidth(Math.round(element.getBoundingClientRect().width) || initial);
    measure();

    if (typeof ResizeObserver === 'undefined') return undefined;

    const observer = new ResizeObserver(measure);
    observer.observe(element);

    return () => observer.disconnect();
  }, [initial]);

  return [ref, width];
}
