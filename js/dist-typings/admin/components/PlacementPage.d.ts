import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Campaign from '../models/Campaign';
import type Advertiser from '../models/Advertiser';
/**
 * Where advertising is managed.
 *
 * A custom page rather than a settings list, because campaigns are records
 * rather than settings — and because putting them in the settings table would
 * bounce every queue worker on the forum each time one was saved.
 */
export default class PlacementPage extends ExtensionPage<ExtensionPageAttrs> {
    protected campaigns: Campaign[] | null;
    protected advertisers: Advertiser[] | null;
    protected expanded: string | null;
    oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>): void;
    protected load(): void;
    content(): JSX.Element;
    sections(): ItemList<Mithril.Children>;
    /**
     * The first thing on the page, because it answers the two questions this
     * extension will otherwise be asked forever: where are the slots, and why is
     * my advert not showing.
     */
    protected demoSection(): Mithril.Children;
    protected campaignSection(): Mithril.Children;
    protected campaignList(): Mithril.Children;
    protected campaignRow(campaign: Campaign): Mithril.Children;
    /**
     * The status an administrator set, and — when they disagree — the fact that
     * the campaign is not actually running.
     *
     * They part ways whenever a flight has ended or a cap has been reached, and
     * that gap is exactly what somebody staring at a campaign marked "active"
     * needs told.
     */
    protected statusBadge(campaign: Campaign): Mithril.Children;
    protected flight(campaign: Campaign): Mithril.Children;
    protected creativeList(campaign: Campaign): Mithril.Children;
    /**
     * Who campaigns are reported to.
     *
     * Optional throughout: a forum running only its own house adverts never
     * opens this list.
     */
    protected advertiserSection(): Mithril.Children;
    /**
     * Every slot this forum has, and what may be changed about each one.
     */
    protected slotSection(): Mithril.Children;
    /**
     * The two things that really are settings. Everything else is a record.
     */
    protected settingsSection(): Mithril.Children;
    protected remove(campaign: Campaign): void;
}
