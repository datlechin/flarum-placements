import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import Stream from 'flarum/common/utils/Stream';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { RESOURCE, trans } from '../config';
import type Creative from '../models/Creative';
import CreativeModal from './CreativeModal';

export interface ReviewSectionAttrs extends ComponentAttrs {
  /** Called after a decision, so the page can reload what it lists. */
  ondecided?: () => void;
}

/**
 * What is waiting on somebody, and what was turned down.
 *
 * Rejected creatives are listed alongside the pending ones rather than
 * disappearing: a rejection is the start of a conversation with whoever
 * submitted it, and an administrator who cannot see what they turned down
 * cannot answer "why?" a week later.
 */
export default class ReviewSection extends Component<ReviewSectionAttrs> {
  protected creatives: Creative[] | null = null;

  /**
   * The creative whose rejection reason is being written, and the reason.
   *
   * Inline rather than in a modal: rejecting is a sentence, and a dialogue box
   * for a sentence puts the queue behind the thing being decided about.
   */
  protected rejecting: string | null = null;
  protected reason = Stream('');

  /** Ids currently being saved, so a row cannot be decided twice. */
  protected saving = new Set<string>();

  oninit(vnode: Mithril.Vnode<ReviewSectionAttrs, this>) {
    super.oninit(vnode);

    this.load();
  }

  protected load(): void {
    app.store
      // Both statuses in one request. The server takes a list, so a queue
      // showing waiting and turned-down together costs one round trip.
      .find<Creative[]>(RESOURCE.creatives, { filter: { status: ['pending', 'rejected'] }, include: 'campaign,reviewer' })
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
    return (
      <section className="PlacementSection container">
        <div className="PlacementSection-header">
          <h2>{trans('review.title')}</h2>
          {this.pendingCount() > 0 && <span className="Badge Badge--important">{this.pendingCount()}</span>}
        </div>
        <p className="helpText">{trans('review.help')}</p>

        {this.creatives === null ? <LoadingIndicator /> : this.list()}
      </section>
    );
  }

  protected pendingCount(): number {
    return (this.creatives ?? []).filter((creative) => creative.status() === 'pending').length;
  }

  protected list(): Mithril.Children {
    if (!this.creatives?.length) {
      return <Placeholder text={trans('review.none')} />;
    }

    // Oldest first: a queue is worked from the front, and the thing that has
    // been waiting longest is the one somebody is waiting on.
    const ordered = [...this.creatives].sort((a, b) => (a.createdAt()?.getTime() ?? 0) - (b.createdAt()?.getTime() ?? 0));

    return <ul className="PlacementReview">{ordered.map((creative) => this.row(creative))}</ul>;
  }

  protected row(creative: Creative): Mithril.Children {
    const id = String(creative.id());
    const rejected = creative.status() === 'rejected';
    // `hasOne` answers false, not undefined, when the relationship was not
    // loaded -- so this cannot be an optional chain.
    const campaign = creative.campaign();

    return (
      <li className="PlacementReview-item" key={id}>
        <div className="PlacementReview-summary">
          <button type="button" className="Button Button--text PlacementReview-name" onclick={() => this.edit(creative)}>
            {creative.name()}
          </button>

          <span className={`Badge Badge--${rejected ? 'danger' : 'warning'}`}>{trans(`creatives.statuses.${creative.status()}`)}</span>

          <span className="PlacementReview-meta">
            {trans('review.from', {
              campaign: campaign ? campaign.name() : '',
              type: app.translator.trans(`datlechin-placements.admin.creatives.types.${creative.type()}`),
            })}
          </span>

          {creative.createdAt() && <span className="PlacementReview-meta">{humanTime(creative.createdAt()!)}</span>}
        </div>

        {rejected && creative.reviewReason() && <div className="PlacementReview-reason">{creative.reviewReason()}</div>}

        {this.rejecting === id ? this.rejectionForm(creative) : this.decisions(creative, rejected)}
      </li>
    );
  }

  protected decisions(creative: Creative, rejected: boolean): Mithril.Children {
    const id = String(creative.id());

    return (
      <div className="PlacementReview-actions">
        <Button
          className="Button Button--primary"
          icon="fas fa-check"
          loading={this.saving.has(id)}
          disabled={this.saving.has(id)}
          onclick={() => this.decide(creative, 'approved')}
        >
          {trans('review.approve')}
        </Button>

        {/* A creative already rejected keeps the button, so the reason can be
            rewritten without approving it first. */}
        <Button
          className="Button"
          icon="fas fa-times"
          disabled={this.saving.has(id)}
          onclick={() => {
            this.rejecting = id;
            this.reason(creative.reviewReason() ?? '');
          }}
        >
          {rejected ? trans('review.edit_reason') : trans('review.reject')}
        </Button>
      </div>
    );
  }

  protected rejectionForm(creative: Creative): Mithril.Children {
    const id = String(creative.id());

    return (
      <div className="PlacementReview-rejection">
        <textarea
          className="FormControl"
          rows="2"
          value={this.reason()}
          oninput={(e: InputEvent) => this.reason((e.target as HTMLTextAreaElement).value)}
          placeholder={extractText(trans('review.reason_placeholder'))}
          oncreate={(vnode: Mithril.VnodeDOM) => (vnode.dom as HTMLTextAreaElement).focus()}
        />

        <div className="PlacementReview-actions">
          <Button
            className="Button Button--danger"
            loading={this.saving.has(id)}
            // A rejection with no reason is one somebody resubmits unchanged,
            // so the button will not fire without one.
            disabled={!this.reason().trim() || this.saving.has(id)}
            onclick={() => this.decide(creative, 'rejected', this.reason().trim())}
          >
            {trans('review.confirm_reject')}
          </Button>

          <Button className="Button Button--text" onclick={() => (this.rejecting = null)}>
            {trans('review.cancel')}
          </Button>
        </div>
      </div>
    );
  }

  protected edit(creative: Creative): void {
    const campaign = creative.campaign();

    if (!campaign) return;

    app.modal.show(CreativeModal, { campaign, creative, onsaved: () => this.load() });
  }

  protected decide(creative: Creative, status: 'approved' | 'rejected', reason?: string): void {
    const id = String(creative.id());

    this.saving.add(id);

    // `reviewReason` is sent on an approval too, as null. The server clears it
    // anyway, but leaving it out means the store keeps showing the old reason
    // beside a creative that was accepted.
    creative
      .save({ status, reviewReason: status === 'rejected' ? reason : null })
      .then(() => {
        this.rejecting = null;
        this.reason('');
        this.load();
        this.attrs.ondecided?.();
      })
      .catch(() => m.redraw())
      .finally(() => {
        this.saving.delete(id);
        m.redraw();
      });
  }
}
