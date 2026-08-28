import app from 'flarum/forum/app';

import { consentGranted, setConsent } from '../common/consent';
import registerCreatives from '../common/creatives';
import placements from '../common/placements';
import registerSlots, { registerTagSlots } from './slots';

export { default as extend } from './extend';

export { default as PlacementSlot } from '../common/components/PlacementSlot';
export { default as PlacementState } from '../common/states/PlacementState';
export { default as placements, setPlacements } from '../common/placements';
export { registerRenderer, rendererFor } from '../common/renderers';
export { report, flush, device } from '../common/beacon';
export { watchViewability, requiredRatio } from '../common/viewability';
export { withinCap, timesSeen, recordSeen } from '../common/frequency';
export { stickyChoice, remember } from '../common/sticky';
export { setConsent, consentGranted, mayLoadWithConsent, whenConsented } from '../common/consent';

app.initializers.add('datlechin-placement', () => {
  // Nothing was written for this viewer — they are ad-free, or a crawler — so
  // there is no reason to register a single extension point. Not registering
  // is also what guarantees an ad-free page has no reserved gaps in it.
  if (!placements().active) return;

  registerCreatives();

  // The seam a forum's own consent banner talks to. Not a consent management
  // platform: whatever CMP the forum already runs tells us once, and creatives
  // that need consent wait until it does.
  (window as unknown as Record<string, unknown>).flarumPlacement = { setConsent, consentGranted };
  registerSlots();
  registerTagSlots();
});
