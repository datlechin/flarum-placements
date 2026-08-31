import app from 'flarum/admin/app';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import ItemList from 'flarum/common/utils/ItemList';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { CAMPAIGN_STATUS, RESOURCE, TIERS, trans } from '../config';
import type Advertiser from '../models/Advertiser';
import type Campaign from '../models/Campaign';
import type { TargetingRule } from '../models/Campaign';
import DaypartGrid from './DaypartGrid';
import RulesEditor from './RulesEditor';
import TabbedFormModal from './TabbedFormModal';
import type { ModalTab } from './TabbedFormModal';

export interface CampaignModalAttrs extends IFormModalAttrs {
  campaign?: Campaign;
  onsaved?: () => void;
}

/** The rate arrangements the form offers. Nothing here ever charges anybody. */
const RATE_TYPES = ['cpm', 'cpc', 'flat', 'barter'];

export default class CampaignModal extends TabbedFormModal<CampaignModalAttrs> {
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
  protected rateType!: Stream<string>;
  protected rateAmount!: Stream<string>;
  protected rateCurrency!: Stream<string>;
  protected contractNotes!: Stream<string>;

  oninit(vnode: Mithril.Vnode<CampaignModalAttrs, this>) {
    super.oninit(vnode);

    const campaign = this.attrs.campaign;

    this.name = Stream(campaign?.name() ?? '');
    this.status = Stream(campaign?.status() ?? CAMPAIGN_STATUS.draft);
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
    this.rateType = Stream(campaign?.rateType() ?? '');
    this.rateAmount = Stream(campaign?.rateAmount() ?? '');
    this.rateCurrency = Stream(campaign?.rateCurrency() ?? '');
    this.contractNotes = Stream(campaign?.contractNotes() ?? '');

    // `hasOne` returns `false` when the relationship was not loaded, which is
    // not the same as there being no advertiser.
    const advertiser = campaign?.advertiser();
    this.advertiserId = Stream(advertiser ? String(advertiser.id()) : '');

    // The picker reads the store, and the store only holds advertisers if the
    // advertisers tab has been opened. Without this, creating a campaign
    // without visiting that tab first offers a picker with nothing in it.
    app.placements.advertisers.ensureLoaded();
  }

  className(): string {
    return 'CampaignModal Modal--large';
  }

  title(): Mithril.Children {
    return this.attrs.campaign ? trans('campaigns.edit') : trans('campaigns.create');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        {this.tabbedContent()}

        {/* Outside the tabs, so saving never depends on which tab is open. */}
        <div className="Form-group Form-controls PlacementModal-controls">
          <Button type="submit" className="Button Button--primary" loading={this.loading}>
            {trans('save')}
          </Button>
        </div>
      </div>
    );
  }

  tabs(): ItemList<ModalTab> {
    const items = new ItemList<ModalTab>();

    items.add(
      'identity',
      { label: trans('campaigns.tab_identity'), fields: ['name', 'status', 'tier', 'isHouse'], content: () => this.identityTab() },
      100
    );

    items.add(
      'schedule',
      { label: trans('campaigns.tab_schedule'), fields: ['startsAt', 'endsAt', 'daypartMask'], content: () => this.scheduleTab() },
      90
    );

    items.add(
      'delivery',
      {
        label: trans('campaigns.tab_delivery'),
        fields: ['maxImpressions', 'maxClicks', 'pacing', 'frequencyCap', 'frequencyWindow'],
        content: () => this.deliveryTab(),
      },
      80
    );

    items.add('targeting', { label: trans('campaigns.tab_targeting'), fields: ['rules'], content: () => this.targetingTab() }, 70);

    items.add(
      'commercial',
      {
        label: trans('campaigns.tab_commercial'),
        fields: ['rateType', 'rateAmount', 'rateCurrency', 'contractNotes'],
        content: () => this.commercialTab(),
      },
      60
    );

    return items;
  }

  protected identityTab(): Mithril.Children {
    return (
      <div className="Form">
        {this.field('name', <input className="FormControl" name="name" bidi={this.name} required />)}

        {this.field(
          'advertiser',
          <Select
            value={this.advertiserId()}
            options={{
              '': extractText(trans('campaigns.no_advertiser')),
              ...Object.fromEntries(app.store.all<Advertiser>(RESOURCE.advertisers).map((a) => [String(a.id()), a.name()])),
            }}
            onchange={this.advertiserId}
          />,
          trans('campaigns.advertiser_help')
        )}

        {this.field(
          'status',
          <Select
            value={this.status()}
            options={Object.fromEntries(Object.values(CAMPAIGN_STATUS).map((s) => [s, extractText(trans(`campaigns.statuses.${s}`))]))}
            onchange={this.status}
            name="status"
          />,
          trans('campaigns.status_help')
        )}

        {this.field(
          'tier',
          <Select
            value={this.tier()}
            options={Object.fromEntries(TIERS.map((tier) => [String(tier.value), extractText(trans(`campaigns.tiers.${tier.key}`))]))}
            onchange={this.tier}
            name="tier"
          />,
          // Said plainly because the industry name misleads: "guaranteed" here
          // is a priority, not a forecast, and nothing in this extension
          // predicts inventory.
          trans('campaigns.tier_help')
        )}

        <div className="Form-group">
          <Switch state={this.isHouse()} onchange={this.isHouse}>
            {trans('campaigns.is_house')}
          </Switch>
          <div className="helpText">{trans('campaigns.is_house_help')}</div>
        </div>
      </div>
    );
  }

  protected scheduleTab(): Mithril.Children {
    return (
      <div className="Form">
        <div className="PlacementFlight">
          {this.field('starts_at', <input className="FormControl" type="datetime-local" name="startsAt" bidi={this.startsAt} />)}
          {this.field(
            'ends_at',
            <input className="FormControl" type="datetime-local" name="endsAt" bidi={this.endsAt} />,
            trans('campaigns.ends_at_help')
          )}
        </div>

        <div className="Form-group">
          <label>{trans('daypart.label')}</label>
          <div className="helpText">{trans('daypart.help')}</div>
          <DaypartGrid mask={this.daypartMask()} onchange={this.daypartMask} />
        </div>
      </div>
    );
  }

  protected deliveryTab(): Mithril.Children {
    return (
      <div className="Form">
        {this.field('max_impressions', <input className="FormControl" type="number" min="1" name="maxImpressions" bidi={this.maxImpressions} />)}
        {this.field('max_clicks', <input className="FormControl" type="number" min="1" name="maxClicks" bidi={this.maxClicks} />)}

        {this.field(
          'pacing',
          <Select
            value={this.pacing()}
            options={{ asap: extractText(trans('campaigns.pacing_asap')), even: extractText(trans('campaigns.pacing_even')) }}
            onchange={this.pacing}
            name="pacing"
          />,
          trans('campaigns.pacing_help')
        )}

        <div className="Form-group">
          <label>{trans('campaigns.frequency_cap')}</label>
          <div className="PlacementFrequency">
            <input className="FormControl" type="number" min="1" name="frequencyCap" bidi={this.frequencyCap} />
            <Select
              value={this.frequencyWindow()}
              options={Object.fromEntries(['session', 'hour', 'day'].map((w) => [w, extractText(trans(`campaigns.frequency_windows.${w}`))]))}
              onchange={this.frequencyWindow}
              name="frequencyWindow"
            />
          </div>
          <div className="helpText">{trans('campaigns.frequency_cap_help')}</div>
        </div>
      </div>
    );
  }

  protected targetingTab(): Mithril.Children {
    return (
      <div className="Form">
        <div className="Form-group">
          <label>{trans('rules.label')}</label>
          {/* The editor renders `rules.help` itself, since it is the component
              whose behaviour the sentence describes. Repeating it here printed
              the same paragraph twice, one directly under the other. */}
          <RulesEditor rules={this.rules()} onchange={this.rules} />
        </div>
      </div>
    );
  }

  /**
   * What was agreed.
   *
   * These four fields were writable over the API from the start and had no
   * control anywhere, so recording a contracted rate meant hand-crafting a
   * request. Nothing here charges, invoices or converts a currency -- it is
   * somewhere to write down what was agreed so a report can state it.
   */
  protected commercialTab(): Mithril.Children {
    return (
      <div className="Form">
        <p className="helpText">{trans('campaigns.commercial_help')}</p>

        {this.field(
          'rate_type',
          <Select
            value={this.rateType()}
            options={{
              '': extractText(trans('campaigns.rate_type_none')),
              ...Object.fromEntries(RATE_TYPES.map((type) => [type, extractText(trans(`campaigns.rate_types.${type}`))])),
            }}
            onchange={this.rateType}
            name="rateType"
          />
        )}

        <div className="Form-group">
          <label>{trans('campaigns.rate_amount')}</label>
          <div className="PlacementFrequency">
            <input className="FormControl" name="rateAmount" bidi={this.rateAmount} placeholder="0.00" />
            <input
              className="FormControl"
              name="rateCurrency"
              maxlength="3"
              bidi={this.rateCurrency}
              placeholder={extractText(trans('campaigns.rate_currency'))}
            />
          </div>
          <div className="helpText">{trans('campaigns.rate_amount_help')}</div>
        </div>

        {this.field('contract_notes', <textarea className="FormControl" rows="4" name="contractNotes" bidi={this.contractNotes} />)}
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
      rateType: this.rateType() || null,
      rateAmount: this.rateAmount() || null,
      rateCurrency: this.rateCurrency() || null,
      contractNotes: this.contractNotes() || null,
      rules: this.rules(),
      relationships: {
        // `getById` returns undefined for an id the store has not seen; the
        // save contract wants a model or an explicit null.
        advertiser: (this.advertiserId() ? app.store.getById<Advertiser>(RESOURCE.advertisers, this.advertiserId()) : null) ?? null,
      },
    };

    const record = this.attrs.campaign ?? app.store.createRecord<Campaign>(RESOURCE.campaigns);

    record
      // `errorHandler` is what routes a rejection into this modal's own alert
      // and on to the tab holding the rejected field. Without it Flarum shows
      // its global error dialogue instead, and the form beneath it never says
      // which field it disliked.
      .save(data, { errorHandler: this.onerror.bind(this) })
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
