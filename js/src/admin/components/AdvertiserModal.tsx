import app from 'flarum/admin/app';
import Button from 'flarum/common/components/Button';
import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';

import { RESOURCE, trans } from '../config';
import type Advertiser from '../models/Advertiser';

export interface AdvertiserModalAttrs extends IFormModalAttrs {
  advertiser?: Advertiser;
  onsaved?: () => void;
}

export default class AdvertiserModal extends FormModal<AdvertiserModalAttrs> {
  protected name!: Stream<string>;
  protected contactEmail!: Stream<string>;
  protected notes!: Stream<string>;

  /**
   * The link, held only for as long as this modal is open.
   *
   * The server sends it once, in the response to the request that asked for
   * it, and never again — so this is the only moment it can be copied.
   */
  protected issuedUrl: string | null = null;

  oninit(vnode: Mithril.Vnode<AdvertiserModalAttrs, this>) {
    super.oninit(vnode);

    const advertiser = this.attrs.advertiser;

    this.name = Stream(advertiser?.name() ?? '');
    this.contactEmail = Stream(advertiser?.contactEmail() ?? '');
    this.notes = Stream(advertiser?.notes() ?? '');
  }

  className(): string {
    return 'AdvertiserModal Modal--medium';
  }

  title(): Mithril.Children {
    return this.attrs.advertiser ? trans('advertisers.edit') : trans('advertisers.create');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{trans('advertisers.name')}</label>
            <input className="FormControl" bidi={this.name} required />
          </div>

          <div className="Form-group">
            <label>{trans('advertisers.contact_email')}</label>
            <input className="FormControl" type="email" bidi={this.contactEmail} />
          </div>

          <div className="Form-group">
            <label>{trans('advertisers.notes')}</label>
            <textarea className="FormControl" rows="3" bidi={this.notes} />
          </div>

          {this.attrs.advertiser && this.reportLink()}

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
   * What the advertiser gets instead of a login.
   */
  protected reportLink(): Mithril.Children {
    const advertiser = this.attrs.advertiser!;

    return (
      <div className="Form-group">
        <label>{trans('advertisers.report_link')}</label>
        <div className="helpText">{trans('advertisers.report_link_help')}</div>

        {this.issuedUrl && (
          <input
            className="FormControl PlacementReportUrl"
            readonly
            value={this.issuedUrl}
            oncreate={(vnode: Mithril.VnodeDOM) => (vnode.dom as HTMLInputElement).select()}
          />
        )}

        <div className="PlacementReportActions">
          <Button className="Button" onclick={() => this.issue(true)} loading={this.loading}>
            {advertiser.hasReportToken() ? trans('advertisers.replace_link') : trans('advertisers.create_link')}
          </Button>

          {advertiser.hasReportToken() && (
            <Button className="Button Button--link" onclick={() => this.issue(false)}>
              {trans('advertisers.revoke_link')}
            </Button>
          )}
        </div>
      </div>
    );
  }

  /**
   * `true` issues a link, invalidating any previous one; `false` revokes.
   */
  protected issue(regenerate: boolean): void {
    this.loading = true;

    this.attrs
      .advertiser!.save({ regenerateReportToken: regenerate })
      .then((saved: Advertiser) => {
        // The URL is present exactly once, on this response.
        this.issuedUrl = regenerate ? ((saved.data.attributes as Record<string, unknown>).reportUrl as string) ?? null : null;
        this.attrs.onsaved?.();
      })
      .catch(() => {})
      .then(() => {
        this.loading = false;
        m.redraw();
      });
  }

  onsubmit(e: SubmitEvent): void {
    e.preventDefault();

    this.loading = true;

    const record = this.attrs.advertiser ?? app.store.createRecord(RESOURCE.advertisers);

    record
      .save({
        name: this.name(),
        contactEmail: this.contactEmail() || null,
        notes: this.notes() || null,
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
