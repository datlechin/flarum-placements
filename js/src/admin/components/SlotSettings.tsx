import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';

import type { SlotConfig } from '../../common/types';
import { RESOURCE, slotsByGroup, trans } from '../config';
import type Creative from '../models/Creative';
import type PlacementSetting from '../models/PlacementSetting';

export interface SlotSettingsAttrs extends ComponentAttrs {}

/**
 * Every slot this forum has, and what an administrator may change about it.
 *
 * Slots are declared in code, so this list is not editable in the sense of
 * adding to it — what is editable is whether each one is used and how. A slot
 * with no row is not unconfigured, it is default-configured, which is why
 * saving writes a row rather than the client creating one up front.
 */
export default class SlotSettings extends Component<SlotSettingsAttrs> {
  protected settings: Record<string, PlacementSetting> = {};

  /** Approved creatives, for the passback picker. */
  protected creatives: Creative[] | null = null;
  protected loading = true;
  protected saving: string | null = null;

  oninit(vnode: Mithril.Vnode<SlotSettingsAttrs, this>) {
    super.oninit(vnode);

    app.store.find<PlacementSetting[]>(RESOURCE.settings).then((rows) => {
      rows.forEach((row) => {
        this.settings[String(row.id())] = row;
      });

      this.loading = false;
      m.redraw();
    });

    // Needed only by the passback picker, and fetched regardless: the picker
    // appears the moment somebody chooses that fallback, and a select that
    // arrives empty and fills in a second later reads as broken.
    app.store
      .find<Creative[]>(RESOURCE.creatives)
      .then((creatives) => {
        this.creatives = creatives.filter((creative) => creative.status() === 'approved');
        m.redraw();
      })
      .catch(() => {
        this.creatives = [];
        m.redraw();
      });
  }

  view(): Mithril.Children {
    if (this.loading) return <LoadingIndicator />;

    return (
      <div className="PlacementSlots-groups">
        {slotsByGroup().map(([group, slots]) => (
          <div className="PlacementSlots-group" key={group}>
            <h3 className="PlacementSlots-heading">{trans(`placement_groups.${group}`)}</h3>
            <ul className="PlacementSlots">{slots.map((slot) => this.row(slot))}</ul>
          </div>
        ))}
      </div>
    );
  }

  protected row(slot: SlotConfig): Mithril.Children {
    const setting = this.settings[slot.key];
    const enabled = setting ? setting.enabled() : true;

    return (
      <li className="PlacementSlots-item" key={slot.key}>
        <div className="PlacementSlots-row">
          <Switch state={enabled} loading={this.saving === slot.key} onchange={(on: boolean) => this.save(slot, { enabled: on })}>
            {app.translator.trans(slot.label)}
          </Switch>

          <code className="PlacementSlots-key">{slot.key}</code>

          {slot.recommendedSize && <span className="PlacementSlots-size">{`${slot.recommendedSize[0]}×${slot.recommendedSize[1]}`}</span>}
        </div>

        <div className="helpText PlacementSlots-description">{app.translator.trans(slot.description)}</div>

        {enabled && this.deliveryControls(slot, setting)}
        {enabled && this.fallbackControls(slot, setting)}
        {enabled && this.reserveControls(slot, setting)}
        {enabled && this.rotationControl(slot, setting)}
        {enabled && slot.repeating && this.repeatControls(slot, setting)}
      </li>
    );
  }

  /**
   * How much this slot holds and what it says about itself.
   *
   * Both were writable through the API and honoured at serve time with no
   * control anywhere, which is the same as not having them.
   */
  protected deliveryControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    return (
      <div className="PlacementSlots-repeat">
        <label>
          {trans('slots.max_fill')}
          <input
            className="FormControl"
            type="number"
            min="1"
            max="10"
            value={setting?.maxFill() ?? slot.maxFill}
            onchange={(e: Event) => this.save(slot, { maxFill: Number((e.target as HTMLInputElement).value) || 1 })}
          />
        </label>

        <label>
          {trans('slots.label_mode')}
          <Select
            value={setting?.labelMode() ?? 'inherit'}
            options={{
              inherit: trans('slots.label_inherit'),
              always: trans('slots.label_always'),
              never: trans('slots.label_never'),
            }}
            onchange={(value: string) => this.save(slot, { labelMode: value })}
          />
        </label>
      </div>
    );
  }

  /**
   * What the slot does when nothing matched.
   *
   * Every mode was writable through the API and none of them did anything:
   * the client took the best tier and ignored the setting entirely, so all
   * four behaved as `next_tier`.
   */
  protected fallbackControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    const fallback = setting?.fallback() ?? slot.fallback ?? 'house';

    return (
      <div className="PlacementSlots-repeat">
        <label>
          {trans('slots.fallback')}
          <Select
            value={fallback}
            options={{
              next_tier: trans('slots.fallback_next_tier'),
              house: trans('slots.fallback_house'),
              passback: trans('slots.fallback_passback'),
              collapse: trans('slots.fallback_collapse'),
            }}
            onchange={(value: string) => this.save(slot, { fallback: value })}
          />
        </label>

        {fallback === 'passback' && (
          <label>
            {trans('slots.passback_creative')}
            <Select
              value={String(setting?.passbackCreativeId() ?? '')}
              options={{
                '': trans('slots.passback_none'),
                ...Object.fromEntries((this.creatives ?? []).map((creative) => [String(creative.id()), creative.name()])),
              }}
              onchange={(value: string) => this.save(slot, { passbackCreativeId: value === '' ? null : Number(value) })}
            />
          </label>
        )}
      </div>
    );
  }

  /**
   * The height held open while a creative loads.
   *
   * Per breakpoint, because a slot that is a leaderboard on a desktop is
   * usually something much shorter on a phone, and reserving the desktop
   * height everywhere pushes the page down on the readers who can least
   * afford it. Blank means reserve nothing.
   */
  protected reserveControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    const fields: Array<['reservePhone' | 'reserveTablet' | 'reserveDesktop', string]> = [
      ['reservePhone', 'phone'],
      ['reserveTablet', 'tablet'],
      ['reserveDesktop', 'desktop'],
    ];

    return (
      <div className="PlacementSlots-repeat">
        <span className="PlacementSlots-reserveLabel">{trans('slots.reserve')}</span>

        {fields.map(([attribute, name]) => (
          <label key={name}>
            {trans(`slots.reserve_${name}`)}
            <input
              className="FormControl"
              type="number"
              min="0"
              max="2000"
              placeholder="—"
              value={setting?.[attribute]() ?? ''}
              onchange={(e: Event) => this.save(slot, { [attribute]: this.number((e.target as HTMLInputElement).value) })}
            />
          </label>
        ))}
      </div>
    );
  }

  /**
   * Whether the slot draws again on every page, or keeps what it drew.
   */
  protected rotationControl(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    return (
      <div className="PlacementSlots-repeat">
        <label>
          {trans('slots.rotation')}
          <Select
            value={setting?.rotation() ?? 'random'}
            options={{ random: trans('slots.rotation_random'), sticky: trans('slots.rotation_sticky') }}
            onchange={(value: string) => this.save(slot, { rotation: value })}
          />
        </label>
      </div>
    );
  }

  /**
   * Only a repeating slot gets these. Offering "every N" on a slot that renders
   * once would be a control that visibly does nothing.
   */
  protected repeatControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    return (
      <div className="PlacementSlots-repeat">
        <label>
          {trans('slots.every_n')}
          <input
            className="FormControl"
            type="number"
            min="1"
            value={setting?.everyN() ?? ''}
            onchange={(e: Event) => this.save(slot, { everyN: this.number((e.target as HTMLInputElement).value) })}
          />
        </label>

        <label>
          {trans('slots.repeat_limit')}
          <input
            className="FormControl"
            type="number"
            min="1"
            value={setting?.repeatLimit() ?? ''}
            onchange={(e: Event) => this.save(slot, { repeatLimit: this.number((e.target as HTMLInputElement).value) })}
          />
        </label>
      </div>
    );
  }

  protected number(value: string): number | null {
    return value === '' ? null : Number(value);
  }

  /**
   * The endpoint takes the placement key as its id and writes the row if there
   * is not one yet, so there is nothing to create here.
   */
  protected save(slot: SlotConfig, data: Record<string, unknown>): void {
    this.saving = slot.key;

    const record = this.settings[slot.key] ?? app.store.createRecord(RESOURCE.settings);

    record.pushData({ id: slot.key, type: RESOURCE.settings });

    record
      .save(data)
      .then((saved: PlacementSetting) => {
        this.settings[slot.key] = saved;
      })
      .catch(() => {})
      .then(() => {
        this.saving = null;
        m.redraw();
      });
  }
}
