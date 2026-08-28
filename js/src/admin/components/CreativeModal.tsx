import app from 'flarum/admin/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { RESOURCE, creativeTypes, slots, trans } from '../config';
import type Campaign from '../models/Campaign';
import type Creative from '../models/Creative';

export interface CreativeModalAttrs extends IFormModalAttrs {
  campaign: Campaign;
  creative?: Creative;
  onsaved?: () => void;
}

export default class CreativeModal extends FormModal<CreativeModalAttrs> {
  protected name!: Stream<string>;
  protected type!: Stream<string>;
  protected status!: Stream<string>;
  protected weight!: Stream<string>;
  protected url!: Stream<string>;
  /**
   * Values are `unknown` rather than `string` because not every payload is
   * flat: a logo wall holds a list of rows. `field()` reads the scalar cases
   * back out for the inputs that expect one.
   */
  protected payload!: Stream<Record<string, unknown>>;
  protected placements!: Stream<Record<string, number | null>>;

  oninit(vnode: Mithril.Vnode<CreativeModalAttrs, this>) {
    super.oninit(vnode);

    const creative = this.attrs.creative;
    const payload = (creative?.payload() ?? {}) as Record<string, unknown>;

    this.name = Stream(creative?.name() ?? '');
    this.type = Stream(creative?.type() ?? 'image');
    this.status = Stream(creative?.status() ?? 'approved');
    this.weight = Stream(String(creative?.weight() ?? 10));
    this.url = Stream(creative?.destinationUrl() ?? '');
    // Scalars become strings for the inputs to bind to; anything structured is
    // kept as it is, because stringifying a list of logos loses it.
    this.payload = Stream(
      Object.fromEntries(
        Object.entries(payload).map(([key, value]) => [key, value !== null && typeof value === 'object' ? value : String(value ?? '')])
      )
    );
    this.placements = Stream(creative?.placements() ?? {});
  }

  className(): string {
    return 'CreativeModal Modal--medium';
  }

  title(): Mithril.Children {
    return this.attrs.creative ? trans('creatives.edit') : trans('creatives.create');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.group('name', <input className="FormControl" bidi={this.name} required />)}

          {this.group(
            'type',
            <Select
              value={this.type()}
              options={Object.fromEntries(creativeTypes().map((type) => [type.key, app.translator.trans(type.label)]))}
              onchange={this.type}
            />
          )}

          {this.typeFields()}

          {this.group(
            'destination_url',
            <input className="FormControl" type="url" bidi={this.url} placeholder="https://" />,
            trans('creatives.destination_url_help')
          )}

          {this.group('weight', <input className="FormControl" type="number" min="1" max="100" bidi={this.weight} />, trans('creatives.weight_help'))}

          {this.group(
            'status',
            <Select
              value={this.status()}
              options={Object.fromEntries(['draft', 'pending', 'approved', 'rejected'].map((s) => [s, trans(`creatives.statuses.${s}`)]))}
              onchange={this.status}
            />,
            trans('creatives.status_help')
          )}

          <div className="Form-group">
            <label>{trans('creatives.placements')}</label>
            <div className="helpText">{trans('creatives.placements_help')}</div>
            {this.slotPicker()}
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

  /**
   * The fields belonging to the chosen type.
   *
   * A type this build does not know how to draw a form for gets a plain JSON
   * field rather than nothing: an extension can register a type on the server
   * before anybody has written its form, and the creative should still be
   * editable.
   */
  protected typeFields(): Mithril.Children {
    if (this.type() === 'image') return this.imageFields();
    if (this.type() === 'text') return this.textFields();
    if (this.type() === 'rich_text') return this.richTextFields();
    if (this.type() === 'logo_wall') return this.logoWallFields();
    if (this.type() === 'raw_html') return this.rawHtmlFields();

    return this.group('payload', <textarea className="FormControl" rows="6" value={JSON.stringify(this.payload(), null, 2)} disabled />);
  }

  /**
   * Raw HTML runs inside `<iframe sandbox="allow-scripts">`, never in the page.
   * The height is asked for because a sandboxed frame cannot measure itself
   * without being allowed to talk to the page.
   */
  protected rawHtmlFields(): Mithril.Children {
    return [
      this.group(
        'html',
        <textarea className="FormControl" rows="8" value={this.field('html')} oninput={this.payloadInput('html')} required />,
        trans('creatives.html_help')
      ),
      this.group(
        'frame_height',
        <input className="FormControl" type="number" min="1" value={this.field('height')} oninput={this.payloadInput('height')} required />
      ),
    ];
  }

  protected imageFields(): Mithril.Children {
    return [
      this.group('asset', <input className="FormControl" type="url" value={this.field('asset')} oninput={this.payloadInput('asset')} required />),
      this.group('alt', <input className="FormControl" value={this.field('alt')} oninput={this.payloadInput('alt')} />, trans('creatives.alt_help')),
      <div className="Form-group PlacementSize">
        {this.group('width', <input className="FormControl" type="number" value={this.field('width')} oninput={this.payloadInput('width')} />)}
        {this.group(
          'height',
          <input className="FormControl" type="number" value={this.field('height')} oninput={this.payloadInput('height')} />,
          trans('creatives.size_help')
        )}
      </div>,
    ];
  }

  protected textFields(): Mithril.Children {
    return [
      this.group('headline', <input className="FormControl" value={this.field('headline')} oninput={this.payloadInput('headline')} required />),
      this.group('body', <textarea className="FormControl" value={this.field('body')} oninput={this.payloadInput('body')} />),
      this.group('cta', <input className="FormControl" value={this.field('cta')} oninput={this.payloadInput('cta')} />),
    ];
  }

  /**
   * Only the source is edited. The markup is the server's, rendered through
   * the forum's own formatter on save, so the syntax here is the syntax of a
   * post on this forum -- whatever formatting extensions happen to be on.
   */
  protected richTextFields(): Mithril.Children {
    return [
      this.group(
        'source',
        <textarea className="FormControl" rows="6" value={this.field('source')} oninput={this.payloadInput('source')} required />,
        trans('creatives.source_help')
      ),
    ];
  }

  protected logoWallFields(): Mithril.Children {
    const logos = this.logos();

    return [
      <div className="Form-group">
        <label>{trans('creatives.logos')}</label>
        <div className="helpText">{trans('creatives.logos_help')}</div>

        <div className="PlacementLogos">
          {logos.map((logo, index) => (
            <div className="PlacementLogos-row" key={index}>
              <input
                className="FormControl"
                type="url"
                value={logo.asset ?? ''}
                placeholder={extractText(trans('creatives.asset'))}
                oninput={this.logoInput(index, 'asset')}
                required
              />
              <input
                className="FormControl"
                value={logo.alt ?? ''}
                placeholder={extractText(trans('creatives.alt'))}
                oninput={this.logoInput(index, 'alt')}
              />
              <input
                className="FormControl"
                type="url"
                value={logo.url ?? ''}
                placeholder={extractText(trans('creatives.logo_url'))}
                oninput={this.logoInput(index, 'url')}
              />
              <Button
                className="Button Button--icon"
                icon="fas fa-times"
                type="button"
                title={extractText(trans('creatives.remove_logo'))}
                onclick={() => this.setLogos(logos.filter((_, i) => i !== index))}
              />
            </div>
          ))}
        </div>

        <Button className="Button" type="button" onclick={() => this.setLogos([...logos, { asset: '' }])}>
          {trans('creatives.add_logo')}
        </Button>
      </div>,

      this.group(
        'columns',
        <input className="FormControl" type="number" min="1" max="8" value={this.field('columns') || '4'} oninput={this.payloadInput('columns')} />,
        trans('creatives.columns_help')
      ),
    ];
  }

  /**
   * @return The logo rows currently being edited, always a real array so that
   *         the form works the same on a new creative and an existing one.
   */
  protected logos(): Array<Record<string, string>> {
    const rows = this.payload().logos;

    if (!Array.isArray(rows)) return [];

    return rows.filter((row): row is Record<string, string> => typeof row === 'object' && row !== null);
  }

  protected setLogos(logos: Array<Record<string, string>>): void {
    this.payload({ ...this.payload(), logos });
  }

  protected logoInput(index: number, key: string): (e: InputEvent) => void {
    return (e: InputEvent) => {
      const logos = this.logos().map((logo, i) => (i === index ? { ...logo, [key]: (e.target as HTMLInputElement).value } : logo));

      this.setLogos(logos);
    };
  }

  /**
   * A payload value as a string, for the inputs that hold one.
   *
   * Anything structured reads back as empty rather than as `[object Object]`,
   * which is what a naive cast would put into the field.
   */
  protected field(key: string): string {
    const value = this.payload()[key];

    return typeof value === 'string' ? value : '';
  }

  /**
   * Which slots this creative runs in.
   *
   * Only slots the server declared appear, because a key nothing renders would
   * be an assignment that silently never shows and is indistinguishable from a
   * targeting problem.
   */
  protected slotPicker(): Mithril.Children {
    const chosen = this.placements();

    return (
      <div className="PlacementPicker">
        {slots()
          .filter((slot) => slot.allowedTypes.length === 0 || slot.allowedTypes.includes(this.type()))
          .map((slot) => (
            <div className="PlacementPicker-slot" key={slot.key}>
              <Switch state={slot.key in chosen} onchange={(on: boolean) => this.toggle(slot.key, on)}>
                {app.translator.trans(slot.label)}
              </Switch>
              <div className="helpText">{app.translator.trans(slot.description)}</div>
            </div>
          ))}
      </div>
    );
  }

  protected toggle(key: string, on: boolean): void {
    const next = { ...this.placements() };

    if (on) {
      next[key] = null;
    } else {
      delete next[key];
    }

    this.placements(next);
  }

  protected payloadInput(key: string): (e: InputEvent) => void {
    return (e: InputEvent) => {
      this.payload({ ...this.payload(), [key]: (e.target as HTMLInputElement).value });
    };
  }

  protected group(key: string, control: Mithril.Children, help?: Mithril.Children): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{trans(`creatives.${key}`)}</label>
        {control}
        {help && <div className="helpText">{help}</div>}
      </div>
    );
  }

  /**
   * Numbers are sent as numbers and empty strings are dropped, so a payload
   * never carries `"width": ""` for the renderer to guess at.
   */
  protected cleanPayload(): Record<string, unknown> {
    const clean: Record<string, unknown> = {};

    Object.entries(this.payload()).forEach(([key, value]) => {
      if (value === '') return;

      // Structured values go through as they are. A logo row is cleaned on the
      // server, which is the side that has to be sure of it anyway.
      if (typeof value !== 'string') {
        clean[key] = value;

        return;
      }

      clean[key] = ['width', 'height', 'columns'].includes(key) ? Number(value) : value;
    });

    return clean;
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();

    this.loading = true;

    const record = this.attrs.creative ?? app.store.createRecord(RESOURCE.creatives);

    record
      .save({
        name: this.name(),
        type: this.type(),
        status: this.status(),
        weight: Number(this.weight()),
        destinationUrl: this.url() || null,
        payload: this.cleanPayload(),
        placements: this.placements(),
        relationships: { campaign: this.attrs.campaign },
      })
      .then(() => {
        this.attrs.onsaved?.();
        this.hide();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
