import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { SlotConfig } from '../../../common/types';
import type Creative from '../../models/Creative';
import type PlacementSetting from '../../models/PlacementSetting';
/**
 * Every slot this forum has, and what may be changed about each one.
 *
 * Slots are declared in code, so this list cannot be added to -- what is
 * editable is whether each one is used and how. A slot with no row is not
 * unconfigured, it is default-configured, which is why saving writes a row
 * rather than the client creating one up front.
 *
 * The controls are behind a disclosure, one slot at a time. Every slot used to
 * render every control it had, always: eight to eleven inputs across sixteen
 * slots, so opening this screen meant meeting something like a hundred and
 * thirty form controls at once, all in identically-styled rows with nothing but
 * the inline label of each saying which cluster it belonged to. Finding one
 * setting meant reading all of them.
 *
 * Collapsed, a slot still says what it is set to. That matters more than the
 * controls: the common question is "what is this slot doing?", not "let me
 * change it", and answering it should not require opening anything.
 */
export default class SlotsTab extends Component {
    protected settings: Record<string, PlacementSetting>;
    /** Approved creatives, for the passback picker. */
    protected creatives: Creative[] | null;
    protected loading: boolean;
    protected saving: string | null;
    /** The slot whose controls are open. One at a time, deliberately. */
    protected open: string | null;
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): Mithril.Children;
    protected row(slot: SlotConfig): Mithril.Children;
    /**
     * What this slot is set to, without opening it.
     *
     * Only the settings that have been moved away from their default are shown:
     * a row repeating "max 1, next tier, random" for every slot on the forum is
     * noise that hides the one slot somebody actually changed.
     */
    protected summary(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    /**
     * How much this slot holds and what it says about itself.
     */
    protected deliveryControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    /**
     * What the slot does when nothing matched.
     */
    protected fallbackControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    /**
     * The height held open while a creative loads.
     *
     * Per breakpoint, because a slot that is a leaderboard on a desktop is
     * usually something much shorter on a phone, and reserving the desktop height
     * everywhere pushes the page down on the readers who can least afford it.
     * Blank means reserve nothing, which is not the same as reserving zero.
     */
    protected reserveControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    protected rotationControl(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    /**
     * Only a repeating slot gets these. Offering "every N" on a slot that renders
     * once would be a control that visibly does nothing.
     */
    protected repeatControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    protected number(value: string): number | null;
    /**
     * The endpoint takes the placement key as its id and writes the row if there
     * is not one yet, so there is nothing to create here.
     *
     * `exists` has to be set by hand, and that is the whole reason first-time
     * configuration never worked. `createRecord` leaves it false, `pushData` sets
     * the id but not `exists`, and core's `Model.save()` chooses its method from
     * `exists` while building the URL from the id -- so a slot with no row yet
     * was sent as `POST /placement-settings/{key}`. That is not a route: the
     * resource registers Show, Index and Update and deliberately no Create,
     * because an administrator configures a slot and never invents one. The
     * request failed, the `catch` below swallowed it, and the switch sprang back
     * with nothing said.
     *
     * Saying the record exists is the truthful thing to say here rather than a
     * trick: every placement key is addressable whether or not a row has been
     * written for it, which is exactly what `PlacementSettingResource::find()`
     * implements.
     */
    protected save(slot: SlotConfig, data: Record<string, unknown>): void;
}
