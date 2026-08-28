import type { Candidate } from './types';

/**
 * The MRC's definition of a viewable display impression: at least half the
 * pixels, for at least one continuous second.
 */
const MIN_RATIO = 0.5;

/**
 * Large units are held to a lower share of pixels, because half of a 970×250
 * is more of the screen than half of a banner.
 */
const LARGE_AREA = 242_500;
const LARGE_RATIO = 0.3;

const REQUIRED_MS = 1000;

/**
 * Watches a slot and reports when it has actually been seen.
 *
 * This is what turns an impression from a vanity number into something an
 * administrator can defend in a sponsorship conversation, and it is what
 * exposes a slot nobody ever scrolls to.
 *
 * Returns a function that stops watching. Call it on unmount: a discussion
 * page can create and destroy dozens of these as the reader scrolls.
 */
export function watchViewability(element: Element, candidate: Candidate, onViewable: () => void): () => void {
  if (typeof IntersectionObserver !== 'function') {
    // Without an observer there is no honest way to know, and guessing would
    // inflate the one number this whole mechanism exists to keep honest.
    return () => {};
  }

  const threshold = requiredRatio(candidate);
  let timer: ReturnType<typeof setTimeout> | null = null;
  let done = false;

  const stop = () => {
    if (timer !== null) {
      clearTimeout(timer);
      timer = null;
    }
  };

  const start = () => {
    if (done || timer !== null) return;

    timer = setTimeout(() => {
      done = true;
      stop();
      observer.disconnect();
      document.removeEventListener('visibilitychange', onHidden);
      onViewable();
    }, REQUIRED_MS);
  };

  const observer = new IntersectionObserver(
    (entries) => {
      const visible = entries.some((entry) => entry.isIntersecting && entry.intersectionRatio >= threshold);

      // Restarted rather than paused: the standard asks for one *continuous*
      // second, so scrolling an advert half out of view and back again does
      // not accumulate.
      visible && document.visibilityState === 'visible' ? start() : stop();
    },
    { threshold: [0, threshold, 1] }
  );

  /**
   * A backgrounded tab is not being looked at, whatever the geometry says.
   * Forgetting this is the classic mistake that reports 99% viewability.
   */
  const onHidden = () => {
    if (document.visibilityState !== 'visible') stop();
  };

  document.addEventListener('visibilitychange', onHidden);
  observer.observe(element);

  return () => {
    stop();
    observer.disconnect();
    document.removeEventListener('visibilitychange', onHidden);
  };
}

/**
 * The share of pixels this creative has to show to count as seen.
 */
export function requiredRatio(candidate: Candidate): number {
  const size = candidate.payload as { width?: unknown; height?: unknown };
  const width = typeof size.width === 'number' ? size.width : 0;
  const height = typeof size.height === 'number' ? size.height : 0;

  return width * height >= LARGE_AREA ? LARGE_RATIO : MIN_RATIO;
}
