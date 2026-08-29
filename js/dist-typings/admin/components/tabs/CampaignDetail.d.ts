import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Campaign from '../../models/Campaign';
import type Creative from '../../models/Creative';
import type { Column } from '../RecordsTable';
export interface CampaignDetailAttrs extends ComponentAttrs {
    campaignId: string;
}
/**
 * One campaign, and the creatives that belong to it: what it has delivered,
 * what was contracted, and why it is not running.
 *
 * It has its own address, so it can be linked to.
 */
export default class CampaignDetail extends Component<CampaignDetailAttrs> {
    /** Null while a campaign reached by link is still being fetched. */
    protected campaign: Campaign | null;
    protected missing: boolean;
    oninit(vnode: Mithril.Vnode<CampaignDetailAttrs, this>): void;
    /**
     * The campaign, from the store if the list has already been read and from the
     * API if this page was opened by following a link.
     */
    protected find(): void;
    view(): Mithril.Children;
    protected pauseButton(campaign: Campaign): Mithril.Children;
    protected backLink(): Mithril.Children;
    /**
     * What was agreed, and when it runs.
     *
     * Each row is shown only when something has been entered, because a forum
     * running its own house adverts has no use for a column of empty contract
     * fields.
     */
    protected summary(campaign: Campaign): Mithril.Children;
    protected creativeColumns(campaign: Campaign): ItemList<Column<Creative>>;
    protected create(campaign: Campaign): void;
    protected edit(campaign: Campaign, creative: Creative): void;
    protected reloadCreatives(): void;
    /**
     * Deleting a creative also drops its slot assignments and clears it from any
     * slot using it as a passback -- the model does that -- so the confirmation
     * says so rather than asking a bare "are you sure?".
     */
    protected removeCreative(creative: Creative): void;
    protected remove(campaign: Campaign): void;
}
