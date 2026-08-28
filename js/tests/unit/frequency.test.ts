import { recordSeen, resetFrequency, timesSeen, windowStart, withinCap } from '../../src/common/frequency';
import type { Candidate } from '../../src/common/types';

function candidate(overrides: Partial<Candidate> = {}): Candidate {
  return {
    creative: 7,
    campaign: 3,
    tier: 50,
    weight: 10,
    type: 'image',
    payload: {},
    url: null,
    label: null,
    cap: 2,
    window: 'day',
    ...overrides,
  };
}

const NOON = Date.parse('2026-08-28T12:00:00Z');
const LATER = Date.parse('2026-08-28T18:00:00Z');
const TOMORROW = Date.parse('2026-08-29T09:00:00Z');

describe('frequency capping', () => {
  beforeEach(() => resetFrequency());

  it('counts nothing before anything is seen', () => {
    expect(timesSeen(candidate(), NOON)).toBe(0);
    expect(withinCap(candidate(), NOON)).toBe(true);
  });

  it('counts each view and stops at the cap', () => {
    const one = candidate({ cap: 2 });

    recordSeen(one, NOON);
    expect(withinCap(one, NOON)).toBe(true);

    recordSeen(one, NOON);
    expect(timesSeen(one, NOON)).toBe(2);
    expect(withinCap(one, NOON)).toBe(false);
  });

  it('counts per campaign, not per creative', () => {
    // An advertiser who supplied four variations of one advert has still shown
    // the reader their advert four times.
    recordSeen(candidate({ creative: 1, cap: 2 }), NOON);
    recordSeen(candidate({ creative: 2, cap: 2 }), NOON);

    expect(withinCap(candidate({ creative: 3, cap: 2 }), NOON)).toBe(false);
  });

  it('keeps campaigns apart', () => {
    recordSeen(candidate({ campaign: 3, cap: 1 }), NOON);

    expect(withinCap(candidate({ campaign: 3, cap: 1 }), NOON)).toBe(false);
    expect(withinCap(candidate({ campaign: 4, cap: 1 }), NOON)).toBe(true);
  });

  it('carries a count across the same day', () => {
    recordSeen(candidate({ cap: 2 }), NOON);

    expect(timesSeen(candidate(), LATER)).toBe(1);
  });

  it('starts again in the next window', () => {
    recordSeen(candidate({ cap: 1 }), NOON);
    recordSeen(candidate({ cap: 1 }), NOON);

    expect(withinCap(candidate({ cap: 1 }), NOON)).toBe(false);
    expect(withinCap(candidate({ cap: 1 }), TOMORROW)).toBe(true);
  });

  it('uses hourly windows when asked to', () => {
    const hourly = candidate({ cap: 1, window: 'hour' });

    recordSeen(hourly, NOON);

    expect(withinCap(hourly, NOON)).toBe(false);
    expect(withinCap(hourly, NOON + 3600 * 1000)).toBe(true);
  });

  it('does not count a campaign with no cap', () => {
    // Nothing to enforce, so nothing is written — a forum with no frequency
    // caps writes nothing to a reader's browser at all.
    const uncapped = candidate({ cap: null });

    recordSeen(uncapped, NOON);

    expect(timesSeen(uncapped, NOON)).toBe(0);
    expect(withinCap(uncapped, NOON)).toBe(true);
  });

  it('treats a cap below one as no cap', () => {
    expect(withinCap(candidate({ cap: 0 }), NOON)).toBe(true);
  });

  it('forgets windows that have passed rather than growing forever', () => {
    recordSeen(candidate({ campaign: 1, cap: 1 }), NOON);
    recordSeen(candidate({ campaign: 2, cap: 1 }), TOMORROW);

    // Yesterday's count is gone, so a forum that has run a hundred campaigns
    // does not accumulate a hundred entries.
    expect(timesSeen(candidate({ campaign: 1 }), TOMORROW)).toBe(0);
    expect(timesSeen(candidate({ campaign: 2 }), TOMORROW)).toBe(1);
  });
});

describe('windows', () => {
  it('are fixed rather than rolling', () => {
    // "Three times a day" is what an administrator means, and a rolling window
    // would need every timestamp kept rather than one count.
    expect(windowStart('day', NOON)).toBe(windowStart('day', LATER));
    expect(windowStart('day', NOON)).not.toBe(windowStart('day', TOMORROW));
  });

  it('divide the hour cleanly', () => {
    expect(windowStart('hour', NOON)).toBe(Math.floor(NOON / 1000));
    expect(windowStart('hour', NOON + 59 * 60 * 1000)).toBe(Math.floor(NOON / 1000));
    expect(windowStart('hour', NOON + 60 * 60 * 1000)).not.toBe(Math.floor(NOON / 1000));
  });

  it('give a session no clock at all', () => {
    // It begins when the storage does, and ends when the tab does.
    expect(windowStart('session', NOON)).toBe(0);
    expect(windowStart('session', TOMORROW)).toBe(0);
  });
});
