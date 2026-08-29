import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';

import PlacementState from '../../src/common/states/PlacementState';
import { setPlacements } from '../../src/common/placements';
import { refreshTokens, resetRefresh } from '../../src/common/refresh';
import type { Candidate, PlacementPayload, SlotConfig } from '../../src/common/types';

/**
 * Asking for fresh proof once per navigation.
 *
 * A plan is minted once per page load, so one nonce had to cover an entire
 * reading session -- and a nonce is refused twice by the browser and again by
 * the server. Sixty pages produced one impression per creative.
 */

beforeAll(() => {
  bootstrapForum();

  app.forum = app.store.createRecord('forums');
  app.forum.pushData({ id: '1', type: 'forums', attributes: { apiUrl: 'https://forum.test/api' } });
});

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
    token: 'original',
    nonce: 'first',
    issued: 1_800_000_000,
    cap: null,
    window: 'day',
    ...overrides,
  };
}

function seed(candidates: Candidate[], demo = false) {
  const slot = {
    key: 'notice',
    group: 'global',
    label: 'x.label',
    description: 'x.description',
    allowedTypes: [],
    maxFill: 2,
    repeating: false,
    recommendedSize: null,
    reserve: {},
    fallback: 'next_tier',
    passbackCreativeId: null,
    labelMode: 'inherit',
    everyN: null,
    repeatLimit: null,
    sortOrder: 0,
    candidates,
  } as unknown as SlotConfig;

  setPlacements(new PlacementState({ demo, slots: { notice: slot } } as unknown as PlacementPayload));

  return candidates;
}

let requests: Array<Record<string, any>>;

beforeEach(() => {
  resetRefresh();

  requests = [];

  app.request = ((options: any) => {
    requests.push(options);

    return Promise.resolve({
      tokens: [{ creative: 7, campaign: 3, placement: 'notice', token: 'renewed', nonce: 'second', issued: 1_800_000_900 }],
    });
  }) as any;
});

describe('refreshing the tokens on a navigation', () => {
  it('replaces the proof on the candidate the slot is already holding', async () => {
    const [held] = seed([candidate()]);

    await refreshTokens();

    // Mutated in place: a mounted slot must go on drawing the same advert.
    expect(held.token).toBe('renewed');
    expect(held.nonce).toBe('second');
    expect(held.issued).toBe(1_800_000_900);
  });

  it('sends what it holds, so the server can read the triple out of the signature', async () => {
    seed([candidate()]);

    await refreshTokens();

    expect(requests).toHaveLength(1);
    expect(requests[0].body.tokens).toEqual([
      { creative: 7, campaign: 3, placement: 'notice', token: 'original', nonce: 'first', issued: 1_800_000_000 },
    ]);
  });

  /**
   * A reader clicking through a thread produces a navigation per click. One
   * request per click would be worse than the undercount.
   */
  it('does not ask again straight away', async () => {
    seed([candidate()]);

    await refreshTokens(1_000_000);
    await refreshTokens(1_005_000);

    expect(requests).toHaveLength(1);
  });

  it('asks again once enough time has passed', async () => {
    seed([candidate()]);

    await refreshTokens(1_000_000);
    await refreshTokens(1_100_000);

    expect(requests).toHaveLength(2);
  });

  it('asks nothing of a viewer who is being served nothing', async () => {
    setPlacements(new PlacementState(null));

    await refreshTokens();

    expect(requests).toHaveLength(0);
  });

  /**
   * A demo sample carries no token and is not measured, so there is nothing to
   * renew.
   */
  it('asks nothing in demo mode', async () => {
    seed([candidate()], true);

    await refreshTokens();

    expect(requests).toHaveLength(0);
  });

  it('leaves the old proof in place when the server refuses', async () => {
    const [held] = seed([candidate()]);

    app.request = (() => Promise.reject(new Error('nope'))) as any;

    await refreshTokens();

    // The old token may still be within its reporting lifetime. The worst case
    // is the undercount this exists to fix, never a wrong count.
    expect(held.token).toBe('original');
    expect(held.nonce).toBe('first');
  });
});
