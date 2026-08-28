import PlacementState from '../../src/common/states/PlacementState';
import type { PlacementPayload, SlotConfig } from '../../src/common/types';

function slot(overrides: Partial<SlotConfig> = {}): SlotConfig {
  return {
    key: 'notice',
    group: 'global',
    label: 'x.label',
    description: 'x.description',
    allowedTypes: [],
    maxFill: 1,
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

function payload(slots: SlotConfig[], demo = false): PlacementPayload {
  return {
    demo,
    slots: Object.fromEntries(slots.map((s) => [s.key, s])),
  };
}

describe('PlacementState', () => {
  describe('when the server wrote nothing', () => {
    // Which is what happens for an ad-free viewer and for a crawler. Nothing
    // renders, nothing is reserved, nothing is measured.
    const state = new PlacementState();

    it('is inactive', () => {
      expect(state.active).toBe(false);
    });

    it('is not in demo mode', () => {
      expect(state.demo).toBe(false);
    });

    it('has no slots', () => {
      expect(state.keys()).toEqual([]);
      expect(state.slot('notice')).toBeNull();
    });
  });

  it('is active once a payload arrives', () => {
    const state = new PlacementState(payload([slot()]));

    expect(state.active).toBe(true);
    expect(state.slot('notice')?.key).toBe('notice');
    expect(state.slot('nothing')).toBeNull();
  });

  it('reports demo mode from the payload', () => {
    expect(new PlacementState(payload([], true)).demo).toBe(true);
    expect(new PlacementState(payload([], false)).demo).toBe(false);
  });

  describe('reserved space', () => {
    it('emits a custom property per breakpoint that reserves something', () => {
      const state = new PlacementState();

      expect(state.reserveVariables(slot({ reserve: { phone: 100, desktop: 90 } }))).toEqual({
        '--placement-reserve-phone': '100px',
        '--placement-reserve-desktop': '90px',
      });
    });

    it('leaves out a breakpoint that reserves nothing', () => {
      // Absent is not the same instruction as zero: the stylesheet falls back
      // to 0 for a missing property, but emitting `0px` would pin the slot
      // open at a height somebody explicitly did not ask for.
      const state = new PlacementState();

      expect(state.reserveVariables(slot({ reserve: {} }))).toEqual({});
      expect(state.reserveVariables(slot({ reserve: { phone: 0 } }))).toEqual({});
    });
  });

  describe('repeating slots', () => {
    const state = new PlacementState();
    const every5 = slot({ key: 'post_footer', repeating: true, everyN: 5 });

    it('renders at every nth position and nowhere else', () => {
      expect(state.repeatsAt(every5, 5)).toBe(true);
      expect(state.repeatsAt(every5, 10)).toBe(true);
      expect(state.repeatsAt(every5, 4)).toBe(false);
      expect(state.repeatsAt(every5, 6)).toBe(false);
    });

    it('counts occurrences from one', () => {
      expect(state.occurrenceAt(every5, 5)).toBe(1);
      expect(state.occurrenceAt(every5, 15)).toBe(3);
      expect(state.occurrenceAt(every5, 7)).toBeNull();
    });

    it('never renders at position zero', () => {
      // Post numbers start at one. Treating 0 as a multiple would put an ad
      // above the first post of every discussion.
      expect(state.repeatsAt(every5, 0)).toBe(false);
    });

    it('ignores negative and fractional positions', () => {
      expect(state.repeatsAt(every5, -5)).toBe(false);
      expect(state.repeatsAt(every5, 2.5)).toBe(false);
    });

    it('stops after the repeat limit', () => {
      const limited = slot({ repeating: true, everyN: 5, repeatLimit: 2 });

      expect(state.repeatsAt(limited, 5)).toBe(true);
      expect(state.repeatsAt(limited, 10)).toBe(true);
      expect(state.repeatsAt(limited, 15)).toBe(false);
    });

    it('never repeats a slot that is not marked repeating', () => {
      expect(state.repeatsAt(slot({ repeating: false, everyN: 5 }), 5)).toBe(false);
    });

    it('never repeats when no interval was configured', () => {
      // An administrator who enabled the slot but left "every N" empty gets
      // nothing rather than an ad after every single post.
      expect(state.repeatsAt(slot({ repeating: true, everyN: null }), 5)).toBe(false);
      expect(state.repeatsAt(slot({ repeating: true, everyN: 0 }), 5)).toBe(false);
    });
  });

  describe('modifier class', () => {
    it('leaves a plain key alone', () => {
      expect(PlacementState.modifier('index_sidebar')).toBe('index_sidebar');
    });

    it('replaces the dots a third party namespaces with', () => {
      // Legal in a class attribute, but every selector matching it would need
      // escaping.
      expect(PlacementState.modifier('acme.profile_rail')).toBe('acme-profile_rail');
      expect(PlacementState.modifier('acme.widgets.rail')).toBe('acme-widgets-rail');
    });
  });
});
