import app from 'flarum/common/app';

import type { Candidate } from './types';

export type EventType = 'impression' | 'viewable' | 'click';

export interface ReportedEvent {
  type: EventType;
  token: string;
  nonce: string;
  issued: number;
  creative: number;
  campaign: number;
  placement: string;
  device: string;
}

/**
 * How long events are held before being sent.
 *
 * A page with three slots would otherwise make three requests in the first
 * second, and a reader moving quickly through a forum would make dozens. One
 * batch is cheaper for everybody and no less accurate.
 */
const BATCH_MS = 2000;

let queue: ReportedEvent[] = [];
let timer: ReturnType<typeof setTimeout> | null = null;
let listening = false;

/**
 * Fired once per event, ever.
 *
 * Mithril redraws on every event, every route change and every model update,
 * and flarum/realtime redraws an open discussion whenever anybody posts to it.
 * Without this, one advert would report an impression dozens of times a minute
 * on a busy thread — and the server would dutifully reject all but the first,
 * which is a lot of wasted requests to make a point.
 */
const reported = new Set<string>();

function endpoint(): string {
  return `${app.forum.attribute('apiUrl')}/placements/events`;
}

/**
 * Which class of screen this is.
 *
 * Read from the CSS variable core sets, so it agrees with the breakpoints the
 * stylesheet uses rather than with a second set of numbers that could drift
 * from them.
 */
export function device(): string {
  // Guarded because this can run before the app has finished booting, and
  // because the server treats an unrecognised value as unknown rather than
  // rejecting the event — misfiling a count between two columns is a much
  // smaller problem than losing it.
  const screen = typeof app?.screen === 'function' ? app.screen() : null;

  if (screen === 'phone') return 'phone';
  if (screen === 'tablet') return 'tablet';

  return screen === null ? '' : 'desktop';
}

/**
 * Report that something happened to a creative.
 *
 * Silently does nothing for a candidate with no token — a demo sample, or one
 * served before measurement was switched on. There is nothing to prove, so
 * there is nothing to count.
 */
export function report(type: EventType, candidate: Candidate, placement: string): void {
  if (!candidate.token || !candidate.nonce || candidate.issued == null) return;

  const key = `${type}:${candidate.nonce}`;

  if (reported.has(key)) return;

  reported.add(key);

  queue.push({
    type,
    token: candidate.token,
    nonce: candidate.nonce,
    issued: candidate.issued,
    creative: candidate.creative,
    campaign: candidate.campaign,
    placement,
    device: device(),
  });

  schedule();
}

function schedule(): void {
  if (!listening) {
    listening = true;

    // `pagehide` rather than `unload`: it is the event that still fires when a
    // page is put into the back/forward cache, which is where a reader
    // navigating away most often ends up.
    window.addEventListener('pagehide', flush);
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') flush();
    });
  }

  if (timer === null) {
    timer = setTimeout(flush, BATCH_MS);
  }
}

/**
 * Send whatever is queued.
 *
 * `sendBeacon` is used where it exists because it survives the page being
 * closed, which is exactly when the last impression of a visit is reported.
 * It cannot set headers, which is why the endpoint is exempt from CSRF and
 * relies on the signed token instead.
 */
export function flush(): void {
  if (timer !== null) {
    clearTimeout(timer);
    timer = null;
  }

  if (!queue.length) return;

  const body = JSON.stringify({ events: queue });

  queue = [];

  if (typeof navigator.sendBeacon === 'function') {
    navigator.sendBeacon(endpoint(), new Blob([body], { type: 'application/json' }));

    return;
  }

  fetch(endpoint(), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body,
    // So the request survives the page going away, which is the whole point.
    keepalive: true,
  }).catch(() => {
    // Nothing is listening for a reply, and a lost count is not worth an error
    // in the console of somebody reading a forum.
  });
}

/**
 * For tests.
 */
export function resetBeacon(): void {
  queue = [];
  reported.clear();

  if (timer !== null) {
    clearTimeout(timer);
    timer = null;
  }
}

export function pending(): ReportedEvent[] {
  return [...queue];
}
