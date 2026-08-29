import Component from 'flarum/common/Component';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
import type Advertiser from '../../models/Advertiser';
import type { Column } from '../RecordsTable';
/**
 * Who campaigns are reported to.
 *
 * Optional throughout: a forum running only its own house adverts never opens
 * this tab. It exists mainly to own the report link, which is what lets an
 * advertiser check their delivery without an account on the forum.
 */
export default class AdvertisersTab extends Component {
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): Mithril.Children;
    columns(): ItemList<Column<Advertiser>>;
    protected reportLink(advertiser: Advertiser): Mithril.Children;
    /**
     * The API has always supported deleting an advertiser and the model has
     * always had the cleanup for it -- campaigns are detached rather than
     * removed -- but there was no way to ask for it from the interface.
     *
     * The confirmation says what happens to the campaigns, because "delete this
     * advertiser?" does not tell somebody whether they are about to lose a year
     * of campaigns with it.
     */
    protected remove(advertiser: Advertiser): void;
}
