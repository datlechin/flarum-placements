import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';

import type { SlotConfig } from '../../common/types';
import { RESOURCE, slotsByGroup, trans } from '../config';
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

        {enabled && this.rotationControl(slot, setting)}
        {enabled && slot.repeating && this.repeatControls(slot, setting)}
      </li>
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
