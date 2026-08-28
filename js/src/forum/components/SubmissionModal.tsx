import app from 'flarum/forum/app';
import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

import { SUBMISSION_RESOURCE, submittableTypes, trans } from '../submissions';
import type Submission from '../models/Submission';

export interface SubmissionModalAttrs extends IFormModalAttrs {
  submission?: Submission;
  onsaved?: () => void;
}

/**
 * Where a member writes their advert.
 *
 * Only the fields that are theirs: what it says, where it goes, and what kind
 * of thing it is. Where it runs and how often are the forum's decisions, made
 * after somebody has read it.
 */
export default class SubmissionModal extends FormModal<SubmissionModalAttrs> {
  protected name!: Stream<string>;
  protected type!: Stream<string>;
  protected url!: Stream<string>;
  protected payload!: Stream<Record<string, string>>;

  oninit(vnode: Mithril.Vnode<SubmissionModalAttrs, this>) {
    super.oninit(vnode);

    const submission = this.attrs.submission;
    const payload = (submission?.payload() ?? {}) as Record<string, unknown>;

    this.name = Stream(submission?.name() ?? '');
    this.type = Stream(submission?.type() ?? submittableTypes()[0]?.key ?? 'image');
    this.url = Stream(submission?.destinationUrl() ?? '');
    this.payload = Stream(Object.fromEntries(Object.entries(payload).map(([key, value]) => [key, value == null ? '' : String(value)])));
  }

  className(): string {
    return 'SubmissionModal Modal--medium';
  }

  title(): Mithril.Children {
    return this.attrs.submission ? trans('edit') : trans('create');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <div className="Form">
          {this.group('name', <input className="FormControl" bidi={this.name} required />, trans('name_help'))}

          {this.group(
            'type',
            <Select
              value={this.type()}
              options={Object.fromEntries(submittableTypes().map((type) => [type.key, app.translator.trans(type.label)]))}
              onchange={this.type}
            />
          )}

          {this.typeFields()}

          {this.group('destination_url', <input className="FormControl" type="url" bidi={this.url} placeholder="https://" required />)}

          <div className="Form-group">
            <Button type="submit" className="Button Button--primary" loading={this.loading}>
              {trans('submit')}
            </Button>
            <div className="helpText">{trans('submit_help')}</div>
          </div>
        </div>
      </div>
    );
  }

  /**
   * A type this build has no form for gets nothing rather than a JSON box:
   * the admin side offers raw JSON as an escape hatch, which is not something
   * to put in front of a member.
   */
  protected typeFields(): Mithril.Children {
    if (this.type() === 'image') {
      return [
        this.group('asset', <input className="FormControl" type="url" value={this.field('asset')} oninput={this.input('asset')} required />),
        this.group('alt', <input className="FormControl" value={this.field('alt')} oninput={this.input('alt')} />, trans('alt_help')),
      ];
    }

    if (this.type() === 'text') {
      return [
        this.group('headline', <input className="FormControl" value={this.field('headline')} oninput={this.input('headline')} required />),
        this.group('body', <textarea className="FormControl" rows="3" value={this.field('body')} oninput={this.input('body')} />),
        this.group('cta', <input className="FormControl" value={this.field('cta')} oninput={this.input('cta')} />),
      ];
    }

    if (this.type() === 'rich_text') {
      return [
        this.group(
          'source',
          <textarea className="FormControl" rows="5" value={this.field('source')} oninput={this.input('source')} required />,
          trans('source_help')
        ),
      ];
    }

    return null;
  }

  protected field(key: string): string {
    return this.payload()[key] ?? '';
  }

  protected input(key: string): (e: InputEvent) => void {
    return (e: InputEvent) => this.payload({ ...this.payload(), [key]: (e.target as HTMLInputElement).value });
  }

  protected group(key: string, control: Mithril.Children, help?: Mithril.Children): Mithril.Children {
    return (
      <div className="Form-group">
        <label>{trans(key)}</label>
        {control}
        {help && <div className="helpText">{help}</div>}
      </div>
    );
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();

    this.loading = true;

    const payload: Record<string, unknown> = {};

    Object.entries(this.payload()).forEach(([key, value]) => {
      if (value !== '') payload[key] = value;
    });

    const attributes = { name: this.name(), type: this.type(), destinationUrl: this.url(), payload };

    const model = this.attrs.submission ?? app.store.createRecord<Submission>(SUBMISSION_RESOURCE);

    model
      .save(attributes)
      .then(() => {
        this.hide();
        this.attrs.onsaved?.();
      })
      .catch((error: unknown) => {
        this.loading = false;
        this.onerror(error as any);
      });
  }
}
