import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import Tooltip from 'flarum/common/components/Tooltip';
import extractText from 'flarum/common/utils/extractText';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import { CAMPAIGN_STATUS, RESOURCE, TABS, TIERS, currentCampaignId, tabRoute, tierKey, toggledStatus, trans } from '../../config';
import type Advertiser from '../../models/Advertiser';
import type Campaign from '../../models/Campaign';
import CampaignModal from '../CampaignModal';
import CampaignDetail from './CampaignDetail';
import ListToolbar from '../ListToolbar';
import RecordsTable from '../RecordsTable';
import type { Column } from '../RecordsTable';
import StatusPill, { campaignTone } from '../../../common/components/StatusPill';
import flightDate from '../../utils/flightDate';

/**
 * The campaign list, and the page of whichever campaign is open.
 *
 * Both live behind the same tab because they are the same task: a campaign is
 * reached from the list and the list is what you go back to.
 */
export default class CampaignsTab extends Component {
  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    const state = app.placements.campaigns;

    // Followed from an advertiser row: "what is Acme running?". The filter is
    // taken from the address rather than from a click, so the answer is a link
    // somebody can send.
    const advertiser = m.route.param('advertiser') ?? '';

    if (state.currentFilter('advertiser') !== advertiser) {
      state.filterBy('advertiser', advertiser || undefined);
    } else {
      state.ensureLoaded();
    }
  }

  view(): Mithril.Children {
    const open = currentCampaignId();

    if (open) return <CampaignDetail campaignId={open} />;

    return (
      <div className="PlacementsTab">
        {this.advertiserNotice()}

        <ListToolbar
          state={app.placements.campaigns}
          searchLabel={trans('campaigns.search')}
          choices={[
            {
              key: 'status',
              label: trans('campaigns.status_label'),
              options: Object.fromEntries(
                Object.values(CAMPAIGN_STATUS).map((status) => [status, extractText(trans(`campaigns.statuses.${status}`))])
              ),
            },
            {
              key: 'tier',
              label: trans('campaigns.tier_label'),
              options: Object.fromEntries(TIERS.map((tier) => [String(tier.value), extractText(trans(`campaigns.tiers.${tier.key}`))])),
            },
            {
              // A member submitting an advert has a campaign provisioned for
              // them, named after them, which then sits among the sold ones.
              key: 'source',
              label: trans('campaigns.source_label'),
              options: {
                direct: extractText(trans('campaigns.sources.direct')),
                member: extractText(trans('campaigns.sources.member')),
              },
            },
          ]}
          actions={
            <Button
              className="Button Button--primary"
              icon="fas fa-plus"
              onclick={() => app.modal.show(CampaignModal, { onsaved: () => app.placements.campaigns.reload() })}
            >
              {trans('campaigns.create')}
            </Button>
          }
        />

        <RecordsTable<Campaign> state={app.placements.campaigns} columns={this.columns()} empty={trans('campaigns.none')} />
      </div>
    );
  }

  /**
   * Says so when the list is showing one advertiser's campaigns, and offers a
   * way out.
   *
   * A filter applied by following a link is one the reader did not set, so
   * without this the list looks like the whole list with most of it missing.
   */
  protected advertiserNotice(): Mithril.Children {
    const id = m.route.param('advertiser');

    if (!id) return null;

    const advertiser = app.store.getById<Advertiser>(RESOURCE.advertisers, id);

    return (
      <div className="PlacementNotice">
        <span>{trans('campaigns.filtered_to_advertiser', { name: advertiser?.name() ?? id })}</span>
        <Link href={tabRoute(TABS.campaigns)} className="Button Button--text">
          {trans('campaigns.show_all')}
        </Link>
      </div>
    );
  }

  columns(): ItemList<Column<Campaign>> {
    const items = new ItemList<Column<Campaign>>();

    items.add(
      'name',
      {
        label: trans('campaigns.name_label'),
        sort: 'name',
        content: (campaign) => (
          <div className="PlacementTable-primary">
            <Link href={tabRoute(TABS.campaigns, { campaign: String(campaign.id()) })} className="PlacementTable-link">
              {campaign.name()}
            </Link>
            {campaign.isMemberSubmitted() && (
              <Tooltip text={extractText(trans('campaigns.member_submitted_help'))}>
                <span>
                  <StatusPill tone="neutral" icon="fas fa-user">
                    {trans('campaigns.member_submitted')}
                  </StatusPill>
                </span>
              </Tooltip>
            )}
          </div>
        ),
      },
      100
    );

    items.add(
      'advertiser',
      {
        label: trans('campaigns.advertiser_label'),
        content: (campaign) => {
          const advertiser = campaign.advertiser();

          return advertiser ? advertiser.name() : <span className="PlacementTable-muted">{trans('campaigns.no_advertiser')}</span>;
        },
      },
      90
    );

    items.add(
      'status',
      {
        label: trans('campaigns.status_label'),
        content: (campaign) => this.status(campaign),
      },
      80
    );

    items.add(
      'tier',
      {
        label: trans('campaigns.tier_label'),
        sort: 'tier',
        content: (campaign) => trans(`campaigns.tiers.${tierKey(campaign.tier())}`),
      },
      70
    );

    items.add(
      'flight',
      {
        label: trans('campaigns.flight_label'),
        sort: 'startsAt',
        content: (campaign) => this.flight(campaign),
      },
      60
    );

    items.add(
      'impressions',
      {
        label: trans('campaigns.impressions_label'),
        sort: 'impressions',
        className: 'PlacementTable-number',
        content: (campaign) => campaign.impressions().toLocaleString(),
      },
      50
    );

    items.add(
      'clicks',
      {
        label: trans('campaigns.clicks_label'),
        sort: 'clicks',
        className: 'PlacementTable-number',
        content: (campaign) => campaign.clicks().toLocaleString(),
      },
      40
    );

    items.add(
      'controls',
      {
        label: <span className="visually-hidden">{trans('lists.actions')}</span>,
        className: 'PlacementTable-controls',
        content: (campaign) => [
          this.pauseButton(campaign),
          <Button
            className="Button Button--icon PlacementTable-controls-item"
            icon="fas fa-pencil-alt"
            aria-label={extractText(trans('campaigns.edit'))}
            onclick={() => app.modal.show(CampaignModal, { campaign, onsaved: () => app.placements.campaigns.reload() })}
          />,
        ],
      },
      -10
    );

    return items;
  }

  /**
   * Pause a running campaign, or start a paused one, from the row.
   */
  protected pauseButton(campaign: Campaign): Mithril.Children {
    const next = toggledStatus(campaign.status());

    if (next === null) return null;

    const pausing = next === CAMPAIGN_STATUS.paused;

    return (
      <Button
        className="Button Button--icon PlacementTable-controls-item"
        icon={pausing ? 'fas fa-pause' : 'fas fa-play'}
        aria-label={extractText(trans(pausing ? 'campaigns.pause' : 'campaigns.resume'))}
        onclick={() => campaign.save({ status: next }).then(() => app.placements.campaigns.reload())}
      />
    );
  }

  /**
   * The status somebody set, and -- when they disagree -- the fact that the
   * campaign is not actually running.
   *
   * They part ways whenever a flight has ended or a cap is spent, and that gap
   * is exactly what somebody staring at a campaign marked "active" needs told.
   */
  protected status(campaign: Campaign): Mithril.Children {
    const live = campaign.isLive();
    const status = campaign.status();
    const stalled = !live && status === CAMPAIGN_STATUS.active;

    const pill = <StatusPill tone={campaignTone(status, live)}>{trans(`campaigns.statuses.${status}`)}</StatusPill>;

    if (!stalled) return pill;

    return (
      <div className="PlacementTable-primary">
        {pill}
        <Tooltip text={extractText(trans('campaigns.not_running_help'))}>
          <span>
            <StatusPill tone="warning" icon="fas fa-exclamation-triangle">
              {trans('campaigns.not_running')}
            </StatusPill>
          </span>
        </Tooltip>
      </div>
    );
  }

  protected flight(campaign: Campaign): Mithril.Children {
    const starts = campaign.startsAt();
    const ends = campaign.endsAt();

    if (!starts && !ends) return <span className="PlacementTable-muted">{trans('campaigns.always')}</span>;

    return [starts ? flightDate(starts) : '…', ' – ', ends ? flightDate(ends) : '…'];
  }
}
