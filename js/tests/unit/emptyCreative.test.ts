import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import mq from 'mithril-query';
import m from 'mithril';

import PlacementSlot from '../../src/common/components/PlacementSlot';
import PlacementState from '../../src/common/states/PlacementState';
import { setPlacements } from '../../src/common/placements';
import { registerRenderer, resetRenderers } from '../../src/common/renderers';
import { pending, resetBeacon } from '../../src/common/beacon';
import type { Candidate, PlacementPayload, SlotConfig } from '../../src/common/types';

beforeAll(() => bootstrapForum());

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
    reserve: {},
    fallback: 'house',
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
    type: 'drawable',
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
  const payload: PlacementPayload = {
    demo: false,
    slots: { notice: { ...slot(), candidates } as any },
  } as any;

  setPlacements(new PlacementState(payload));
}

beforeEach(() => {
  resetBeacon();
  resetRenderers();

  registerRenderer('drawable', () => m('span', { className: 'Drew' }, 'an advert'));
  // Exactly what a network container does before consent is known, and what
  // any type does when it cannot use the payload it was given.
  registerRenderer('draws-nothing', () => null);
});

const render = () => mq(m(PlacementSlot, { name: 'notice' }));

describe('a creative that draws nothing', () => {
  it('leaves no labelled empty box behind', () => {
    seed([candidate({ type: 'draws-nothing' })]);

    const rendered = render();

    expect(rendered).not.toHaveElement('.Placement-label');
    expect(rendered).not.toHaveElement('.Placement-creative');
    expect(rendered).not.toHaveElement('.Placement');
  });

  /**
   * The number the README calls the one worth quoting to a sponsor. Counting
   * an advert nobody could see inflates it.
   */
  it('is not counted as an impression', () => {
    seed([candidate({ type: 'draws-nothing' })]);

    render();

    expect(pending()).toEqual([]);
  });

  it('does not stop the ones beside it from drawing or counting', () => {
    seed([candidate({ type: 'draws-nothing', creative: 1 }), candidate({ type: 'drawable', creative: 2 })]);

    const rendered = render();

    expect(rendered.find('.Placement-creative')).toHaveLength(1);
    expect(rendered).toHaveElement('.Placement-label');

    const reported = pending();

    expect(reported).toHaveLength(1);
    expect(reported[0].creative).toBe(2);
  });
});

describe('a creative that draws', () => {
  it('is wrapped, labelled and counted', () => {
    seed([candidate()]);

    const rendered = render();

    expect(rendered).toHaveElement('.Placement-creative .Drew');
    expect(rendered).toHaveElement('.Placement-label');

    const reported = pending();

    expect(reported).toHaveLength(1);
    expect(reported[0].type).toBe('impression');
  });

  /**
   * A renderer can return an array, and an array of nothings is still
   * nothing.
   */
  it('is judged on what the array holds, not on the array', () => {
    resetRenderers();
    registerRenderer('drawable', () => [null, false, '']);

    seed([candidate()]);

    expect(render()).not.toHaveElement('.Placement-creative');
    expect(pending()).toEqual([]);
  });
});
