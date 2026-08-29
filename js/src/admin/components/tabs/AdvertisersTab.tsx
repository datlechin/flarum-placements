import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import { TABS, tabRoute, trans } from '../../config';
import type Advertiser from '../../models/Advertiser';
import AdvertiserModal from '../AdvertiserModal';
import ListToolbar from '../ListToolbar';
import RecordsTable from '../RecordsTable';
import type { Column } from '../RecordsTable';
import StatusPill from '../../../common/components/StatusPill';

/**
 * Who campaigns are reported to.
 *
 * Optional throughout: a forum running only its own house adverts never opens
 * this tab. It exists mainly to own the report link, which is what lets an
 * advertiser check their delivery without an account on the forum.
 */
export default class AdvertisersTab extends Component {
  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    app.placements.advertisers.ensureLoaded();
  }

  view(): Mithril.Children {
    return (
      <div className="PlacementsTab">
        <p className="helpText">{trans('advertisers.help')}</p>

        <ListToolbar
          state={app.placements.advertisers}
          searchLabel={trans('advertisers.search')}
          actions={
            <Button
              className="Button Button--primary"
              icon="fas fa-plus"
              onclick={() => app.modal.show(AdvertiserModal, { onsaved: () => app.placements.advertisers.reload() })}
            >
              {trans('advertisers.create')}
            </Button>
          }
        />

        <RecordsTable<Advertiser> state={app.placements.advertisers} columns={this.columns()} empty={trans('advertisers.none')} />
      </div>
    );
  }

  columns(): ItemList<Column<Advertiser>> {
    const items = new ItemList<Column<Advertiser>>();

    items.add(
      'name',
      {
        label: trans('advertisers.name_label'),
        sort: 'name',
        content: (advertiser) => {
          // `hasOne` answers `false`, not undefined, when the relationship was
          // not loaded, so this is bound rather than optionally chained.
          const user = advertiser.user();

          return (
            <div className="PlacementTable-primary">
              <span className="PlacementTable-strong">{advertiser.name()}</span>
              {user && (
                <StatusPill tone="neutral" icon="fas fa-user">
                  {user.username()}
                </StatusPill>
              )}
            </div>
          );
        },
      },
      100
    );

    items.add(
      'contact',
      {
        label: trans('advertisers.contact_label'),
        content: (advertiser) => advertiser.contactEmail() ?? <span className="PlacementTable-muted">{trans('advertisers.no_contact')}</span>,
      },
      90
    );

    items.add(
      'link',
      {
        label: trans('advertisers.link_label'),
        content: (advertiser) => this.reportLink(advertiser),
      },
      80
    );

    items.add(
      'campaigns',
      {
        label: <span className="visually-hidden">{trans('advertisers.campaigns_label')}</span>,
        content: (advertiser) => (
          <Link href={tabRoute(TABS.campaigns, { advertiser: String(advertiser.id()) })} className="Button Button--text">
            {trans('advertisers.view_campaigns')}
          </Link>
        ),
      },
      70
    );

    items.add(
      'controls',
      {
        label: <span className="visually-hidden">{trans('lists.actions')}</span>,
        className: 'Table-controls',
        content: (advertiser) => [
          <Button
            className="Button Button--icon Table-controls-item"
            icon="fas fa-pencil-alt"
            aria-label={extractText(trans('advertisers.edit'))}
            onclick={() => app.modal.show(AdvertiserModal, { advertiser, onsaved: () => app.placements.advertisers.reload() })}
          />,
          <Button
            className="Button Button--icon Table-controls-item"
            icon="fas fa-trash-alt"
            aria-label={extractText(trans('advertisers.delete'))}
            onclick={() => this.remove(advertiser)}
          />,
        ],
      },
      -10
    );

    return items;
  }

  protected reportLink(advertiser: Advertiser): Mithril.Children {
    if (!advertiser.hasReportToken()) {
      return <span className="PlacementTable-muted">{trans('advertisers.no_link')}</span>;
    }

    const expires = advertiser.reportTokenExpiresAt();

    return (
      <div className="PlacementTable-primary">
        <StatusPill tone="success" icon="fas fa-link">
          {trans('advertisers.has_link')}
        </StatusPill>
        {expires && <span className="PlacementTable-muted">{humanTime(expires)}</span>}
      </div>
    );
  }

  /**
   * Campaigns are detached rather than removed -- the model does that -- and
   * the confirmation says so, because "delete this advertiser?" does not tell
   * somebody whether they are about to lose a year of campaigns with it.
   */
  protected remove(advertiser: Advertiser): void {
    if (!confirm(extractText(trans('advertisers.delete_confirm', { name: advertiser.name() })))) return;

    advertiser.delete().then(() => {
      app.placements.advertisers.reload();
      // The campaign rows carry the advertiser's name, and they no longer do.
      app.placements.campaigns.reload();
    });
  }
}
