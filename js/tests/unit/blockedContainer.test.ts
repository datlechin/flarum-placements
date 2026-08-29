import { jest } from '@jest/globals';
import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import mq from 'mithril-query';
import m from 'mithril';

import PlacementSlot from '../../src/common/components/PlacementSlot';
import PlacementState from '../../src/common/states/PlacementState';
import { setPlacements } from '../../src/common/placements';
import { registerRenderer, resetRenderers } from '../../src/common/renderers';
import { resetBeacon } from '../../src/common/beacon';
import type { Candidate, PlacementPayload, SlotConfig } from '../../src/common/types';

/**
 * A container an external script never fills.
 *
 * A network creative is an empty element the network's own script is meant to
 * fill. When that script does not arrive -- blocked, or simply down -- the
 * element stays empty and nothing tells the slot so: the reserved height holds
 * the space open and the disclosure label sits above it, so every page carries
 * a labelled blank rectangle. A large minority of readers on any forum see
 * exactly that.
 */

beforeAll(() => {
  bootstrapForum();

  // `bootstrapForum` loads a forum resource but never boots the application,
  // so `app.forum` is not populated -- and the beacon reads its endpoint from
  // there the moment the clock is advanced.
  app.forum = app.store.createRecord('forums');
  app.forum.pushData({ id: '1', type: 'forums', attributes: { apiUrl: 'https://forum.test/api', baseUrl: 'https://forum.test' } });
});

function slot(overrides: Partial<SlotConfig> = {}): SlotConfig {
  return {
    key: 'notice',
    group: 'global',
    label: 'x.label',
    description: 'x.description',
    allowedTypes: [],
    maxFill: 2,
    repeating: false,
    recommendedSize: null,
    // Empty: the DOM shim these tests run against cannot parse the CSS
    // custom properties a reserved height produces, and the height is not
    // what is under test here.
    reserve: {},
    fallback: 'next_tier',
    passbackCreativeId: null,
    labelMode: 'inherit',
    everyN: null,
    repeatLimit: null,
    sortOrder: 0,
    ...overrides,
  };
}

function candidate(overrides: Partial<Candidate> = {}): Candidate {
  return {
    creative: 7,
    campaign: 3,
    tier: 50,
    weight: 10,
    type: 'network',
    payload: {},
    url: null,
    label: null,
    token: 'signed',
    nonce: 'abc',
    issued: 1_800_000_000,
    cap: null,
    window: 'day',
    ...overrides,
  };
}

function seed(candidates: Candidate[]) {
  setPlacements(
    new PlacementState({
      demo: false,
      slots: { notice: { ...slot(), candidates } as any },
    } as unknown as PlacementPayload)
  );
}

beforeEach(() => {
  jest.useFakeTimers();

  // Advancing the clock far enough to collapse the slot also fires the
  // beacon's own debounce, and the environment these tests run in has no
  // `fetch`. What it sends is asserted elsewhere.
  (globalThis as unknown as { fetch: unknown }).fetch = () => Promise.resolve({ ok: true });

  resetBeacon();
  resetRenderers();

  // Stands in for a network container: an element with nothing in it, waiting
  // for a script that may never come.
  registerRenderer('network', () => m('div', { className: 'Container' }));

  // Anything this extension renders itself is on the page by the time the slot
  // mounts, so it must never be collapsed.
  registerRenderer('image', () => m('span', { className: 'Drew' }, 'an advert'));
});

afterEach(() => {
  jest.useRealTimers();
});

// `mq(Component, attrs)` rather than `mq(m(Component, attrs))`: reusing a
// single vnode across redraws is a Mithril anti-pattern, and the redraw then
// renders the stale tree -- so a change made by a timer never appears.
const render = () => mq(PlacementSlot, { name: 'notice' });

describe('a network container that never fills', () => {
  it('is left alone while the script might still arrive', () => {
    seed([candidate()]);

    const rendered = render();

    expect(rendered).toHaveElement('.Placement');

    jest.advanceTimersByTime(1000);
    rendered.redraw();

    expect(rendered).toHaveElement('.Placement');
  });

  it('collapses once it is clear nothing is coming', () => {
    seed([candidate()]);

    const rendered = render();

    jest.advanceTimersByTime(5000);
    rendered.redraw();

    // No label and no reserved height, rather than a labelled hole on every
    // page of the forum.
    expect(rendered).not.toHaveElement('.Placement');
    expect(rendered).not.toHaveElement('.Placement-label');
  });

  /**
   * The check is only for content that arrives from outside. Everything this
   * extension draws itself is already on the page, so measuring it would
   * either prove nothing or race a first paint and hide an advert that was
   * about to appear.
   */
  it('never collapses a creative this extension rendered itself', () => {
    seed([candidate({ type: 'image' })]);

    const rendered = render();

    jest.advanceTimersByTime(5000);
    rendered.redraw();

    expect(rendered).toHaveElement('.Placement');
    expect(rendered).toHaveElement('.Drew');
  });
});
