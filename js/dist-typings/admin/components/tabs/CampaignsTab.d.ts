import Component from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Campaign from '../../models/Campaign';
import type { Column } from '../RecordsTable';
/**
 * The campaign list, and the page of whichever campaign is open.
 *
 * Both live behind the same tab because they are the same task: a campaign is
 * reached from the list and the list is what you go back to.
 */
export default class CampaignsTab extends Component {
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): Mithril.Children;
    /**
     * Says so when the list is showing one advertiser's campaigns, and offers a
     * way out.
     *
     * A filter applied by following a link is one the reader did not set, so
     * without this the list looks like the whole list with most of it missing.
     */
    protected advertiserNotice(): Mithril.Children;
    columns(): ItemList<Column<Campaign>>;
    /**
     * The status somebody set, and -- when they disagree -- the fact that the
     * campaign is not actually running.
     *
     * They part ways whenever a flight has ended or a cap is spent, and that gap
     * is exactly what somebody staring at a campaign marked "active" needs told.
     */
    protected status(campaign: Campaign): Mithril.Children;
    protected flight(campaign: Campaign): Mithril.Children;
}
