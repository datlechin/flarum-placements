import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

import { RESOURCE, advertisers, trans } from '../config';
import type Campaign from '../models/Campaign';
import type { TargetingRule } from '../models/Campaign';
import DaypartGrid from './DaypartGrid';
import RulesEditor from './RulesEditor';

export interface CampaignModalAttrs extends IFormModalAttrs {
  campaign?: Campaign;
  onsaved?: () => void;
}

export default class CampaignModal extends FormModal<CampaignModalAttrs> {
  protected name!: Stream<string>;
  protected status!: Stream<string>;
  protected tier!: Stream<string>;
  protected isHouse!: Stream<boolean>;
  protected startsAt!: Stream<string>;
  protected endsAt!: Stream<string>;
  protected maxImpressions!: Stream<string>;
  protected maxClicks!: Stream<string>;
  protected pacing!: Stream<string>;
  protected rules!: Stream<TargetingRule[]>;
  protected daypartMask!: Stream<string | null>;
  protected frequencyCap!: Stream<string>;
  protected frequencyWindow!: Stream<string>;
  protected advertiserId!: Stream<string>;

  oninit(vnode: Mithril.Vnode<CampaignModalAttrs, this>) {
    super.oninit(vnode);

    const campaign = this.attrs.campaign;

    this.name = Stream(campaign?.name() ?? '');
    this.status = Stream(campaign?.status() ?? 'draft');
    this.tier = Stream(String(campaign?.tier() ?? 50));
    this.isHouse = Stream(campaign?.isHouse() ?? false);
    this.startsAt = Stream(this.date(campaign?.startsAt()));
    this.endsAt = Stream(this.date(campaign?.endsAt()));
    this.maxImpressions = Stream(campaign?.maxImpressions()?.toString() ?? '');
    this.maxClicks = Stream(campaign?.maxClicks()?.toString() ?? '');
    this.pacing = Stream(campaign?.pacing() ?? 'asap');
    this.rules = Stream(campaign?.rules() ?? []);
    this.daypartMask = Stream(campaign?.daypartMask() ?? null);
    this.frequencyCap = Stream(campaign?.frequencyCap()?.toString() ?? '');
    this.frequencyWindow = Stream(campaign?.frequencyWindow() ?? 'day');
    // `hasOne` returns `false` when the relationship was not loaded, which is
    // not the same as there being no advertiser.
    const advertiser = campaign?.advertiser();
    this.advertiserId = Stream(advertiser ? String(advertiser.id()) : '');
  }

  className(): string {
    return 'CampaignModal Modal--medium';
  }

  title(): Mithril.Children {
    return this.attrs.campaign ? trans('campaigns.edit') : trans('campaigns.create');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.field('name', <input className="FormControl" bidi={this.name} required />)}

          {this.field(
            'advertiser',
            <Select
              value={this.advertiserId()}
              options={{
                '': String(trans('campaigns.no_advertiser')),
                ...Object.fromEntries(advertisers().map((a) => [String(a.id()), a.name()])),
              }}
              onchange={this.advertiserId}
            />,
            trans('campaigns.advertiser_help')
          )}

          {this.field(
            'status',
            <Select
              value={this.status()}
              options={Object.fromEntries(['draft', 'scheduled', 'active', 'paused', 'archived'].map((s) => [s, trans(`campaigns.statuses.${s}`)]))}
              onchange={this.status}
            />,
            trans('campaigns.status_help')
          )}

          {this.field(
            'tier',
            <Select
              value={this.tier()}
              options={Object.fromEntries(
                [
                  ['10', 'sponsorship'],
                  ['30', 'guaranteed'],
                  ['50', 'standard'],
                  ['70', 'remnant'],
                  ['90', 'house'],
                ].map(([value, key]) => [value, trans(`campaigns.tiers.${key}`)])
              )}
              onchange={this.tier}
            />,
            // Said plainly because the industry name misleads: "guaranteed"
            // here is a priority, not a forecast, and nothing in this
            // extension predicts inventory.
            trans('campaigns.tier_help')
          )}

          <div className="Form-group">
            <Switch state={this.isHouse()} onchange={this.isHouse}>
              {trans('campaigns.is_house')}
            </Switch>
            <div className="helpText">{trans('campaigns.is_house_help')}</div>
          </div>

          <div className="Form-group PlacementFlight">
            {this.field('starts_at', <input className="FormControl" type="datetime-local" bidi={this.startsAt} />)}
            {this.field('ends_at', <input className="FormControl" type="datetime-local" bidi={this.endsAt} />, trans('campaigns.ends_at_help'))}
          </div>

          {this.field('max_impressions', <input className="FormControl" type="number" min="1" bidi={this.maxImpressions} />)}
          {this.field('max_clicks', <input className="FormControl" type="number" min="1" bidi={this.maxClicks} />)}

          {this.field(
            'pacing',
            <Select
              value={this.pacing()}
              options={{ asap: trans('campaigns.pacing_asap'), even: trans('campaigns.pacing_even') }}
              onchange={this.pacing}
            />,
            trans('campaigns.pacing_help')
          )}

          <div className="Form-group">
            <label>{trans('campaigns.frequency_cap')}</label>
            <div className="PlacementFrequency">
              <input className="FormControl" type="number" min="1" bidi={this.frequencyCap} />
              <Select
                value={this.frequencyWindow()}
                options={Object.fromEntries(['session', 'hour', 'day'].map((w) => [w, trans(`campaigns.frequency_windows.${w}`)]))}
                onchange={this.frequencyWindow}
              />
            </div>
            <div className="helpText">{trans('campaigns.frequency_cap_help')}</div>
          </div>

          <div className="Form-group">
            <label>{trans('daypart.label')}</label>
            <DaypartGrid mask={this.daypartMask()} onchange={this.daypartMask} />
          </div>

          <div className="Form-group">
            <label>{trans('rules.label')}</label>
            <RulesEditor rules={this.rules()} onchange={this.rules} />
          </div>

          <div className="Form-group">
            <Button type="submit" className="Button Button--primary" loading={this.loading}>
              {trans('save')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  protected field(key: string, control: Mithril.Children, help?: Mithril.Children): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{trans(`campaigns.${key}`)}</label>
        {control}
        {help && <div className="helpText">{help}</div>}
      </div>
    );
  }

  /**
   * `datetime-local` wants `YYYY-MM-DDTHH:mm` in the browser's own zone, while
   * the API speaks ISO 8601. The conversion is here rather than in the model
   * because it is a property of the input element, not of a campaign.
   */
  protected date(value: Date | null | undefined): string {
    if (!value) return '';

    const local = new Date(value.getTime() - value.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
  }

  protected iso(value: string): string | null {
    return value ? new Date(value).toISOString() : null;
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();

    this.loading = true;

    const data = {
      name: this.name(),
      status: this.status(),
      tier: Number(this.tier()),
      isHouse: this.isHouse(),
      startsAt: this.iso(this.startsAt()),
      endsAt: this.iso(this.endsAt()),
      maxImpressions: this.maxImpressions() ? Number(this.maxImpressions()) : null,
      maxClicks: this.maxClicks() ? Number(this.maxClicks()) : null,
      pacing: this.pacing(),
      daypartMask: this.daypartMask(),
      frequencyCap: this.frequencyCap() ? Number(this.frequencyCap()) : null,
      frequencyWindow: this.frequencyWindow(),
      rules: this.rules(),
      relationships: {
        // `getById` returns undefined for an id the store has not seen; the
        // save contract wants a model or an explicit null.
        advertiser: (this.advertiserId() ? app.store.getById(RESOURCE.advertisers, this.advertiserId()) : null) ?? null,
      },
    };

    const record = this.attrs.campaign ?? app.store.createRecord(RESOURCE.campaigns);

    record
      .save(data)
      .then(() => {
        this.attrs.onsaved?.();
        this.hide();
      })
      .catch(() => {
        // The modal stays open with its alert showing, so a validation error
        // is corrected in place rather than retyped.
        this.loading = false;
        m.redraw();
      });
  }
}
