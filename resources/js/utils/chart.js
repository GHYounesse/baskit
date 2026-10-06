/**
 * Round a maximum up to a clean axis: 0 / 250 / 500 / 750 / 1000.
 * Returns the new max and the tick values (including 0).
 */
export function niceScale(rawMax, { ticks = 4, integer = false } = {}) {
  if (!(rawMax > 0)) {
    return { max: ticks, ticks: Array.from({ length: ticks + 1 }, (_, i) => i) };
  }

  const roughStep = rawMax / ticks;
  const magnitude = 10 ** Math.floor(Math.log10(roughStep));
  const normalized = roughStep / magnitude;
  const factor = [1, 2, 2.5, 5, 10].find((f) => normalized <= f) ?? 10;

  let step = factor * magnitude;
  if (integer) step = Math.max(1, Math.ceil(step));

  const count = Math.ceil(rawMax / step);

  return { max: step * count, ticks: Array.from({ length: count + 1 }, (_, i) => i * step) };
}

/**
 * Indexes of up to `count` evenly spaced items, always including the first and last.
 */
export function evenIndexes(length, count) {
  if (length <= count) return Array.from({ length }, (_, i) => i);

  return Array.from({ length: count }, (_, i) => Math.round((i * (length - 1)) / (count - 1)));
}
