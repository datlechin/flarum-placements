import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import FieldSet from 'flarum/common/components/FieldSet';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import type { SlotConfig } from '../../../common/types';
import { CREATIVE_STATUS, RESOURCE, slotsByGroup, trans } from '../../config';
import type Creative from '../../models/Creative';
import type PlacementSetting from '../../models/PlacementSetting';
import StatusPill from '../../../common/components/StatusPill';

/**
 * Every slot this forum has, and what may be changed about each one.
 *
 * Slots are declared in code, so this list cannot be added to -- what is
 * editable is whether each one is used and how. A slot with no row is not
 * unconfigured, it is default-configured, which is why saving writes a row
 * rather than the client creating one up front.
 *
 * The controls are behind a disclosure, one slot at a time: rendering every
 * control of every slot at once is well over a hundred inputs on this screen.
 *
 * Collapsed, a slot still says what it is set to. That matters more than the
 * controls: the common question is "what is this slot doing?", not "let me
 * change it", and answering it should not require opening anything.
 */
export default class SlotsTab extends Component {
  protected settings: Record<string, PlacementSetting> = {};

  /** Approved creatives, for the passback picker. */
  protected creatives: Creative[] | null = null;
  protected loading = true;
  protected saving: string | null = null;

  /** The slot whose controls are open. One at a time, deliberately. */
  protected open: string | null = null;

  oninit(vnode: Mithril.Vnode<{}, this>) {
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
    //
    // Filtered on the server rather than in the browser. This is still one
    // page of results -- the endpoint caps at fifty, and the picker does not
    // page -- so it does not make the list complete. What it fixes is the
    // worse version: asking for the first fifty creatives of any status and
    // then keeping the approved ones, which offered an empty picker on a forum
    // whose fifty most recent creatives all happened to be pending.
    app.store
      .find<Creative[]>(RESOURCE.creatives, { filter: { status: CREATIVE_STATUS.approved } })
      .then((creatives) => {
        this.creatives = creatives;
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
      <div className="PlacementsTab">
        <div className="PlacementToolbar">
          <p className="helpText PlacementToolbar-help">{trans('slots.help')}</p>

          {/* The answer to the two questions this extension is otherwise asked
              for ever: where are the slots, and why is my advert not showing.
              It belongs here, next to the list of slots it labels. */}
          <div className="PlacementToolbar-actions">
            <Button
              className="Button"
              icon="fas fa-eye"
              onclick={() => window.open(`${app.forum.attribute('baseUrl')}/?placement_demo=1`, '_blank', 'noopener')}
            >
              {trans('demo.open')}
            </Button>
          </div>
        </div>

        {slotsByGroup().map(([group, slots]) => (
          <div className="PlacementSlots-group" key={group}>
            <h4 className="PlacementSlots-heading">{trans(`placement_groups.${group}`)}</h4>
            <ul className="PlacementSlots">{slots.map((slot) => this.row(slot))}</ul>
          </div>
        ))}
      </div>
    );
  }

  protected row(slot: SlotConfig): Mithril.Children {
    const setting = this.settings[slot.key];
    const enabled = setting ? setting.enabled() : true;
    const open = this.open === slot.key;

    return (
      <li className="PlacementSlots-item" key={slot.key}>
        <div className="PlacementSlots-row">
          <Switch
            className="PlacementSlots-switch"
            state={enabled}
            loading={this.saving === slot.key}
            onchange={(on: boolean) => this.save(slot, { enabled: on })}
          >
            {app.translator.trans(slot.label)}
          </Switch>

          <div className="PlacementSlots-facts">
            <code className="PlacementSlots-key">{slot.key}</code>
            {slot.recommendedSize && <span className="PlacementSlots-size">{`${slot.recommendedSize[0]}×${slot.recommendedSize[1]}`}</span>}
            {enabled && this.summary(slot, setting)}
          </div>

          {enabled && (
            <Button
              className="Button Button--text PlacementSlots-disclosure"
              icon={open ? 'fas fa-chevron-up' : 'fas fa-chevron-down'}
              aria-expanded={open ? 'true' : 'false'}
              onclick={() => (this.open = open ? null : slot.key)}
            >
              {open ? trans('slots.done') : trans('slots.configure')}
            </Button>
          )}
        </div>

        <div className="helpText PlacementSlots-description">{app.translator.trans(slot.description)}</div>

        {enabled && open && (
          <div className="PlacementSlots-controls">
            {this.deliveryControls(slot, setting)}
            {this.fallbackControls(slot, setting)}
            {this.reserveControls(slot, setting)}
            {this.rotationControl(slot, setting)}
            {slot.repeating && this.repeatControls(slot, setting)}
          </div>
        )}
      </li>
    );
  }

  /**
   * What this slot is set to, without opening it.
   *
   * Only the settings that have been moved away from their default are shown:
   * a row repeating "max 1, next tier, random" for every slot on the forum is
   * noise that hides the one slot somebody actually changed.
   */
  protected summary(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    if (!setting) return null;

    const pills: Mithril.Children[] = [];

    const maxFill = setting.maxFill();
    if (maxFill !== null && maxFill !== slot.maxFill) {
      pills.push(<StatusPill tone="neutral">{trans('slots.summary_max_fill', { count: maxFill })}</StatusPill>);
    }

    const fallback = setting.fallback();
    if (fallback && fallback !== 'next_tier') {
      pills.push(<StatusPill tone="neutral">{trans(`slots.fallback_${fallback}`)}</StatusPill>);
    }

    if (setting.rotation() === 'sticky') {
      pills.push(<StatusPill tone="neutral">{trans('slots.rotation_sticky')}</StatusPill>);
    }

    if (setting.labelMode() && setting.labelMode() !== 'inherit') {
      pills.push(<StatusPill tone="neutral">{trans(`slots.label_${setting.labelMode()}`)}</StatusPill>);
    }

    return pills.length ? <span className="PlacementSlots-summary">{pills}</span> : null;
  }

  protected deliveryControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    return (
      <FieldSet label={extractText(trans('slots.group_delivery'))} className="PlacementSlots-fieldset">
        <div className="Form-group">
          <label>{trans('slots.max_fill')}</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            max="10"
            value={setting?.maxFill() ?? slot.maxFill}
            onchange={(e: Event) => this.save(slot, { maxFill: Number((e.target as HTMLInputElement).value) || 1 })}
          />
        </div>

        <div className="Form-group">
          <label>{trans('slots.label_mode')}</label>
          <Select
            value={setting?.labelMode() ?? 'inherit'}
            options={{
              inherit: extractText(trans('slots.label_inherit')),
              always: extractText(trans('slots.label_always')),
              never: extractText(trans('slots.label_never')),
            }}
            onchange={(value: string) => this.save(slot, { labelMode: value })}
          />
        </div>
      </FieldSet>
    );
  }

  /**
   * What the slot does when nothing matched.
   */
  protected fallbackControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    // `next_tier` rather than `house`: the column's default moved when the
    // setting was given behaviour, and a stale default here would show a slot
    // as doing one thing while the server did another.
    const fallback = setting?.fallback() ?? slot.fallback ?? 'next_tier';

    return (
      <FieldSet label={extractText(trans('slots.group_fallback'))} className="PlacementSlots-fieldset">
        <div className="Form-group">
          <label>{trans('slots.fallback')}</label>
          <Select
            value={fallback}
            options={{
              next_tier: extractText(trans('slots.fallback_next_tier')),
              house: extractText(trans('slots.fallback_house')),
              passback: extractText(trans('slots.fallback_passback')),
              collapse: extractText(trans('slots.fallback_collapse')),
            }}
            onchange={(value: string) => this.save(slot, { fallback: value })}
          />
          <div className="helpText">{trans(`slots.fallback_${fallback}_help`)}</div>
        </div>

        {fallback === 'passback' && (
          <div className="Form-group">
            <label>{trans('slots.passback_creative')}</label>
            <Select
              value={String(setting?.passbackCreativeId() ?? '')}
              options={{
                '': extractText(trans('slots.passback_none')),
                ...Object.fromEntries((this.creatives ?? []).map((creative) => [String(creative.id()), creative.name()])),
              }}
              onchange={(value: string) => this.save(slot, { passbackCreativeId: value === '' ? null : Number(value) })}
            />
            <div className="helpText">{trans('slots.passback_help')}</div>
          </div>
        )}
      </FieldSet>
    );
  }

  /**
   * The height held open while a creative loads.
   *
   * Per breakpoint, because a slot that is a leaderboard on a desktop is
   * usually something much shorter on a phone, and reserving the desktop height
   * everywhere pushes the page down on the readers who can least afford it.
   * Blank means reserve nothing, which is not the same as reserving zero.
   */
  protected reserveControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    const fields: Array<['reservePhone' | 'reserveTablet' | 'reserveDesktop', string]> = [
      ['reservePhone', 'phone'],
      ['reserveTablet', 'tablet'],
      ['reserveDesktop', 'desktop'],
    ];

    return (
      <FieldSet label={extractText(trans('slots.group_reserve'))} className="PlacementSlots-fieldset PlacementSlots-fieldset--inline">
        {fields.map(([attribute, name]) => (
          <div className="Form-group" key={name}>
            <label>{trans(`slots.reserve_${name}`)}</label>
            <input
              className="FormControl"
              type="number"
              min="0"
              max="2000"
              placeholder="—"
              value={setting?.[attribute]() ?? ''}
              onchange={(e: Event) => this.save(slot, { [attribute]: this.number((e.target as HTMLInputElement).value) })}
            />
          </div>
        ))}
      </FieldSet>
    );
  }

  protected rotationControl(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    return (
      <FieldSet label={extractText(trans('slots.group_rotation'))} className="PlacementSlots-fieldset">
        <div className="Form-group">
          <label>{trans('slots.rotation')}</label>
          <Select
            value={setting?.rotation() ?? 'random'}
            options={{
              random: extractText(trans('slots.rotation_random')),
              sticky: extractText(trans('slots.rotation_sticky')),
            }}
            onchange={(value: string) => this.save(slot, { rotation: value })}
          />
        </div>
      </FieldSet>
    );
  }

  /**
   * Only a repeating slot gets these. Offering "every N" on a slot that renders
   * once would be a control that visibly does nothing.
   */
  protected repeatControls(slot: SlotConfig, setting: PlacementSetting | undefined): Mithril.Children {
    return (
      <FieldSet label={extractText(trans('slots.group_repeat'))} className="PlacementSlots-fieldset PlacementSlots-fieldset--inline">
        <div className="Form-group">
          <label>{trans('slots.every_n')}</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            value={setting?.everyN() ?? ''}
            onchange={(e: Event) => this.save(slot, { everyN: this.number((e.target as HTMLInputElement).value) })}
          />
        </div>

        <div className="Form-group">
          <label>{trans('slots.repeat_limit')}</label>
          <input
            className="FormControl"
            type="number"
            min="1"
            value={setting?.repeatLimit() ?? ''}
            onchange={(e: Event) => this.save(slot, { repeatLimit: this.number((e.target as HTMLInputElement).value) })}
          />
        </div>
      </FieldSet>
    );
  }

  protected number(value: string): number | null {
    return value === '' ? null : Number(value);
  }

  /**
   * The endpoint takes the placement key as its id and writes the row if there
   * is not one yet, so there is nothing to create here.
   *
   * `exists` has to be set by hand, and that is the whole reason first-time
   * configuration never worked. `createRecord` leaves it false, `pushData` sets
   * the id but not `exists`, and core's `Model.save()` chooses its method from
   * `exists` while building the URL from the id -- so a slot with no row yet
   * was sent as `POST /placement-settings/{key}`. That is not a route: the
   * resource registers Show, Index and Update and deliberately no Create,
   * because an administrator configures a slot and never invents one. The
   * request failed, the `catch` below swallowed it, and the switch sprang back
   * with nothing said.
   *
   * Saying the record exists is the truthful thing to say here rather than a
   * trick: every placement key is addressable whether or not a row has been
   * written for it, which is exactly what `PlacementSettingResource::find()`
   * implements.
   */
  protected save(slot: SlotConfig, data: Record<string, unknown>): void {
    this.saving = slot.key;

    const record = this.settings[slot.key] ?? app.store.createRecord<PlacementSetting>(RESOURCE.settings);

    record.pushData({ id: slot.key, type: RESOURCE.settings });
    record.exists = true;

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
