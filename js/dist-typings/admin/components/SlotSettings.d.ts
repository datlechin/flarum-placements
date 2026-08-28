import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { SlotConfig } from '../../common/types';
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
    protected loading: boolean;
    protected saving: string | null;
    oninit(vnode: Mithril.Vnode<SlotSettingsAttrs, this>): void;
    view(): Mithril.Children;
    protected row(slot: SlotConfig): Mithril.Children;
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
