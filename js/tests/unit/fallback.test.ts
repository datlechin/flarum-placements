import PlacementState from '../../src/common/states/PlacementState';
import { recordSeen, resetFrequency } from '../../src/common/frequency';
import type { Candidate, PlacementPayload, SlotConfig } from '../../src/common/types';

/**
 * What a slot does when nothing matched.
 *
 * Every mode was writable through the API and none of them did anything: the
 * client took the best tier and never read the setting, so all four behaved as
 * `next_tier`. These tests are the difference between them.
 */
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
    token: 'signed',
    nonce: 'abc',
    issued: 1_800_000_000,
    cap: null,
    window: 'day',
    house: false,
    passback: false,
    ...overrides,
  };
}

function state(fallback: SlotConfig['fallback'], candidates: Candidate[]): PlacementState {
  const slot: SlotConfig = {
    key: 'notice',
    group: 'global',
    label: 'x.label',
    description: 'x.description',
    allowedTypes: [],
    maxFill: 1,
    repeating: false,
    recommendedSize: null,
    reserve: {},
    fallback,
    passbackCreativeId: null,
    labelMode: 'inherit',
    everyN: null,
    repeatLimit: null,
    sortOrder: 0,
  };

  const payload: PlacementPayload = { demo: false, slots: { notice: { ...slot, candidates } as any } } as any;

  return new PlacementState(payload);
}

const pick = (s: PlacementState) => s.pick(s.slot('notice')!, () => 0);

beforeEach(() => resetFrequency());

/** Spend a campaign's frequency cap so it stops being eligible. */
function exhaust(c: Candidate) {
  recordSeen(c);
}

describe('next_tier', () => {
  it('drops to the next tier when the best one is spent', () => {
    const top = candidate({ creative: 1, campaign: 1, tier: 10, cap: 1 });
    const next = candidate({ creative: 2, campaign: 2, tier: 50 });

    exhaust(top);

    expect(pick(state('next_tier', [top, next])).map((c) => c.creative)).toEqual([2]);
  });

  it('is what a slot does by default', () => {
    const top = candidate({ creative: 1, tier: 10 });
    const next = candidate({ creative: 2, campaign: 2, tier: 50 });

    expect(pick(state('next_tier', [top, next])).map((c) => c.creative)).toEqual([1]);
  });
});

describe('collapse', () => {
  /**
   * A sponsorship position that quietly fills with remnant is worth less than
   * an empty one, and the sponsor notices.
   */
  it('shows nothing rather than falling to a lower tier', () => {
    const top = candidate({ creative: 1, campaign: 1, tier: 10, cap: 1 });
    const next = candidate({ creative: 2, campaign: 2, tier: 50 });

    exhaust(top);

    expect(pick(state('collapse', [top, next]))).toEqual([]);
  });

  it('still serves its own tier while that tier has something', () => {
    const top = candidate({ creative: 1, tier: 10 });
    const next = candidate({ creative: 2, campaign: 2, tier: 50 });

    expect(pick(state('collapse', [top, next])).map((c) => c.creative)).toEqual([1]);
  });
});

describe('house', () => {
  /**
   * The distinction that makes this a separate mode at all: `next_tier` would
   * reach the house campaigns eventually anyway, so if `house` meant the same
   * thing it would be one option and a bug.
   */
  it('skips the paid tiers in between and goes straight to house', () => {
    const top = candidate({ creative: 1, campaign: 1, tier: 10, cap: 1 });
    const paid = candidate({ creative: 2, campaign: 2, tier: 50 });
    const house = candidate({ creative: 3, campaign: 3, tier: 90, house: true });

    expect(pick(state('house', [top, paid, house])).map((c) => c.creative)).toEqual([1]);

    exhaust(top);

    // Not 2. The slot was sold to its top tier, and the forum would rather run
    // its own notice than hand a premium position to remnant.
    expect(pick(state('house', [top, paid, house])).map((c) => c.creative)).toEqual([3]);
  });

  it('serves its top tier while that tier has something', () => {
    const top = candidate({ creative: 1, tier: 10 });
    const house = candidate({ creative: 3, campaign: 3, tier: 90, house: true });

    expect(pick(state('house', [top, house])).map((c) => c.creative)).toEqual([1]);
  });

  it('leaves the slot empty when there is no house advert either', () => {
    const top = candidate({ creative: 1, campaign: 1, tier: 10, cap: 1 });
    const paid = candidate({ creative: 2, campaign: 2, tier: 50 });

    exhaust(top);

    expect(pick(state('house', [top, paid]))).toEqual([]);
  });

  it('reads house off the flag, not off the tier', () => {
    // A paid campaign may sit at the house tier without being one, so it is
    // no use as a fallback even though its number says 90.
    const top = candidate({ creative: 1, campaign: 1, tier: 10, cap: 1 });
    const atHouseTier = candidate({ creative: 4, campaign: 4, tier: 90, house: false });

    exhaust(top);

    expect(pick(state('house', [top, atHouseTier]))).toEqual([]);
  });
});

describe('passback', () => {
  it('is held out of the draw while anything else is eligible', () => {
    const paid = candidate({ creative: 1, tier: 50 });
    const fallback = candidate({ creative: 9, campaign: 9, tier: Number.MAX_SAFE_INTEGER, passback: true });

    expect(pick(state('passback', [paid, fallback])).map((c) => c.creative)).toEqual([1]);
  });

  it('is what the slot shows once nothing else is', () => {
    const paid = candidate({ creative: 1, campaign: 1, tier: 50, cap: 1 });
    const fallback = candidate({ creative: 9, campaign: 9, tier: Number.MAX_SAFE_INTEGER, passback: true });

    exhaust(paid);

    expect(pick(state('passback', [paid, fallback])).map((c) => c.creative)).toEqual([9]);
  });

  /**
   * Another slot's fallback must not leak into this one's draw just because
   * the payload carried it.
   */
  it('is never drawn by a slot set to any other mode', () => {
    const paid = candidate({ creative: 1, campaign: 1, tier: 50, cap: 1 });
    const fallback = candidate({ creative: 9, campaign: 9, tier: Number.MAX_SAFE_INTEGER, passback: true });

    exhaust(paid);

    expect(pick(state('next_tier', [paid, fallback]))).toEqual([]);
    expect(pick(state('house', [paid, fallback]))).toEqual([]);
    expect(pick(state('collapse', [paid, fallback]))).toEqual([]);
  });
});
