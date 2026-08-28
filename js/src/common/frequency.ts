import type { Candidate } from './types';

/**
 * How often a reader has already seen each creative.
 *
 * Kept in the browser, because that is the only place it is knowable: on a
 * public forum most readers are guests, so a server-side counter would apply
 * to a minority of impressions and the majority would go uncapped anyway.
 *
 * This is best effort and the admin panel says so. A private window, cleared
 * site data, or a second device all start the count again. It stops a reader
 * seeing the same advert forty times in an afternoon, which is the complaint
 * people actually have; it is not an auditable guarantee to an advertiser, and
 * nothing in the reports pretends otherwise.
 */
const KEY = 'datlechin-placements.seen';

interface SeenEntry {
  /** When the window this count belongs to began, as an epoch second. */
  window: number;
  count: number;
}

type SeenMap = Record<string, SeenEntry>;

/**
 * `sessionStorage` for a session window, `localStorage` for the rest — a
 * session cap that survived closing the tab would not be a session cap.
 */
function store(window_: string): Storage | null {
  try {
    const storage = window_ === 'session' ? sessionStorage : localStorage;

    // Touching it is the only reliable test: Safari in private mode exposes
    // the object and throws on write.
    storage.getItem(KEY);

    return storage;
  } catch {
    // No storage means no capping rather than no advertising.
    return null;
  }
}

function read(storage: Storage): SeenMap {
  try {
    const raw = storage.getItem(KEY);
    const parsed = raw ? JSON.parse(raw) : null;

    return parsed && typeof parsed === 'object' ? (parsed as SeenMap) : {};
  } catch {
    return {};
  }
}

function write(storage: Storage, map: SeenMap): void {
  try {
    storage.setItem(KEY, JSON.stringify(map));
  } catch {
    // Full, or refused. A lost count is not worth an error in the console of
    // somebody reading a forum.
  }
}

/**
 * When the window a moment belongs to began.
 *
 * Fixed windows rather than rolling ones: "three times a day" is what an
 * administrator means, and a rolling window would need every timestamp kept
 * rather than one count.
 */
export function windowStart(window_: string, now: number = Date.now()): number {
  const seconds = Math.floor(now / 1000);

  if (window_ === 'hour') return seconds - (seconds % 3600);
  if (window_ === 'day') return seconds - (seconds % 86400);

  // A session has no clock: it begins when the storage does.
  return 0;
}

export function timesSeen(candidate: Candidate, now: number = Date.now()): number {
  const window_ = candidate.window ?? 'day';
  const storage = store(window_);

  if (!storage) return 0;

  const entry = read(storage)[String(candidate.campaign)];

  if (!entry) return 0;

  // A count from a window that has passed is not this window's count.
  return entry.window === windowStart(window_, now) ? entry.count : 0;
}

/**
 * Whether this creative may still be shown to this reader.
 *
 * Counted per campaign rather than per creative: an advertiser who supplied
 * four variations of one advert has still shown the reader their advert four
 * times.
 */
export function withinCap(candidate: Candidate, now: number = Date.now()): boolean {
  const cap = candidate.cap;

  if (!cap || cap < 1) return true;

  return timesSeen(candidate, now) < cap;
}

export function recordSeen(candidate: Candidate, now: number = Date.now()): void {
  if (!candidate.cap || candidate.cap < 1) return;

  const window_ = candidate.window ?? 'day';
  const storage = store(window_);

  if (!storage) return;

  const map = read(storage);
  const key = String(candidate.campaign);
  const start = windowStart(window_, now);
  const entry = map[key];

  map[key] = {
    window: start,
    count: entry && entry.window === start ? entry.count + 1 : 1,
  };

  // Counts from windows that have passed are dropped on write, so the map
  // cannot grow without bound on a forum that has run a hundred campaigns.
  Object.keys(map).forEach((other) => {
    if (map[other].window !== start && map[other].window !== 0) delete map[other];
  });

  write(storage, map);
}

/**
 * For tests.
 */
export function resetFrequency(): void {
  [sessionStorage, localStorage].forEach((storage) => {
    try {
      storage.removeItem(KEY);
    } catch {
      // Nothing to clear.
    }
  });
}
