import app from 'flarum/common/app';

import PlacementState from './states/PlacementState';
import type { PlacementPayload } from './types';

let state: PlacementState | null = null;

/**
 * The page's placement state, built once from the boot payload.
 *
 * Memoised rather than rebuilt per render: `view()` runs on every Mithril
 * redraw, and flarum/realtime redraws an open discussion every time anybody
 * posts to it.
 */
export default function placements(): PlacementState {
  if (state === null) {
    state = new PlacementState((app.data as Record<string, unknown>).placement as PlacementPayload | undefined);
  }

  return state;
}

/**
 * Replace the state. For tests, and for the admin preview, which needs to
 * render slots against a payload the server did not send.
 */
export function setPlacements(next: PlacementState | null): void {
  state = next;
}

/**
 * Demo mode, as the server names it.
 *
 * Kept beside the client rather than typed into a link, so the way out cannot
 * drift from the way in.
 *
 * @see \Datlechin\Placements\Support\DemoMode
 */
export const DemoMode = {
  PARAM: 'placement_demo',
} as const;
