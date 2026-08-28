import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import ItemList from 'flarum/common/utils/ItemList';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { RESOURCE, trans } from '../config';
import type Campaign from '../models/Campaign';
import type Advertiser from '../models/Advertiser';
import type Creative from '../models/Creative';
import CampaignModal from './CampaignModal';
import AdvertiserModal from './AdvertiserModal';
import CreativeModal from './CreativeModal';
import ReportSection from './ReportSection';
import SlotSettings from './SlotSettings';

/**
 * Where advertising is managed.
 *
 * A custom page rather than a settings list, because campaigns are records
 * rather than settings — and because putting them in the settings table would
 * bounce every queue worker on the forum each time one was saved.
 */
export default class PlacementPage extends ExtensionPage<ExtensionPageAttrs> {
  protected campaigns: Campaign[] | null = null;
  protected advertisers: Advertiser[] | null = null;
  protected expanded: string | null = null;

  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);

    this.load();
  }

  protected load(): void {
    app.store.find<Campaign[]>(RESOURCE.campaigns, { include: 'advertiser,creatives' }).then((campaigns) => {
      this.campaigns = campaigns;
      m.redraw();
    });
  }

  content(): JSX.Element {
    return <div className="ExtensionPage-body">{this.sections().toArray()}</div>;
  }

  sections(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('demo', this.demoSection(), 100);
    items.add('campaigns', this.campaignSection(), 90);
    items.add('reports', <ReportSection />, 85);
    items.add('advertisers', this.advertiserSection(), 82);
    items.add('slots', this.slotSection(), 80);
    items.add('settings', this.settingsSection(), 70);

    return items;
  }

  /**
   * The first thing on the page, because it answers the two questions this
   * extension will otherwise be asked forever: where are the slots, and why is
   * my advert not showing.
   */
  protected demoSection(): Mithril.Children {
    return (
      <section className="PlacementSection container">
        <h2>{trans('demo.title')}</h2>
        <p className="helpText">{trans('demo.help')}</p>
        <Button
          className="Button"
          icon="fas fa-eye"
          onclick={() => window.open(`${app.forum.attribute('baseUrl')}/?placement_demo=1`, '_blank', 'noopener')}
        >
          {trans('demo.open')}
        </Button>
      </section>
    );
  }

  protected campaignSection(): Mithril.Children {
    return (
      <section className="PlacementSection container">
        <div className="PlacementSection-header">
          <h2>{trans('campaigns.title')}</h2>
          <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => app.modal.show(CampaignModal, { onsaved: () => this.load() })}>
            {trans('campaigns.create')}
          </Button>
        </div>

        {this.campaigns === null ? <LoadingIndicator /> : this.campaignList()}
      </section>
    );
  }

  protected campaignList(): Mithril.Children {
    if (!this.campaigns?.length) {
      return <Placeholder text={trans('campaigns.none')} />;
    }

    return <ul className="PlacementList">{this.campaigns.map((campaign) => this.campaignRow(campaign))}</ul>;
  }

  protected campaignRow(campaign: Campaign): Mithril.Children {
    const id = String(campaign.id());
    const open = this.expanded === id;

    return (
      <li className="PlacementList-item" key={id}>
        <div className="PlacementList-row">
          <button className="Button Button--link PlacementList-toggle" onclick={() => (this.expanded = open ? null : id)} aria-expanded={open}>
            {campaign.name()}
          </button>

          {this.statusBadge(campaign)}

          <span className="PlacementList-meta">{this.flight(campaign)}</span>

          <Button
            className="Button Button--icon Button--link"
            icon="fas fa-pencil-alt"
            aria-label={trans('campaigns.edit')}
            onclick={() => app.modal.show(CampaignModal, { campaign, onsaved: () => this.load() })}
          />
          <Button
            className="Button Button--icon Button--link"
            icon="fas fa-trash-alt"
            aria-label={trans('campaigns.delete')}
            onclick={() => this.remove(campaign)}
          />
        </div>

        {open && this.creativeList(campaign)}
      </li>
    );
  }

  /**
   * The status an administrator set, and — when they disagree — the fact that
   * the campaign is not actually running.
   *
   * They part ways whenever a flight has ended or a cap has been reached, and
   * that gap is exactly what somebody staring at a campaign marked "active"
   * needs told.
   */
  protected statusBadge(campaign: Campaign): Mithril.Children {
    const live = campaign.isLive();
    const status = campaign.status();

    return (
      <span className={`PlacementBadge PlacementBadge--${live ? 'live' : 'idle'}`}>
        {trans(`campaigns.statuses.${status}`)}
        {!live && status === 'active' && <span className="PlacementBadge-note">{trans('campaigns.not_running')}</span>}
      </span>
    );
  }

  protected flight(campaign: Campaign): Mithril.Children {
    const starts = campaign.startsAt();
    const ends = campaign.endsAt();

    if (!starts && !ends) return trans('campaigns.always');

    return [starts ? humanTime(starts) : '…', ' – ', ends ? humanTime(ends) : '…'];
  }

  protected creativeList(campaign: Campaign): Mithril.Children {
    const creatives = campaign.creatives() as Creative[] | false | null;

    return (
      <div className="PlacementList-detail">
        <div className="PlacementSection-header">
          <h3>{trans('creatives.title')}</h3>
          <Button
            className="Button Button--link"
            icon="fas fa-plus"
            onclick={() => app.modal.show(CreativeModal, { campaign, onsaved: () => this.load() })}
          >
            {trans('creatives.create')}
          </Button>
        </div>

        {!creatives || !creatives.length ? (
          <Placeholder text={trans('creatives.none')} />
        ) : (
          <ul className="PlacementList PlacementList--nested">
            {creatives.map((creative) => (
              <li className="PlacementList-item" key={creative.id()}>
                <div className="PlacementList-row">
                  <span className="PlacementList-name">{creative.name()}</span>
                  <code className="PlacementList-type">{creative.type()}</code>
                  <span className="PlacementList-meta">{Object.keys(creative.placements() ?? {}).length || trans('creatives.unassigned')}</span>
                  <Button
                    className="Button Button--icon Button--link"
                    icon="fas fa-pencil-alt"
                    aria-label={trans('creatives.edit')}
                    onclick={() => app.modal.show(CreativeModal, { campaign, creative, onsaved: () => this.load() })}
                  />
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    );
  }

  /**
   * Who campaigns are reported to.
   *
   * Optional throughout: a forum running only its own house adverts never
   * opens this list.
   */
  protected advertiserSection(): Mithril.Children {
    return (
      <section className="PlacementSection container">
        <div className="PlacementSection-header">
          <h2>{trans('advertisers.title')}</h2>
          <Button className="Button" icon="fas fa-plus" onclick={() => app.modal.show(AdvertiserModal, { onsaved: () => this.load() })}>
            {trans('advertisers.create')}
          </Button>
        </div>

        <p className="helpText">{trans('advertisers.help')}</p>

        {!this.advertisers?.length ? (
          <Placeholder text={trans('advertisers.none')} />
        ) : (
          <ul className="PlacementList">
            {this.advertisers.map((advertiser) => (
              <li className="PlacementList-item" key={advertiser.id()}>
                <div className="PlacementList-row">
                  <span className="PlacementList-name">{advertiser.name()}</span>
                  {advertiser.hasReportToken() && <span className="PlacementBadge PlacementBadge--live">{trans('advertisers.has_link')}</span>}
                  <span className="PlacementList-meta">{advertiser.contactEmail()}</span>
                  <Button
                    className="Button Button--icon Button--link"
                    icon="fas fa-pencil-alt"
                    aria-label={trans('advertisers.edit')}
                    onclick={() => app.modal.show(AdvertiserModal, { advertiser, onsaved: () => this.load() })}
                  />
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>
    );
  }

  /**
   * Every slot this forum has, and what may be changed about each one.
   */
  protected slotSection(): Mithril.Children {
    return (
      <section className="PlacementSection container">
        <h2>{trans('slots.title')}</h2>
        <p className="helpText">{trans('slots.help')}</p>
        <SlotSettings />
      </section>
    );
  }

  /**
   * The two things that really are settings. Everything else is a record.
   */
  protected settingsSection(): Mithril.Children {
    return (
      <section className="PlacementSection container">
        <h2>{trans('settings.title')}</h2>

        <div className="Form-group">
          <label>{trans('settings.timezone')}</label>
          <input className="FormControl" bidi={this.setting('datlechin-placement.timezone', 'UTC')} placeholder="UTC" />
          <div className="helpText">{trans('settings.timezone_help')}</div>
        </div>

        <div className="Form-group">
          <label>{trans('settings.retention_days')}</label>
          <input className="FormControl" type="number" min="1" max="730" bidi={this.setting('datlechin-placement.retention_days', '90')} />
          <div className="helpText">{trans('settings.retention_days_help')}</div>
        </div>

        <div className="Form-group">
          <label>{trans('settings.ads_txt')}</label>
          <textarea
            className="FormControl"
            rows="5"
            bidi={this.setting('datlechin-placement.ads_txt', '')}
            placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"
          />
          <div className="helpText">{trans('settings.ads_txt_help')}</div>
        </div>

        <div className="Form-group">
          <label>{trans('settings.network_scripts')}</label>
          <textarea
            className="FormControl"
            rows="3"
            bidi={this.setting('datlechin-placement.network_scripts', '')}
            placeholder="https://…/loader.js"
          />
          <div className="helpText">{trans('settings.network_scripts_help')}</div>
        </div>

        {this.submitButton()}
      </section>
    );
  }

  protected remove(campaign: Campaign): void {
    if (!confirm(extractText(trans('campaigns.delete_confirm')))) return;

    campaign.delete().then(() => this.load());
  }
}
