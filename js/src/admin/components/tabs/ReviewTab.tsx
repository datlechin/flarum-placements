import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Pagination from 'flarum/common/components/Pagination';
import Placeholder from 'flarum/common/components/Placeholder';
import Stream from 'flarum/common/utils/Stream';
import humanTime from 'flarum/common/helpers/humanTime';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { CREATIVE_STATUS, trans } from '../../config';
import type Creative from '../../models/Creative';
import CreativeModal from '../CreativeModal';
import CreativePreview from '../CreativePreview';
import StatusPill, { creativeTone } from '../../../common/components/StatusPill';

/**
 * What is waiting on somebody, and what was turned down.
 *
 * Rejected creatives are listed alongside the pending ones rather than
 * disappearing: a rejection is the start of a conversation with whoever
 * submitted it, and an administrator who cannot see what they turned down
 * cannot answer "why?" a week later.
 */
export default class ReviewTab extends Component {
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

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    app.placements.review.ensureLoaded();
  }

  view(): Mithril.Children {
    const state = app.placements.review;

    return (
      <div className="PlacementsTab">
        <p className="helpText">{trans('review.help')}</p>

        {state.isInitialLoading() ? <LoadingIndicator /> : this.list()}
      </div>
    );
  }

  protected list(): Mithril.Children {
    const state = app.placements.review;

    if (state.isEmpty()) {
      return <Placeholder text={trans('review.none')} />;
    }

    // Waiting first, then turned-down, and oldest first within each.
    //
    // Ordering the two together by age alone put a rejection from last year
    // above a submission from this morning, while the count on the tab
    // insisted three things needed deciding. Rejected creatives stay in view
    // because a rejection is the start of a conversation, but nobody has to
    // act on one.
    const rank = (creative: Creative) => (creative.status() === CREATIVE_STATUS.rejected ? 1 : 0);

    const ordered = [...state.items()].sort((a, b) => rank(a) - rank(b) || (a.createdAt()?.getTime() ?? 0) - (b.createdAt()?.getTime() ?? 0));

    return [
      <ul className="PlacementReview">{ordered.map((creative) => this.row(creative))}</ul>,
      state.pageSize && state.total() > state.pageSize ? (
        <Pagination total={state.total()} perPage={state.pageSize} currentPage={state.currentPage()} onChange={(page: number) => state.goto(page)} />
      ) : null,
    ];
  }

  protected row(creative: Creative): Mithril.Children {
    const id = String(creative.id());
    const rejected = creative.status() === CREATIVE_STATUS.rejected;
    // `hasOne` answers false, not undefined, when the relationship was not
    // loaded -- so this cannot be an optional chain.
    const campaign = creative.campaign();

    return (
      <li className="PlacementReview-item" key={id}>
        <div className="PlacementReview-summary">
          <button type="button" className="Button Button--text PlacementReview-name" onclick={() => this.edit(creative)}>
            {creative.name()}
          </button>

          <StatusPill tone={creativeTone(creative.status())}>{trans(`creatives.statuses.${creative.status()}`)}</StatusPill>

          <span className="PlacementReview-meta">
            {trans('review.from', {
              campaign: campaign ? campaign.name() : '',
              type: app.translator.trans(`datlechin-placements.lib.creatives.types.${creative.type()}`),
            })}
          </span>

          {creative.createdAt() && <span className="PlacementReview-meta">{humanTime(creative.createdAt()!)}</span>}
        </div>

        {rejected && creative.reviewReason() && <div className="PlacementReview-reason">{creative.reviewReason()}</div>}

        {/* The thing being judged. Approving an advert without looking at it
            is the one mistake this queue exists to prevent. */}
        <CreativePreview
          type={creative.type()}
          payload={creative.payload()}
          destinationUrl={creative.destinationUrl()}
          label={creative.labelOverride()}
        />

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
          onclick={() => this.decide(creative, CREATIVE_STATUS.approved)}
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
            onclick={() => this.decide(creative, CREATIVE_STATUS.rejected, this.reason().trim())}
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

    app.modal.show(CreativeModal, { campaign, creative, onsaved: () => this.reload() });
  }

  protected decide(creative: Creative, status: string, reason?: string): void {
    const id = String(creative.id());

    this.saving.add(id);

    // `reviewReason` is sent on an approval too, as null. The server clears it
    // anyway, but leaving it out means the store keeps showing the old reason
    // beside a creative that was accepted.
    creative
      .save({ status, reviewReason: status === CREATIVE_STATUS.rejected ? reason : null })
      .then(() => {
        this.rejecting = null;
        this.reason('');
        this.reload();
      })
      .catch(() => m.redraw())
      .finally(() => {
        this.saving.delete(id);
        m.redraw();
      });
  }

  /**
   * A decision changes both the queue and the count on the tab beside it, and
   * it can change what a campaign is serving, so all three are refreshed.
   */
  protected reload(): void {
    app.placements.review.reload();
    app.placements.countPending();
    app.placements.forgetCreatives();
  }
}
