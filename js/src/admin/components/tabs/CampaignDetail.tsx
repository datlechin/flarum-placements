import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import { RESOURCE, TABS, tabRoute, tierKey, toggledStatus, trans } from '../../config';
import type Campaign from '../../models/Campaign';
import type Creative from '../../models/Creative';
import CampaignModal from '../CampaignModal';
import CreativeModal from '../CreativeModal';
import CreativePreviewModal from '../CreativePreviewModal';
import DiagnoseModal from '../DiagnoseModal';
import Figures, { rate } from '../Figures';
import RecordsTable from '../RecordsTable';
import type { Column } from '../RecordsTable';
import StatusPill, { campaignTone, creativeTone } from '../../../common/components/StatusPill';

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
  protected campaign: Campaign | null = null;

  protected missing: boolean = false;

  oninit(vnode: Mithril.Vnode<CampaignDetailAttrs, this>) {
    super.oninit(vnode);

    this.find();

    app.placements.creativesOf(this.attrs.campaignId);
  }

  /**
   * The campaign, from the store if the list has already been read and from the
   * API if this page was opened by following a link.
   */
  protected find(): void {
    const existing = app.store.getById<Campaign>(RESOURCE.campaigns, this.attrs.campaignId);

    if (existing) {
      this.campaign = existing;

      return;
    }

    app.store
      .find<Campaign>(RESOURCE.campaigns, this.attrs.campaignId, { include: 'advertiser' })
      .then((campaign) => {
        this.campaign = campaign;
        m.redraw();
      })
      .catch(() => {
        // A link to a campaign somebody has since deleted. Saying so beats an
        // endless spinner.
        this.missing = true;
        m.redraw();
      });
  }

  view(): Mithril.Children {
    if (this.missing) {
      return (
        <div className="PlacementsTab">
          {this.backLink()}
          <Placeholder text={trans('campaigns.gone')} />
        </div>
      );
    }

    if (!this.campaign) return <LoadingIndicator />;

    const campaign = this.campaign;

    return (
      <div className="PlacementsTab PlacementDetail">
        {this.backLink()}

        <div className="PlacementDetail-header">
          <h3 className="PlacementDetail-title">{campaign.name()}</h3>

          <div className="PlacementDetail-pills">
            <StatusPill tone={campaignTone(campaign.status(), campaign.isLive())}>{trans(`campaigns.statuses.${campaign.status()}`)}</StatusPill>
            <StatusPill tone="neutral">{trans(`campaigns.tiers.${tierKey(campaign.tier())}`)}</StatusPill>
            {campaign.isHouse() && <StatusPill tone="neutral">{trans('campaigns.house')}</StatusPill>}
            {campaign.isMemberSubmitted() && (
              <StatusPill tone="neutral" icon="fas fa-user">
                {trans('campaigns.member_submitted')}
              </StatusPill>
            )}
          </div>

          <div className="PlacementDetail-actions">
            <Button
              className="Button"
              icon="fas fa-pencil-alt"
              onclick={() =>
                app.modal.show(CampaignModal, {
                  campaign,
                  onsaved: () => {
                    app.placements.campaigns.reload();
                    m.redraw();
                  },
                })
              }
            >
              {trans('campaigns.edit')}
            </Button>
            {this.pauseButton(campaign)}
            <Button className="Button Button--danger" icon="fas fa-trash-alt" onclick={() => this.remove(campaign)}>
              {trans('campaigns.delete')}
            </Button>
          </div>
        </div>

        {!campaign.isLive() && campaign.status() === 'active' && (
          <p className="helpText PlacementDetail-note">{trans('campaigns.not_running_help')}</p>
        )}

        <Figures
          figures={[
            { label: trans('reports.impressions'), value: campaign.impressions().toLocaleString() },
            { label: trans('reports.clicks'), value: campaign.clicks().toLocaleString() },
            { label: trans('reports.ctr'), value: rate(campaign.clicks(), campaign.impressions()), help: trans('reports.ctr_help') },
          ]}
        />

        {this.summary(campaign)}

        <div className="PlacementDetail-section">
          <div className="PlacementDetail-sectionHeader">
            <h4>{trans('creatives.title')}</h4>
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.create(campaign)}>
              {trans('creatives.create')}
            </Button>
          </div>

          <RecordsTable<Creative> state={app.placements.creatives} columns={this.creativeColumns(campaign)} empty={trans('creatives.none')} />
        </div>
      </div>
    );
  }

  protected pauseButton(campaign: Campaign): Mithril.Children {
    const next = toggledStatus(campaign.status());

    if (next === null) return null;

    const pausing = next === 'paused';

    return (
      <Button
        className="Button"
        icon={pausing ? 'fas fa-pause' : 'fas fa-play'}
        onclick={() =>
          campaign.save({ status: next }).then(() => {
            app.placements.campaigns.reload();
            m.redraw();
          })
        }
      >
        {trans(pausing ? 'campaigns.pause' : 'campaigns.resume')}
      </Button>
    );
  }

  protected backLink(): Mithril.Children {
    return (
      <Link href={tabRoute(TABS.campaigns)} className="Button Button--link PlacementDetail-back">
        {trans('campaigns.back')}
      </Link>
    );
  }

  /**
   * What was agreed, and when it runs.
   *
   * Each row is shown only when something has been entered, because a forum
   * running its own house adverts has no use for a column of empty contract
   * fields.
   */
  protected summary(campaign: Campaign): Mithril.Children {
    const rows = new ItemList<{ label: Mithril.Children; value: Mithril.Children }>();

    const starts = campaign.startsAt();
    const ends = campaign.endsAt();

    if (starts || ends) {
      rows.add('flight', {
        label: trans('campaigns.flight_label'),
        value: [starts ? humanTime(starts) : '…', ' – ', ends ? humanTime(ends) : '…'],
      });
    }

    // `hasOne` answers `false`, not undefined, when the relationship was not
    // loaded, so this is bound rather than optionally chained.
    const advertiser = campaign.advertiser();

    if (advertiser) {
      rows.add('advertiser', {
        label: trans('campaigns.advertiser_label'),
        value: <Link href={tabRoute(TABS.campaigns, { advertiser: String(advertiser.id()) })}>{advertiser.name()}</Link>,
      });
    }

    if (campaign.maxImpressions()) {
      rows.add('maxImpressions', { label: trans('campaigns.max_impressions'), value: campaign.maxImpressions()!.toLocaleString() });
    }

    if (campaign.maxClicks()) {
      rows.add('maxClicks', { label: trans('campaigns.max_clicks'), value: campaign.maxClicks()!.toLocaleString() });
    }

    if (campaign.rateType() || campaign.rateAmount()) {
      rows.add('rate', {
        label: trans('campaigns.rate_label'),
        value: [
          campaign.rateAmount(),
          campaign.rateCurrency() ? ` ${campaign.rateCurrency()}` : null,
          campaign.rateType() ? ` · ${extractText(trans(`campaigns.rate_types.${campaign.rateType()}`))}` : null,
        ],
      });
    }

    if (campaign.contractNotes()) {
      rows.add('contractNotes', { label: trans('campaigns.contract_notes'), value: campaign.contractNotes() });
    }

    const items = rows.toArray();

    if (!items.length) return null;

    return <dl className="PlacementDetail-summary">{items.map((row) => [<dt>{row.label}</dt>, <dd>{row.value}</dd>])}</dl>;
  }

  protected creativeColumns(campaign: Campaign): ItemList<Column<Creative>> {
    const items = new ItemList<Column<Creative>>();

    items.add(
      'name',
      {
        label: trans('creatives.name_label'),
        sort: 'name',
        content: (creative) => <span className="PlacementTable-strong">{creative.name()}</span>,
      },
      100
    );

    items.add(
      'type',
      {
        label: trans('creatives.type_label'),
        content: (creative) => <code className="PlacementTable-code">{creative.type()}</code>,
      },
      90
    );

    items.add(
      'status',
      {
        label: trans('creatives.status_label'),
        content: (creative) => <StatusPill tone={creativeTone(creative.status())}>{trans(`creatives.statuses.${creative.status()}`)}</StatusPill>,
      },
      80
    );

    items.add(
      'placements',
      {
        label: trans('creatives.slots_label'),
        content: (creative) => {
          const count = Object.keys(creative.placements() ?? {}).length;

          // An unassigned creative can never be served, which is the single
          // most common reason for "my advert is not showing".
          return count ? count : <span className="PlacementTable-warning">{trans('creatives.unassigned')}</span>;
        },
      },
      70
    );

    items.add(
      'impressions',
      {
        label: trans('reports.impressions'),
        sort: 'impressions',
        className: 'PlacementTable-number',
        content: (creative) => creative.impressions().toLocaleString(),
      },
      60
    );

    items.add(
      'clicks',
      {
        label: trans('reports.clicks'),
        sort: 'clicks',
        className: 'PlacementTable-number',
        content: (creative) => creative.clicks().toLocaleString(),
      },
      50
    );

    items.add(
      'controls',
      {
        label: <span className="visually-hidden">{trans('lists.actions')}</span>,
        className: 'PlacementTable-controls',
        content: (creative) => [
          <Button
            className="Button Button--icon PlacementTable-controls-item"
            icon="fas fa-eye"
            aria-label={extractText(trans('creatives.preview'))}
            onclick={() => app.modal.show(CreativePreviewModal, { creative })}
          />,
          <Button
            className="Button Button--icon PlacementTable-controls-item"
            icon="fas fa-stethoscope"
            aria-label={extractText(trans('diagnose.title', { name: creative.name() }))}
            onclick={() => app.modal.show(DiagnoseModal, { creative })}
          />,
          <Button
            className="Button Button--icon PlacementTable-controls-item"
            icon="fas fa-pencil-alt"
            aria-label={extractText(trans('creatives.edit'))}
            onclick={() => this.edit(campaign, creative)}
          />,
          <Button
            className="Button Button--icon PlacementTable-controls-item"
            icon="fas fa-trash-alt"
            aria-label={extractText(trans('creatives.delete'))}
            onclick={() => this.removeCreative(creative)}
          />,
        ],
      },
      -10
    );

    return items;
  }

  protected create(campaign: Campaign): void {
    app.modal.show(CreativeModal, { campaign, onsaved: () => this.reloadCreatives() });
  }

  protected edit(campaign: Campaign, creative: Creative): void {
    app.modal.show(CreativeModal, { campaign, creative, onsaved: () => this.reloadCreatives() });
  }

  protected reloadCreatives(): void {
    app.placements.creatives.reload();
    app.placements.countPending();
  }

  /**
   * Deleting a creative also drops its slot assignments and clears it from any
   * slot using it as a passback -- the model does that -- so the confirmation
   * says so rather than asking a bare "are you sure?".
   */
  protected removeCreative(creative: Creative): void {
    if (!confirm(extractText(trans('creatives.delete_confirm', { name: creative.name() })))) return;

    creative.delete().then(() => this.reloadCreatives());
  }

  protected remove(campaign: Campaign): void {
    if (!confirm(extractText(trans('campaigns.delete_confirm', { name: campaign.name() })))) return;

    campaign.delete().then(() => {
      app.placements.campaigns.reload();

      // Back to the list: staying would leave the page pointed at a campaign
      // that no longer exists.
      m.route.set(tabRoute(TABS.campaigns));
    });
  }
}
