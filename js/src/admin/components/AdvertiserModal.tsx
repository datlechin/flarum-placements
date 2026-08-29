import app from 'flarum/admin/app';
import Avatar from 'flarum/common/components/Avatar';
import Button from 'flarum/common/components/Button';
import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import UserSelectionModal from 'flarum/common/components/UserSelectionModal';
import Stream from 'flarum/common/utils/Stream';
import extractText from 'flarum/common/utils/extractText';
import type User from 'flarum/common/models/User';
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
   * The forum account this advertiser is, if any.
   *
   * Set automatically when a member submits an advert. It was writable over the
   * API and had no control, so an advertiser who already had a login could not
   * be connected to it by hand -- only by submitting something.
   */
  protected user!: Stream<User | null>;

  /**
   * The link, held only for as long as this modal is open.
   *
   * The server sends it once, in the response to the request that asked for it,
   * and never again -- so this is the only moment it can be copied.
   */
  protected issuedUrl: string | null = null;

  oninit(vnode: Mithril.Vnode<AdvertiserModalAttrs, this>) {
    super.oninit(vnode);

    const advertiser = this.attrs.advertiser;

    this.name = Stream(advertiser?.name() ?? '');
    this.contactEmail = Stream(advertiser?.contactEmail() ?? '');
    this.notes = Stream(advertiser?.notes() ?? '');
    // `hasOne` answers false, not undefined, when the relationship was not
    // loaded, so this cannot be an optional chain.
    this.user = Stream((advertiser?.user() || null) as User | null);
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
            <input className="FormControl" name="name" bidi={this.name} required />
          </div>

          <div className="Form-group">
            <label>{trans('advertisers.contact_email')}</label>
            <input className="FormControl" type="email" name="contactEmail" bidi={this.contactEmail} />
          </div>

          <div className="Form-group">
            <label>{trans('advertisers.user')}</label>
            <div className="helpText">{trans('advertisers.user_help')}</div>
            {this.userField()}
          </div>

          <div className="Form-group">
            <label>{trans('advertisers.notes')}</label>
            <textarea className="FormControl" rows="3" name="notes" bidi={this.notes} />
          </div>

          {this.attrs.advertiser && this.reportLink()}

          <div className="Form-group Form-controls">
            <Button type="submit" className="Button Button--primary" loading={this.loading}>
              {trans('save')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  protected userField(): Mithril.Children {
    const user = this.user();

    return (
      <div className="PlacementUserField">
        {user ? (
          <div className="PlacementUserField-chosen">
            <Avatar user={user} className="PlacementUserField-avatar" />
            <span>{user.displayName()}</span>
            <Button
              className="Button Button--icon Button--link"
              icon="fas fa-times"
              type="button"
              aria-label={extractText(trans('advertisers.unlink_user'))}
              onclick={() => this.user(null)}
            />
          </div>
        ) : (
          <Button className="Button" type="button" icon="fas fa-user-plus" onclick={() => this.chooseUser()}>
            {trans('advertisers.link_user')}
          </Button>
        )}
      </div>
    );
  }

  protected chooseUser(): void {
    app.modal.show(UserSelectionModal, {
      selected: [],
      maxItems: 1,
      onsubmit: (users: User[]) => {
        this.user(users[0] ?? null);
        m.redraw();
      },
    });
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
          <div className="PlacementReportUrl">
            <input
              className="FormControl"
              readonly
              value={this.issuedUrl}
              oncreate={(vnode: Mithril.VnodeDOM) => (vnode.dom as HTMLInputElement).select()}
            />
            <Button className="Button" type="button" icon="fas fa-copy" onclick={() => navigator.clipboard?.writeText(this.issuedUrl!)}>
              {trans('advertisers.copy_link')}
            </Button>
          </div>
        )}

        <div className="PlacementReportActions">
          <Button className="Button" type="button" onclick={() => this.issue(true)} loading={this.loading}>
            {advertiser.hasReportToken() ? trans('advertisers.replace_link') : trans('advertisers.create_link')}
          </Button>

          {advertiser.hasReportToken() && (
            <Button className="Button Button--danger" type="button" onclick={() => this.issue(false)}>
              {trans('advertisers.revoke_link')}
            </Button>
          )}
        </div>
      </div>
    );
  }

  /**
   * `true` issues a link, invalidating any previous one; `false` revokes.
   *
   * Both are confirmed first. Each destroys a URL that cannot be recovered,
   * which is a heavier thing than deleting a campaign a backup can restore.
   */
  protected issue(regenerate: boolean): void {
    const advertiser = this.attrs.advertiser!;

    // Issuing the first link destroys nothing, so it is the one case that does
    // not ask.
    if (advertiser.hasReportToken()) {
      const question = regenerate ? 'advertisers.replace_link_confirm' : 'advertisers.revoke_link_confirm';

      if (!confirm(extractText(trans(question, { name: advertiser.name() })))) return;
    }

    this.loading = true;

    advertiser
      .save({ regenerateReportToken: regenerate })
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

    const record = this.attrs.advertiser ?? app.store.createRecord<Advertiser>(RESOURCE.advertisers);

    record
      .save(
        {
          name: this.name(),
          contactEmail: this.contactEmail() || null,
          notes: this.notes() || null,
          relationships: { user: this.user() },
        },
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
