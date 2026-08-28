/**
 * Whether the reader has agreed to whatever a network sets.
 *
 * This extension is not a consent management platform and does not pretend to
 * be one. Since January 2024 Google has required a *certified* IAB TCF v2.2 CMP
 * for traffic from the EEA and the UK, and shipping our own banner would imply
 * a certification we do not have and cannot get.
 *
 * What this is instead is the seam. Whatever CMP a forum already runs tells us
 * once, and creatives that need consent wait until it does:
 *
 * ```js
 * // from the forum's own consent banner, whichever one it is
 * window.flarumPlacement.setConsent(true);
 * ```
 *
 * Until somebody says otherwise, consent is unknown — and unknown is treated
 * as "not yet", so a network creative does not load. A first-party image
 * advert is unaffected: it sets nothing, so there is nothing to consent to.
 */
type Consent = boolean | null;

let granted: Consent = null;
const listeners: Array<(granted: boolean) => void> = [];

export function consentGranted(): Consent {
  return granted;
}

/**
 * Whether a creative that needs consent may load right now.
 *
 * Unknown reads as no. The alternative — loading and hoping somebody objects
 * later — is the thing consent exists to prevent.
 */
export function mayLoadWithConsent(requiresConsent: boolean): boolean {
  return !requiresConsent || granted === true;
}

export function setConsent(value: boolean): void {
  if (granted === value) return;

  granted = value;

  if (value) listeners.forEach((listener) => listener(true));
}

/**
 * Run something once consent arrives, or immediately if it already has.
 *
 * Returns a function that cancels the wait, for a slot that is unmounted
 * before the reader has decided.
 */
export function whenConsented(listener: () => void): () => void {
  if (granted === true) {
    listener();

    return () => {};
  }

  listeners.push(listener);

  return () => {
    const index = listeners.indexOf(listener);

    if (index !== -1) listeners.splice(index, 1);
  };
}

/**
 * For tests.
 */
export function resetConsent(): void {
  granted = null;
  listeners.length = 0;
}
