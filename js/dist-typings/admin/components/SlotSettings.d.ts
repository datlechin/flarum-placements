import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { SlotConfig } from '../../common/types';
import type Creative from '../models/Creative';
import type PlacementSetting from '../models/PlacementSetting';
export interface SlotSettingsAttrs extends ComponentAttrs {
}
/**
 * Every slot this forum has, and what an administrator may change about it.
 *
 * Slots are declared in code, so this list is not editable in the sense of
 * adding to it — what is editable is whether each one is used and how. A slot
 * with no row is not unconfigured, it is default-configured, which is why
 * saving writes a row rather than the client creating one up front.
 */
export default class SlotSettings extends Component<SlotSettingsAttrs> {
    protected settings: Record<string, PlacementSetting>;
    /** Approved creatives, for the passback picker. */
    protected creatives: Creative[] | null;
    protected loading: boolean;
    protected saving: string | null;
    oninit(vnode: Mithril.Vnode<SlotSettingsAttrs, this>): void;
    view(): Mithril.Children;
    protected row(slot: SlotConfig): Mithril.Children;
    /**
     * How much this slot holds and what it says about itself.
     *
     * Both were writable through the API and honoured at serve time with no
     * control anywhere, which is the same as not having them.
     */
    protected deliveryControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    /**
     * What the slot does when nothing matched.
     *
     * Every mode was writable through the API and none of them did anything:
     * the client took the best tier and ignored the setting entirely, so all
     * four behaved as `next_tier`.
     */
    protected fallbackControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    /**
     * The height held open while a creative loads.
     *
     * Per breakpoint, because a slot that is a leaderboard on a desktop is
     * usually something much shorter on a phone, and reserving the desktop
     * height everywhere pushes the page down on the readers who can least
     * afford it. Blank means reserve nothing.
     */
    protected reserveControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children;
    /**
     * Whether the slot draws again on every page, or keeps what it drew.
     */
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
     */
    protected save(slot: SlotConfig, data: Record<string, unknown>): void;
}
