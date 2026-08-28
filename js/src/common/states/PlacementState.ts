import { withinCap } from '../frequency';
import { remember, stickyChoice } from '../sticky';
import type { Breakpoint, Candidate, PlacementPayload, SlotConfig } from '../types';

/**
 * What this page knows about placements.
 *
 * Everything here is synchronous and reads from the boot payload, so a slot
 * paints in the same frame as the page around it. Nothing in this class makes
 * a request.
 */
export default class PlacementState {
  protected payload: PlacementPayload | null;

  constructor(payload?: PlacementPayload | null) {
    this.payload = payload ?? null;
  }

  /**
   * Whether the server wrote anything for this viewer.
   *
   * False for a crawler and for anybody whose group was granted ad-free
   * browsing, in which case no slot renders and nothing is measured.
   */
  get active(): boolean {
    return this.payload !== null;
  }

  /**
   * Whether every slot should render a labelled sample instead of a creative.
   */
  get demo(): boolean {
    return this.payload?.demo ?? false;
  }

  slot(key: string): SlotConfig | null {
    return this.payload?.slots[key] ?? null;
  }

  keys(): string[] {
    return Object.keys(this.payload?.slots ?? {});
  }

  /**
   * The reserved heights, as custom properties for the stylesheet to pick up
   * at each breakpoint.
   *
   * Inline custom properties rather than generated CSS on purpose. Feeding
   * these through Flarum's LESS config variables would mark assets dirty on
   * every save, and `Assets::flushCss()` rebuilds one bundle per locale — so
   * nudging a slot height on an eight-language forum would trigger sixteen
   * recompiles. A media query in our own stylesheet reads these instead.
   */
  reserveVariables(slot: SlotConfig): Record<string, string> {
    const style: Record<string, string> = {};

    (['phone', 'tablet', 'desktop'] as Breakpoint[]).forEach((breakpoint) => {
      const height = slot.reserve[breakpoint];

      if (typeof height === 'number' && height > 0) {
        style[`--placement-reserve-${breakpoint}`] = `${height}px`;
      }
    });

    return style;
  }

  /**
   * Which occurrence of a repeating slot falls at this position, counting from
   * one, or null when nothing belongs here.
   *
   * `position` is a post number or a row index, never a vnode index. That
   * distinction is the whole point: flarum/realtime pushes new posts into an
   * open discussion over a websocket with no route change and no remount, so
   * anything keyed on array position reshuffles under the reader every time
   * somebody replies.
   */
  occurrenceAt(slot: SlotConfig, position: number): number | null {
    const every = slot.everyN;

    if (!slot.repeating || !every || every < 1) return null;
    if (!Number.isInteger(position) || position < 1) return null;
    if (position % every !== 0) return null;

    const occurrence = position / every;

    if (slot.repeatLimit !== null && occurrence > slot.repeatLimit) return null;

    return occurrence;
  }

  /**
   * Whether a repeating slot renders at this position.
   */
  repeatsAt(slot: SlotConfig, position: number): boolean {
    return this.occurrenceAt(slot, position) !== null;
  }

  /**
   * The candidates in the best tier that has any.
   *
   * Tiers are never mixed. A sponsorship and a remnant filler competing in the
   * same weighted draw is what makes "why didn't my paid campaign show?"
   * unanswerable, and the server has already sorted them, so this is the
   * leading run and nothing more.
   */
  topTier(candidates: Candidate[]): Candidate[] {
    if (!candidates.length) return [];

    const best = candidates[0].tier;

    return candidates.filter((candidate) => candidate.tier === best);
  }

  /**
   * Draw up to `maxFill` creatives for a slot, by weight, without repeats.
   *
   * `random` is injectable so the draw can be tested; nothing else should pass
   * it. Note the caller is expected to do this once and keep the result:
   * Mithril redraws on every event and every model change, and flarum/realtime
   * redraws an open discussion whenever anybody posts to it, so drawing inside
   * `view()` would reshuffle the ads under the reader.
   */
  pick(slot: SlotConfig, random: () => number = Math.random): Candidate[] {
    // Frequency is applied before the tier is chosen, not after: a sponsorship
    // this reader has already seen its fill of should let the next tier
    // through rather than leaving the slot empty.
    // Frequency is applied before the tier is chosen, not after: a sponsorship
    // this reader has already seen its fill of should let the next tier
    // through rather than leaving the slot empty.
    const eligible = (slot.candidates ?? []).filter((candidate) => withinCap(candidate));

    // A slot set to hold its choice keeps what it drew earlier in the visit —
    // but only among what is *still* eligible, so a campaign that has since
    // ended or hit its frequency cap is never resurrected.
    if (slot.rotation === 'sticky') {
      const held = stickyChoice(slot.key, eligible);

      if (held.length) return held;
    }

    const remaining = this.topTier(eligible);
    const wanted = Math.min(Math.max(slot.maxFill, 1), remaining.length);
    const chosen: Candidate[] = [];

    for (let filled = 0; filled < wanted; filled++) {
      const total = remaining.reduce((sum, candidate) => sum + Math.max(candidate.weight, 1), 0);

      if (total <= 0) break;

      let point = random() * total;
      let index = remaining.length - 1;

      for (let i = 0; i < remaining.length; i++) {
        point -= Math.max(remaining[i].weight, 1);

        if (point < 0) {
          index = i;
          break;
        }
      }

      chosen.push(remaining[index]);
      remaining.splice(index, 1);
    }

    if (slot.rotation === 'sticky') {
      remember(slot.key, chosen);
    }

    return chosen;
  }

  /**
   * A CSS-safe suffix for a slot's modifier class.
   *
   * Third-party keys are namespaced with dots, which are legal in a class
   * attribute but would need escaping in every selector that matched them.
   */
  static modifier(key: string): string {
    return key.replace(/\./g, '-');
  }
}
