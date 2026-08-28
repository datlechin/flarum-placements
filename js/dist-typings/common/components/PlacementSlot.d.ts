import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import PlacementState from '../states/PlacementState';
import type { Candidate, SlotConfig } from '../types';
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
    protected picked: Candidate[];
    /**
     * Stops the viewability observers when this slot goes away. A discussion
     * page creates and destroys dozens of these as the reader scrolls.
     */
    protected watchers: Array<() => void>;
    oninit(vnode: Mithril.Vnode<CustomAttrs, this>): void;
    /**
     * Reported from `oncreate` rather than from `view()`, so it fires once per
     * mounted element rather than once per redraw — and flarum/realtime redraws
     * an open discussion every time anybody posts to it.
     */
    oncreate(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    onremove(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    view(): Mithril.Children;
    /**
     * Whether a repeating slot belongs at the position it was handed.
     */
    protected rendersHere(state: PlacementState, slot: SlotConfig): boolean;
    /**
     * Extension point for the parts of a slot. A theme adds a "why this?" link
     * here; a creative type replaces the body.
     */
    contentItems(state: PlacementState, slot: SlotConfig): ItemList<Mithril.Children>;
    /**
     * The visible disclosure.
     *
     * Required twice over: AdSense's policies demand advertising be labelled,
     * and the FTC demands paid placements be identifiable to a reader. It is
     * translated text rather than a class name, so it survives an ad blocker's
     * cosmetic rules and reads in the forum's own language.
     */
    protected label(candidate: Candidate | undefined): Mithril.Children;
    protected creative(candidate: Candidate): Mithril.Children;
    /**
     * The sample shown in demo mode.
     *
     * It names the slot, because "where is the index sidebar?" is the question
     * demo mode exists to answer, and Flarum gives an administrator no other way
     * to find out: placements are Mithril components, not template files they
     * can open.
     */
    demo(slot: SlotConfig): Mithril.Children;
}
