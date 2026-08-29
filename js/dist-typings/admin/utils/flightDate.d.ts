import type Mithril from 'mithril';
/**
 * One end of a campaign's flight, as a date somebody can act on.
 *
 * Not core's `humanTime`. That helper deliberately clamps anything in the
 * future to the present -- see the comment in `common/utils/humanTime`: it
 * exists to stop a post whose timestamp is a few seconds ahead of the reader's
 * clock reading "in a few seconds". Every date it is given is meant to be in
 * the past.
 *
 * A flight is the opposite case. `starts_at` and `ends_at` are routinely in the
 * future, and that clamp turned every one of them into "a few seconds ago": a
 * campaign scheduled to run for the next six weeks reported that it had already
 * finished, on both the campaign list and the campaign's own page.
 *
 * Absolute is also simply the more useful form here. Somebody reconciling an
 * invoice, or answering "when does this come off the forum?", wants the date,
 * not a distance from now.
 */
export default function flightDate(date: Date): Mithril.Vnode;
