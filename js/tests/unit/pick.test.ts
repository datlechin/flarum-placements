import PlacementState from '../../src/common/states/PlacementState';
import { recordSeen, resetFrequency } from '../../src/common/frequency';
import { resetSticky } from '../../src/common/sticky';
import { safeUrl } from '../../src/common/creatives';
import type { Candidate, SlotConfig } from '../../src/common/types';

function candidate(overrides: Partial<Candidate> = {}): Candidate {
  return {
    creative: 1,
    campaign: 1,
    tier: 50,
    weight: 10,
    type: 'image',
    payload: {},
    url: null,
    label: null,
    ...overrides,
  };
}

function slot(candidates: Candidate[], overrides: Partial<SlotConfig> = {}): SlotConfig {
  return {
    key: 'notice',
    group: 'global',
    label: 'x',
    description: 'x',
    allowedTypes: [],
    maxFill: 1,
    repeating: false,
    recommendedSize: null,
    reserve: {},
    // `next_tier`, which is the shipped default and the behaviour these tests
    // describe. `house` would stop the fallthrough they are about.
    fallback: 'next_tier',
    passbackCreativeId: null,
    labelMode: 'inherit',
    everyN: null,
    repeatLimit: null,
    sortOrder: 0,
    candidates,
    ...overrides,
  };
}

/** A `Math.random` that returns each of these in turn. */
function sequence(...values: number[]): () => number {
  let i = 0;

  return () => values[i++] ?? 0;
}

describe('drawing a creative', () => {
  const state = new PlacementState();

  describe('tiers', () => {
    it('draws only from the best tier that has anything', () => {
      // Mixing a sponsorship with a remnant filler in one weighted draw is
      // what makes "why didn't my paid campaign show?" unanswerable.
      const candidates = [candidate({ creative: 1, tier: 10 }), candidate({ creative: 2, tier: 10 }), candidate({ creative: 3, tier: 90 })];

      expect(state.topTier(candidates).map((c) => c.creative)).toEqual([1, 2]);
    });

    it('falls through to whatever tier is present', () => {
      const candidates = [candidate({ creative: 3, tier: 90 })];

      expect(state.topTier(candidates).map((c) => c.creative)).toEqual([3]);
    });

    it('has nothing to draw from when there are no candidates', () => {
      expect(state.topTier([])).toEqual([]);
      expect(state.pick(slot([]))).toEqual([]);
      expect(state.pick(slot([], { candidates: undefined }))).toEqual([]);
    });
  });

  describe('weighting', () => {
    const two = [candidate({ creative: 1, weight: 1 }), candidate({ creative: 2, weight: 9 })];

    it('lands on the first creative only in its share of the range', () => {
      // Total weight 10, so 0.0 to 0.1 is the first creative.
      expect(state.pick(slot(two), sequence(0)).map((c) => c.creative)).toEqual([1]);
      expect(state.pick(slot(two), sequence(0.09)).map((c) => c.creative)).toEqual([1]);
    });

    it('lands on the second for the rest of it', () => {
      expect(state.pick(slot(two), sequence(0.1)).map((c) => c.creative)).toEqual([2]);
      expect(state.pick(slot(two), sequence(0.99)).map((c) => c.creative)).toEqual([2]);
    });

    it('still returns something when the draw lands exactly at the top', () => {
      // Floating point means `point` can fail to go negative on the last
      // candidate; falling off the end and returning nothing would leave a
      // hole on the page for no reason a reader could see.
      expect(state.pick(slot(two), sequence(1)).map((c) => c.creative)).toEqual([2]);
    });

    it('gives an equal share to equal weights', () => {
      const even = [candidate({ creative: 1, weight: 5 }), candidate({ creative: 2, weight: 5 })];

      expect(state.pick(slot(even), sequence(0.49)).map((c) => c.creative)).toEqual([1]);
      expect(state.pick(slot(even), sequence(0.5)).map((c) => c.creative)).toEqual([2]);
    });

    it('treats a zero weight as one rather than as never', () => {
      const zero = [candidate({ creative: 1, weight: 0 })];

      expect(state.pick(slot(zero), sequence(0.5)).map((c) => c.creative)).toEqual([1]);
    });
  });

  describe('filling a slot that holds more than one', () => {
    const three = [candidate({ creative: 1, weight: 1 }), candidate({ creative: 2, weight: 1 }), candidate({ creative: 3, weight: 1 })];

    it('never draws the same creative twice', () => {
      const picked = state.pick(slot(three, { maxFill: 3 }), sequence(0, 0, 0));

      expect(picked.map((c) => c.creative).sort()).toEqual([1, 2, 3]);
    });

    it('stops at maxFill', () => {
      expect(state.pick(slot(three, { maxFill: 2 }), sequence(0, 0))).toHaveLength(2);
    });

    it('stops when the candidates run out, not when maxFill does', () => {
      expect(state.pick(slot(three, { maxFill: 10 }), sequence(0, 0, 0))).toHaveLength(3);
    });

    it('treats a maxFill below one as one', () => {
      expect(state.pick(slot(three, { maxFill: 0 }), sequence(0))).toHaveLength(1);
    });

    it('does not mutate the payload it drew from', () => {
      // The state is shared by every slot on the page, and drawing for the
      // sidebar must not empty the header.
      const config = slot(three, { maxFill: 3 });

      state.pick(config, sequence(0, 0, 0));

      expect(config.candidates).toHaveLength(3);
    });
  });
});

describe('creative destinations', () => {
  it('allows a link a browser will simply navigate to', () => {
    expect(safeUrl('https://example.com/offer')).toBe('https://example.com/offer');
    expect(safeUrl('HTTP://example.com')).toBe('HTTP://example.com');
  });

  it('refuses a destination that is really code execution', () => {
    // Checked here as well as on save, because this is the last point before
    // the value reaches an href.
    expect(safeUrl('javascript:alert(1)')).toBeNull();
    expect(safeUrl('data:text/html,<script>')).toBeNull();
    expect(safeUrl('//example.com')).toBeNull();
    expect(safeUrl('/somewhere')).toBeNull();
    expect(safeUrl('')).toBeNull();
    expect(safeUrl(null)).toBeNull();
  });
});

describe('frequency and tiers together', () => {
  const state = new PlacementState();

  beforeEach(() => resetFrequency());

  it('lets the next tier through when the best one is capped out', () => {
    // The order matters: filtering by cap *after* choosing the tier would take
    // the sponsorship tier, find it exhausted, and leave the slot empty —
    // rather than falling through to the campaign that is still eligible.
    const sponsorship = candidate({ creative: 1, campaign: 1, tier: 10, cap: 1, window: 'day' });
    const standard = candidate({ creative: 2, campaign: 2, tier: 50 });

    recordSeen(sponsorship);

    const picked = state.pick(slot([sponsorship, standard]), sequence(0));

    expect(picked.map((c) => c.creative)).toEqual([2]);
  });

  it('serves the best tier while it still has room', () => {
    const sponsorship = candidate({ creative: 1, campaign: 1, tier: 10, cap: 2, window: 'day' });
    const standard = candidate({ creative: 2, campaign: 2, tier: 50 });

    recordSeen(sponsorship);

    expect(state.pick(slot([sponsorship, standard]), sequence(0)).map((c) => c.creative)).toEqual([1]);
  });

  it('leaves the slot empty when everything is capped out', () => {
    const only = candidate({ creative: 1, campaign: 1, cap: 1, window: 'day' });

    recordSeen(only);

    expect(state.pick(slot([only]), sequence(0))).toEqual([]);
  });
});

describe('a slot that holds its choice', () => {
  const state = new PlacementState();

  beforeEach(() => {
    resetSticky();
    resetFrequency();
  });

  const two = [candidate({ creative: 1, weight: 1 }), candidate({ creative: 2, weight: 1 })];

  it('draws once and keeps it', () => {
    // A sponsor whose advert flickers between three others as a reader moves
    // through the forum looks like a forum with a fault.
    const sticky = slot(two, { rotation: 'sticky' });

    const first = state.pick(sticky, sequence(0));

    expect(first.map((c) => c.creative)).toEqual([1]);
    // A draw that would otherwise have landed on the second creative.
    expect(state.pick(sticky, sequence(0.9)).map((c) => c.creative)).toEqual([1]);
  });

  it('draws again on every page when it is not sticky', () => {
    const rotating = slot(two, { rotation: 'random' });

    expect(state.pick(rotating, sequence(0)).map((c) => c.creative)).toEqual([1]);
    expect(state.pick(rotating, sequence(0.9)).map((c) => c.creative)).toEqual([2]);
  });

  it('never resurrects something that has stopped being eligible', () => {
    // A campaign that has since ended, or one whose frequency cap has been
    // reached, must not come back just because it was drawn earlier — and the
    // slot must fall back to a real draw rather than to whatever happens to be
    // first in the list.
    const sticky = slot(two, { rotation: 'sticky' });

    state.pick(sticky, sequence(0));
    expect(state.pick(sticky, sequence(0.9)).map((c) => c.creative)).toEqual([1], 'held its choice');

    // Creative 1 is gone. A fresh weighted draw at 0.9 lands on the second of
    // the two that remain, which is deliberately not the first in the list.
    const remaining = slot([candidate({ creative: 3, weight: 1 }), candidate({ creative: 2, weight: 1 })], { rotation: 'sticky' });

    expect(state.pick(remaining, sequence(0.9)).map((c) => c.creative)).toEqual([2]);
  });

  it('keeps each slot separate', () => {
    const notice = slot(two, { key: 'notice', rotation: 'sticky' });
    const sidebar = slot(two, { key: 'index_sidebar', rotation: 'sticky' });

    state.pick(notice, sequence(0));

    expect(state.pick(sidebar, sequence(0.9)).map((c) => c.creative)).toEqual([2]);
    expect(state.pick(notice, sequence(0.9)).map((c) => c.creative)).toEqual([1]);
  });
});
