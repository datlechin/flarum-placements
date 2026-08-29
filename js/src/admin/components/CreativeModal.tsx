import app from 'flarum/admin/app';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import ItemList from 'flarum/common/utils/ItemList';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import ImageUploadField from '../../common/components/ImageUploadField';
import { CREATIVE_STATUS, RESOURCE, creativeTypes, slots, trans } from '../config';
import type Campaign from '../models/Campaign';
import type Creative from '../models/Creative';
import CreativePreview from './CreativePreview';
import TabbedFormModal from './TabbedFormModal';
import type { ModalTab } from './TabbedFormModal';

export interface CreativeModalAttrs extends IFormModalAttrs {
  campaign: Campaign;
  creative?: Creative;
  onsaved?: () => void;
}

/**
 * Exactly what `NetworkType::normalize` keeps.
 *
 * Mirrored here so a name the server would drop is refused while it is being
 * typed rather than discarded on save, where the only symptom is a container
 * the network never fills.
 */
const ALLOWED_ATTRIBUTE = /^(data-[a-z0-9-]+|class|id|style)$/i;

export default class CreativeModal extends TabbedFormModal<CreativeModalAttrs> {
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

  /**
   * A network container's attributes, held as ordered pairs while they are
   * being typed. See `attributePairs()`.
   */
  protected attributes!: Stream<Array<[string, string]>>;

  /**
   * Both writable over the API from the start, with no control anywhere.
   *
   * `labelOverride` replaces the "Advertisement" wording for one creative --
   * some sponsors contract for specific disclosure text. `variantGroup` marks
   * creatives as variants of one another.
   */
  protected labelOverride!: Stream<string>;
  protected variantGroup!: Stream<string>;

  oninit(vnode: Mithril.Vnode<CreativeModalAttrs, this>) {
    super.oninit(vnode);

    const creative = this.attrs.creative;
    const payload = (creative?.payload() ?? {}) as Record<string, unknown>;

    this.name = Stream(creative?.name() ?? '');
    this.type = Stream(creative?.type() ?? 'image');
    this.status = Stream(creative?.status() ?? 'approved');
    this.weight = Stream(String(creative?.weight() ?? 10));
    this.url = Stream(creative?.destinationUrl() ?? '');
    // Scalars become strings for the inputs to bind to. Two exceptions:
    // anything structured, because stringifying a list of logos loses it, and
    // booleans, because `String(false)` is `'false'` and every non-empty
    // string is truthy -- a switch reading it back would show "on" for a flag
    // that was saved off.
    this.payload = Stream(
      Object.fromEntries(
        Object.entries(payload).map(([key, value]) => [
          key,
          value !== null && (typeof value === 'object' || typeof value === 'boolean') ? value : String(value ?? ''),
        ])
      )
    );
    this.placements = Stream(creative?.placements() ?? {});
    this.labelOverride = Stream(creative?.labelOverride() ?? '');
    this.variantGroup = Stream(creative?.variantGroup() ?? '');

    const stored = payload.attributes;

    this.attributes = Stream(
      typeof stored === 'object' && stored !== null && !Array.isArray(stored)
        ? Object.entries(stored as Record<string, unknown>).map(([name, value]): [string, string] => [name, value == null ? '' : String(value)])
        : []
    );
  }

  className(): string {
    return 'CreativeModal Modal--large';
  }

  title(): Mithril.Children {
    return this.attrs.creative ? trans('creatives.edit') : trans('creatives.create');
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

  /**
   * Anything the server rejected that no tab claims came from the payload, and
   * the payload is edited here.
   *
   * @see TabbedFormModal.tabHolding for why a payload field cannot be listed.
   */
  protected fallbackTab(): string | null {
    return 'content';
  }

  tabs(): ItemList<ModalTab> {
    const items = new ItemList<ModalTab>();

    items.add(
      'details',
      {
        label: trans('creatives.tab_details'),
        fields: ['name', 'type', 'destinationUrl', 'weight', 'status'],
        content: () => this.detailsTab(),
      },
      100
    );

    // The type's own sub-form. Its fields live under `payload`, so the server
    // points at `payload` when it rejects one and that is what maps here.
    items.add('content', { label: trans('creatives.tab_content'), fields: ['payload'], content: () => this.contentTab() }, 90);

    items.add('slots', { label: trans('creatives.tab_slots'), fields: ['placements'], content: () => this.slotsTab() }, 80);

    items.add(
      'advanced',
      { label: trans('creatives.tab_advanced'), fields: ['labelOverride', 'variantGroup'], content: () => this.advancedTab() },
      70
    );

    items.add('preview', { label: trans('creatives.tab_preview'), fields: [], content: () => this.previewTab() }, 60);

    return items;
  }

  protected detailsTab(): Mithril.Children {
    return (
      <div className="Form">
        {this.group('name', <input className="FormControl" name="name" bidi={this.name} required />)}

        {this.group(
          'type',
          <Select
            value={this.type()}
            options={Object.fromEntries(creativeTypes().map((type) => [type.key, extractText(app.translator.trans(type.label))]))}
            onchange={this.type}
            name="type"
          />
        )}

        {this.group(
          'destination_url',
          <input className="FormControl" type="url" name="destinationUrl" bidi={this.url} placeholder="https://" />,
          trans('creatives.destination_url_help')
        )}

        {this.group(
          'weight',
          <input className="FormControl" type="number" min="1" max="100" name="weight" bidi={this.weight} />,
          trans('creatives.weight_help')
        )}

        {this.group(
          'status',
          <Select
            value={this.status()}
            options={Object.fromEntries(Object.values(CREATIVE_STATUS).map((s) => [s, extractText(trans(`creatives.statuses.${s}`))]))}
            onchange={this.status}
            name="status"
          />,
          trans('creatives.status_help')
        )}
      </div>
    );
  }

  protected contentTab(): Mithril.Children {
    return <div className="Form">{this.typeFields()}</div>;
  }

  protected slotsTab(): Mithril.Children {
    return (
      <div className="Form">
        <div className="Form-group">
          <label>{trans('creatives.placements')}</label>
          <div className="helpText">{trans('creatives.placements_help')}</div>
          {this.slotPicker()}
        </div>
      </div>
    );
  }

  protected advancedTab(): Mithril.Children {
    return (
      <div className="Form">
        {this.group(
          'label_override',
          <input
            className="FormControl"
            name="labelOverride"
            bidi={this.labelOverride}
            placeholder={extractText(trans('creatives.label_override_placeholder'))}
          />,
          trans('creatives.label_override_help')
        )}

        {this.group(
          'variant_group',
          <input className="FormControl" name="variantGroup" bidi={this.variantGroup} />,
          trans('creatives.variant_group_help')
        )}
      </div>
    );
  }

  /**
   * What a reader would see, drawn from the form as it currently stands rather
   * than from what was last saved.
   */
  protected previewTab(): Mithril.Children {
    return (
      <CreativePreview type={this.type()} payload={this.cleanPayload()} destinationUrl={this.url() || null} label={this.labelOverride() || null} />
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
    if (this.type() === 'network') return this.networkFields();
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
      this.group(
        'asset',
        <ImageUploadField value={this.field('asset')} onchange={(url: string) => this.payload({ ...this.payload(), asset: url })} required />
      ),
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
              <ImageUploadField
                value={logo.asset ?? ''}
                placeholder={extractText(trans('creatives.asset'))}
                onchange={(url: string) => this.setLogos(logos.map((l, i) => (i === index ? { ...l, asset: url } : l)))}
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
   * A container an external network fills.
   *
   * The attributes are typed in as name/value pairs rather than pasted as a
   * snippet, because that is how they are stored and how they are rendered:
   * as real attributes on a real element, never through `innerHTML`. The
   * server keeps only `data-*`, `class`, `id` and `style`, which is said here
   * rather than discovered by having a save silently drop half the form.
   */
  protected networkFields(): Mithril.Children {
    const attributes = this.attributePairs();

    return [
      this.group(
        'element',
        <Select
          value={this.field('element') || 'div'}
          options={{ div: 'div', ins: 'ins' }}
          onchange={(value: string) => this.payload({ ...this.payload(), element: value })}
        />,
        trans('creatives.element_help')
      ),

      <div className="Form-group">
        <label>{trans('creatives.attributes')}</label>
        <div className="helpText">{trans('creatives.attributes_help')}</div>

        <div className="PlacementPairs">
          {attributes.map(([name, value], index) => (
            <div className="PlacementPairs-row" key={index}>
              <div className="PlacementPairs-name">
                <input
                  className="FormControl"
                  value={name}
                  placeholder="data-ad-client"
                  aria-invalid={this.attributeAllowed(name) ? undefined : 'true'}
                  oninput={(e: InputEvent) => this.setAttributeAt(index, (e.target as HTMLInputElement).value, value)}
                />
                {/* Said while it is being typed rather than discovered by
                    having the save quietly drop it. The server keeps only
                    `data-*`, `class`, `id` and `style`, and until now a name
                    outside that set vanished with no message anywhere. */}
                {!this.attributeAllowed(name) && <div className="PlacementPairs-warning">{trans('creatives.attribute_not_allowed')}</div>}
              </div>
              <input
                className="FormControl"
                value={value}
                oninput={(e: InputEvent) => this.setAttributeAt(index, name, (e.target as HTMLInputElement).value)}
              />
              <Button
                className="Button Button--icon"
                icon="fas fa-times"
                type="button"
                title={extractText(trans('creatives.remove_attribute'))}
                onclick={() => this.setAttributes(attributes.filter((_, i) => i !== index))}
              />
            </div>
          ))}
        </div>

        <Button className="Button" type="button" onclick={() => this.setAttributes([...attributes, ['', '']])}>
          {trans('creatives.add_attribute')}
        </Button>
      </div>,

      this.group(
        'frame_height',
        <input className="FormControl" type="number" min="1" value={this.field('height')} oninput={this.payloadInput('height')} />,
        trans('creatives.network_height_help')
      ),

      <div className="Form-group">
        <Switch state={this.payload().requiresConsent !== false} onchange={(on: boolean) => this.payload({ ...this.payload(), requiresConsent: on })}>
          {trans('creatives.requires_consent')}
        </Switch>
        <div className="helpText">{trans('creatives.requires_consent_help')}</div>
      </div>,

      <div className="Form-group">
        <Switch
          state={this.payload().refreshOnNavigate === true}
          onchange={(on: boolean) => this.payload({ ...this.payload(), refreshOnNavigate: on })}
        >
          {trans('creatives.refresh_on_navigate')}
        </Switch>
        <div className="helpText">{trans('creatives.refresh_on_navigate_help')}</div>
      </div>,
    ];
  }

  /**
   * The attributes being edited, as an ordered list of pairs.
   *
   * A map cannot be edited in place. Renaming a key means deleting one and
   * adding another, so the row would jump or vanish under the cursor as it was
   * typed, and two rows briefly sharing a blank name would collapse into one.
   * The list is turned back into a map on save, and only then.
   */
  protected attributePairs(): Array<[string, string]> {
    return this.attributes();
  }

  /**
   * Whether the server would keep an attribute by this name.
   *
   * A blank name is allowed: it is a row somebody has started, not a mistake,
   * and `cleanPayload` drops it on save anyway.
   */
  protected attributeAllowed(name: string): boolean {
    const trimmed = name.trim();

    return trimmed === '' || ALLOWED_ATTRIBUTE.test(trimmed);
  }

  protected setAttributes(pairs: Array<[string, string]>): void {
    this.attributes(pairs);
  }

  protected setAttributeAt(index: number, name: string, value: string): void {
    this.setAttributes(this.attributes().map((pair: [string, string], i: number): [string, string] => (i === index ? [name, value] : pair)));
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

    // The pairs become a map here and nowhere earlier, so a half-typed name
    // never has to be a valid key. A row with no name is a row the author
    // started and abandoned.
    if (this.type() === 'network') {
      clean.attributes = Object.fromEntries(this.attributes().filter(([name]: [string, string]) => name.trim() !== ''));
    }

    return clean;
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();

    this.loading = true;

    const record = this.attrs.creative ?? app.store.createRecord<Creative>(RESOURCE.creatives);

    record
      .save(
        {
          name: this.name(),
          type: this.type(),
          status: this.status(),
          weight: Number(this.weight()),
          destinationUrl: this.url() || null,
          labelOverride: this.labelOverride() || null,
          variantGroup: this.variantGroup() || null,
          payload: this.cleanPayload(),
          placements: this.placements(),
          relationships: { campaign: this.attrs.campaign },
        },
        // Routes a rejection into this modal's alert and on to the tab holding
        // the rejected field, instead of Flarum's global error dialogue.
        { errorHandler: this.onerror.bind(this) }
      )
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
