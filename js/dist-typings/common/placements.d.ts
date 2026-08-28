import PlacementState from './states/PlacementState';
/**
 * The page's placement state, built once from the boot payload.
 *
 * Memoised rather than rebuilt per render: `view()` runs on every Mithril
 * redraw, and flarum/realtime redraws an open discussion every time anybody
 * posts to it.
 */
export default function placements(): PlacementState;
/**
 * Replace the state. For tests, and for the admin preview, which needs to
 * render slots against a payload the server did not send.
 */
export declare function setPlacements(next: PlacementState | null): void;
