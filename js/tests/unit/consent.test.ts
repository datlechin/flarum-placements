import { consentGranted, mayLoadWithConsent, resetConsent, setConsent, whenConsented } from '../../src/common/consent';
import registerCreatives from '../../src/common/creatives';
import { rendererFor, resetRenderers } from '../../src/common/renderers';
import type { Candidate } from '../../src/common/types';

describe('consent', () => {
  beforeEach(() => resetConsent());

  it('starts unknown', () => {
    // Not "no", and not "yes". Nobody has been asked yet.
    expect(consentGranted()).toBeNull();
  });

  it('treats unknown as not yet', () => {
    // Loading and hoping somebody objects later is the thing consent exists
    // to prevent.
    expect(mayLoadWithConsent(true)).toBe(false);
  });

  it('lets a creative that needs no consent load regardless', () => {
    // A first-party image sets nothing, so there is nothing to consent to.
    expect(mayLoadWithConsent(false)).toBe(true);

    setConsent(false);
    expect(mayLoadWithConsent(false)).toBe(true);
  });

  it('lets a creative load once consent is given', () => {
    setConsent(true);

    expect(consentGranted()).toBe(true);
    expect(mayLoadWithConsent(true)).toBe(true);
  });

  it('refuses again when consent is withdrawn', () => {
    setConsent(true);
    setConsent(false);

    expect(mayLoadWithConsent(true)).toBe(false);
  });

  describe('waiting for an answer', () => {
    // A plain counter rather than a spy: these run as ES modules, where the
    // `jest` global is not defined.
    function counter() {
      const calls = { count: 0 };

      return [() => calls.count++, calls] as const;
    }

    it('runs a listener when consent arrives', () => {
      const [load, calls] = counter();

      whenConsented(load);
      expect(calls.count).toBe(0);

      setConsent(true);
      expect(calls.count).toBe(1);
    });

    it('runs a listener immediately when consent is already given', () => {
      setConsent(true);

      const [load, calls] = counter();
      whenConsented(load);

      expect(calls.count).toBe(1);
    });

    it('does not run a listener on a refusal', () => {
      const [load, calls] = counter();

      whenConsented(load);
      setConsent(false);

      expect(calls.count).toBe(0);
    });

    it('does not run the same listener twice for one answer', () => {
      const [load, calls] = counter();

      whenConsented(load);
      setConsent(true);
      setConsent(true);

      expect(calls.count).toBe(1);
    });

    it('can be cancelled by a slot that goes away first', () => {
      const [load, calls] = counter();

      const cancel = whenConsented(load);
      cancel();
      setConsent(true);

      expect(calls.count).toBe(0);
    });
  });
});

describe('a network container', () => {
  function candidate(payload: Record<string, unknown>): Candidate {
    return {
      creative: 1,
      campaign: 1,
      tier: 50,
      weight: 10,
      type: 'network',
      payload,
      url: null,
      label: null,
    };
  }

  const container = { element: 'div', attributes: { 'data-ad-client': 'pub-1' }, requiresConsent: true };

  beforeEach(() => {
    resetConsent();
    resetRenderers();
    registerCreatives();
  });

  it('renders nothing until the reader has consented', () => {
    // The whole reason the seam exists: a network sets things, and loading it
    // before anybody agreed is what consent is meant to prevent.
    expect(rendererFor('network')!(candidate(container))).toBeNull();
  });

  it('renders once consent is given', () => {
    setConsent(true);

    expect(rendererFor('network')!(candidate(container))).not.toBeNull();
  });

  it('renders nothing again when consent is withdrawn', () => {
    setConsent(true);
    setConsent(false);

    expect(rendererFor('network')!(candidate(container))).toBeNull();
  });

  it('renders straight away for a container that needs no consent', () => {
    const noConsent = { ...container, requiresConsent: false };

    expect(rendererFor('network')!(candidate(noConsent))).not.toBeNull();
  });
});
