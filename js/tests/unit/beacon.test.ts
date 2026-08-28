import { pending, report, resetBeacon } from '../../src/common/beacon';
import { requiredRatio } from '../../src/common/viewability';
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
    token: 'signed-token',
    nonce: 'abc123',
    issued: 1_800_000_000,
    ...overrides,
  };
}

describe('reporting an event', () => {
  beforeEach(() => resetBeacon());

  it('queues what the server needs to verify it', () => {
    report('impression', candidate(), 'index_above_list');

    expect(pending()).toHaveLength(1);
    expect(pending()[0]).toMatchObject({
      type: 'impression',
      token: 'signed-token',
      nonce: 'abc123',
      issued: 1_800_000_000,
      creative: 7,
      campaign: 3,
      placement: 'index_above_list',
    });
  });

  it('reports the same event only once', () => {
    // Mithril redraws on every event and every model update, and
    // flarum/realtime redraws an open discussion whenever anybody posts to it.
    // Without this one advert would report itself dozens of times a minute.
    report('impression', candidate(), 'index_above_list');
    report('impression', candidate(), 'index_above_list');
    report('impression', candidate(), 'index_above_list');

    expect(pending()).toHaveLength(1);
  });

  it('reports an impression, a view and a click separately', () => {
    // They share a token, because a click has to prove the impression was
    // served, so the guard has to be per kind of event and not per token.
    const one = candidate();

    report('impression', one, 'index_above_list');
    report('viewable', one, 'index_above_list');
    report('click', one, 'index_above_list');

    expect(pending().map((e) => e.type)).toEqual(['impression', 'viewable', 'click']);
  });

  it('treats two creatives as two events', () => {
    report('impression', candidate({ nonce: 'first' }), 'index_above_list');
    report('impression', candidate({ nonce: 'second' }), 'index_sidebar');

    expect(pending()).toHaveLength(2);
  });

  it('reports nothing for a candidate with no token', () => {
    // A demo sample, or something served before measurement was switched on.
    // There is nothing to prove, so there is nothing to count.
    report('impression', candidate({ token: null }), 'index_above_list');
    report('impression', candidate({ nonce: null }), 'index_above_list');
    report('impression', candidate({ issued: null }), 'index_above_list');

    expect(pending()).toHaveLength(0);
  });
});

describe('the share of pixels a creative must show to count as seen', () => {
  it('is half, for an ordinary unit', () => {
    expect(requiredRatio(candidate({ payload: { width: 728, height: 90 } }))).toBe(0.5);
  });

  it('is lower for a large one, because half of it is most of the screen', () => {
    expect(requiredRatio(candidate({ payload: { width: 970, height: 250 } }))).toBe(0.3);
  });

  it('falls back to half when the size is unknown', () => {
    expect(requiredRatio(candidate({ payload: {} }))).toBe(0.5);
    expect(requiredRatio(candidate({ payload: { width: '728', height: '90' } }))).toBe(0.5);
  });
});
