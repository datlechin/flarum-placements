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
     * The subset of `picked` that actually drew something, filled in by
     * `contentItems()` during `view()` and read by `oncreate()` afterwards.
     *
     * These are two different lists and the difference is what gets counted: a
     * candidate can be chosen and then render nothing.
     */
    protected drawn: Candidate[];
    /**
     * Stops the viewability observers when this slot goes away. A discussion
     * page creates and destroys dozens of these as the reader scrolls.
     */
    protected watchers: Array<() => void>;
    /**
     * Whether what was drawn here turned out to render nothing at all.
     *
     * A network container is an empty element the network's own script is meant
     * to fill. When that script never arrives -- blocked, or simply down -- the
     * element stays empty, and nothing tells this component so: the reserved
     * height holds the space open and the disclosure label sits above it, so
     * every page carries a labelled blank rectangle. A large minority of readers
     * see that on every page of the forum.
     */
    protected collapsed: boolean;
    protected collapseTimer?: ReturnType<typeof setTimeout>;
    /**
     * How long to give an external script before deciding it is not coming.
     *
     * Long enough for a slow network to win, short enough that the hole is not
     * part of the reading experience.
     */
    protected static readonly FILL_TIMEOUT = 2500;
    oninit(vnode: Mithril.Vnode<CustomAttrs, this>): void;
    /**
     * Reported from `oncreate` rather than from `view()`, so it fires once per
     * mounted element rather than once per redraw.
     */
    oncreate(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    /**
     * Collapse the slot when nothing ever appeared in it.
     *
     * Only for creatives whose content arrives from outside: everything this
     * extension renders itself is on the page by the time `oncreate` runs, so a
     * check would either be pointless or, worse, race a first paint and hide
     * something that was about to appear.
     *
     * The impression has already been reported by this point and is left alone.
     * It was served: the server chose it, the element reached the page, and the
     * reader's blocker is not something the publisher can attest to either way.
     * What the collapse fixes is the hole, not the accounting -- and viewability,
     * which is measured separately and will never fire for an element with no
     * height, is what stops an empty container looking like a seen one.
     */
    protected watchForAnEmptyContainer(dom: Element): void;
    onremove(vnode: Mithril.VnodeDOM<CustomAttrs, this>): void;
    view(): Mithril.Children;
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
