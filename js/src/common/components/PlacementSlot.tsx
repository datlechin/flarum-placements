import app from 'flarum/common/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { report } from '../beacon';
import { recordSeen } from '../frequency';
import placements from '../placements';
import { rendererFor } from '../renderers';
import { watchViewability } from '../viewability';
import PlacementState from '../states/PlacementState';
import { DemoMode } from '../placements';
import type { Candidate, SlotConfig } from '../types';

/**
 * Whether a renderer produced nothing worth wrapping.
 *
 * `null` is what a renderer returns when it declines -- no consent yet, a
 * payload it cannot use, no logos in the list. Mithril also treats `undefined`,
 * `false` and `''` as nothing, and an empty array renders no elements, so all
 * of them have to count as nothing here too, or the slot draws a labelled
 * empty box and books an impression for it.
 */
function isEmpty(content: Mithril.Children): boolean {
  if (Array.isArray(content)) return content.every(isEmpty);

  return content === null || content === undefined || content === false || content === '';
}

export interface PlacementSlotAttrs extends ComponentAttrs {
  className?: string;
  /** The placement key this slot renders. */
  name: string;
  /**
   * For a repeating slot, the position it is being asked to render at — a post
   * number or a row index, counting from one. Never a vnode index.
   */
  position?: number;
}

/**
 * One slot on the page.
 *
 * Renders nothing at all when there is nothing to show, rather than an empty
 * box: a collapsed slot is the correct answer both when the viewer is ad-free
 * and when nothing matched, and holding a reserved gap open for a creative
 * that will never arrive is worse than the layout shift the reservation exists
 * to prevent.
 */
export default class PlacementSlot<CustomAttrs extends PlacementSlotAttrs = PlacementSlotAttrs> extends Component<CustomAttrs> {
  /**
   * Drawn once, at mount, and kept.
   *
   * Mithril redraws on every event, every route change and every model update,
   * and flarum/realtime redraws an open discussion whenever anybody posts to
   * it. Drawing in `view()` would reshuffle the advert under the reader
   * several times a minute on a busy thread.
   */
  protected picked: Candidate[] = [];

  /**
   * The subset of `picked` that actually drew something, filled in by
   * `contentItems()` during `view()` and read by `oncreate()` afterwards.
   *
   * These are two different lists and the difference is what gets counted: a
   * candidate can be chosen and then render nothing.
   */
  protected drawn: Candidate[] = [];

  /**
   * Stops the viewability observers when this slot goes away. A discussion
   * page creates and destroys dozens of these as the reader scrolls.
   */
  protected watchers: Array<() => void> = [];

  oninit(vnode: Mithril.Vnode<CustomAttrs, this>) {
    super.oninit(vnode);

    const state = placements();
    const slot = state.slot(this.attrs.name);

    if (slot && !state.demo && this.rendersHere(state, slot)) {
      this.picked = state.pick(slot);
    }
  }

  /**
   * Reported from `oncreate` rather than from `view()`, so it fires once per
   * mounted element rather than once per redraw.
   */
  oncreate(vnode: Mithril.VnodeDOM<CustomAttrs, this>) {
    super.oncreate(vnode);

    const state = placements();

    // `drawn`, not `picked`: only what reached the page is an impression.
    if (state.demo || !this.drawn.length) return;

    this.drawn.forEach((candidate) => {
      report('impression', candidate, this.attrs.name);
      recordSeen(candidate);

      this.watchers.push(watchViewability(vnode.dom, candidate, () => report('viewable', candidate, this.attrs.name)));
    });
  }

  onremove(vnode: Mithril.VnodeDOM<CustomAttrs, this>) {
    this.watchers.forEach((stop) => stop());
    this.watchers = [];

    super.onremove(vnode);
  }

  view(): Mithril.Children {
    const state = placements();
    const slot = state.slot(this.attrs.name);

    if (!slot || !this.rendersHere(state, slot)) return null;

    const content = this.contentItems(state, slot).toArray();

    if (!content.length) return null;

    return (
      <aside
        className={classList('Placement', `Placement--${PlacementState.modifier(slot.key)}`, state.demo && 'Placement--demo', this.attrs.className)}
        style={state.reserveVariables(slot)}
        aria-label={extractText(app.translator.trans('datlechin-placements.forum.label'))}
      >
        {content}
      </aside>
    );
  }

  protected rendersHere(state: PlacementState, slot: SlotConfig): boolean {
    return !slot.repeating || state.repeatsAt(slot, this.attrs.position ?? 0);
  }

  /**
   * Extension point for the parts of a slot. A theme adds a "why this?" link
   * here; a creative type replaces the body.
   */
  contentItems(state: PlacementState, slot: SlotConfig): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    if (state.demo) {
      items.add('demo', this.demo(slot), 50);

      return items;
    }

    // Drawn first, then filtered on what actually produced something. A
    // renderer that returns nothing -- a network container waiting on consent,
    // a payload the type could not use -- must not leave a labelled empty box
    // holding the reserve open, and must not be counted as an impression.
    const drawn = this.picked
      .map((candidate) => ({ candidate, content: this.creative(candidate) }))
      .filter((entry): entry is { candidate: Candidate; content: Mithril.Children } => !isEmpty(entry.content));

    // Read by `oncreate`, which runs after the first `view()`. Reporting from
    // `this.picked` instead is how an empty slot came to book an impression
    // and a viewable impression on the one number worth quoting to a sponsor.
    this.drawn = drawn.map((entry) => entry.candidate);

    if (!drawn.length) return items;

    if (slot.labelMode !== 'never') {
      items.add('label', this.label(drawn[0].candidate), 100);
    }

    items.add(
      'creatives',
      drawn.map((entry) => entry.content),
      50
    );

    return items;
  }

  /**
   * The visible disclosure.
   *
   * Required twice over: AdSense's policies demand advertising be labelled,
   * and the FTC demands paid placements be identifiable to a reader. It is
   * translated text rather than a class name, so it survives an ad blocker's
   * cosmetic rules and reads in the forum's own language.
   */
  protected label(candidate: Candidate | undefined): Mithril.Children {
    return <span className="Placement-label">{candidate?.label ?? app.translator.trans('datlechin-placements.forum.label')}</span>;
  }

  protected creative(candidate: Candidate): Mithril.Children {
    const renderer = rendererFor(candidate.type);

    // A creative whose type nothing knows how to draw renders as nothing,
    // which collapses the slot. Better than a broken box on every page of a
    // forum whose administrator disabled the extension that owned the type.
    if (!renderer) return null;

    const content = renderer(candidate);

    // The wrapper is only worth having around something. Returning it
    // regardless is what made an empty creative indistinguishable from a
    // drawn one to everything downstream, including the counters.
    if (isEmpty(content)) return null;

    // `mousedown` and `auxclick` rather than `click`: a middle click and a
    // ctrl-click open the destination in a new tab without ever firing a
    // click event on the link, and those are real readers going to a real
    // advertiser.
    const count = () => report('click', candidate, this.attrs.name);

    return (
      <div className="Placement-creative" onmousedown={count} onauxclick={count}>
        {content}
      </div>
    );
  }

  /**
   * The sample shown in demo mode.
   *
   * It names the slot, because "where is the index sidebar?" is the question
   * demo mode exists to answer, and Flarum gives an administrator no other way
   * to find out: placements are Mithril components, not template files they
   * can open.
   */
  demo(slot: SlotConfig): Mithril.Children {
    const size = slot.recommendedSize;

    return (
      <div className="Placement-demo">
        <span className="Placement-demoName">{app.translator.trans(slot.label)}</span>
        <code className="Placement-demoKey">{slot.key}</code>
        {size && <span className="Placement-demoSize">{`${size[0]}×${size[1]}`}</span>}

        {/* Demo mode is held in the session, so leaving it means knowing to
            type `?placement_demo=0` -- which nothing anywhere says. A way in
            with no way out is a trap, even a harmless one. */}
        <a className="Placement-demoExit" href={`${app.forum.attribute('baseUrl')}/?${DemoMode.PARAM}=0`}>
          {app.translator.trans('datlechin-placements.forum.demo_exit')}
        </a>
      </div>
    );
  }
}
