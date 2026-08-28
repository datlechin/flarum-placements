import type { Candidate } from './types';

/**
 * What a slot drew earlier in this visit.
 *
 * A sponsor whose advert flickers between three others as a reader moves
 * through the forum looks like a forum with a fault. Holding the choice for a
 * visit also makes the click-through rate of one creative mean something,
 * rather than being an average of whatever happened to be drawn each time.
 *
 * `sessionStorage`, so it lasts exactly as long as the visit does.
 */
const KEY = 'datlechin-placements.sticky';

type Choices = Record<string, number[]>;

function store(): Storage | null {
  try {
    sessionStorage.getItem(KEY);

    return sessionStorage;
  } catch {
    // No storage means the slot rotates, which is the ordinary behaviour
    // rather than a failure.
    return null;
  }
}

function read(): Choices {
  const storage = store();

  if (!storage) return {};

  try {
    const parsed = JSON.parse(storage.getItem(KEY) ?? 'null');

    return parsed && typeof parsed === 'object' ? (parsed as Choices) : {};
  } catch {
    return {};
  }
}

/**
 * The creatives this slot settled on, of those still eligible.
 *
 * Anything that has since become ineligible — a campaign that ended, a
 * frequency cap now reached — is dropped rather than resurrected, so a sticky
 * slot never shows something a fresh draw would not have.
 */
export function stickyChoice(placement: string, eligible: Candidate[]): Candidate[] {
  const ids = read()[placement];

  if (!Array.isArray(ids) || !ids.length) return [];

  return ids
    .map((id) => eligible.find((candidate) => candidate.creative === id))
    .filter((candidate): candidate is Candidate => candidate !== undefined);
}

export function remember(placement: string, chosen: Candidate[]): void {
  const storage = store();

  if (!storage || !chosen.length) return;

  const choices = read();
  choices[placement] = chosen.map((candidate) => candidate.creative);

  try {
    storage.setItem(KEY, JSON.stringify(choices));
  } catch {
    // Full, or refused. The slot rotates instead, which is not a failure.
  }
}

/**
 * For tests.
 */
export function resetSticky(): void {
  try {
    sessionStorage.removeItem(KEY);
  } catch {
    // Nothing to clear.
  }
}
