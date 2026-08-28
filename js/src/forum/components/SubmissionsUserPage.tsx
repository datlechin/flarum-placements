import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import humanTime from 'flarum/common/helpers/humanTime';
import type Mithril from 'mithril';

import { SUBMISSION_RESOURCE, canSubmit, trans } from '../submissions';
import type Submission from '../models/Submission';
import SubmissionModal from './SubmissionModal';

/**
 * A member's own adverts, and what became of them.
 *
 * On the profile page because that is where Flarum already puts everything
 * belonging to one person, and it comes with the routing and the layout. The
 * tab is only ever drawn on your own profile: everything here is between one
 * member and the staff.
 */
export default class SubmissionsUserPage extends UserPage {
  protected submissions: Submission[] | null = null;

  oninit(vnode: Mithril.Vnode<any, this>) {
    super.oninit(vnode);

    this.loadUser(m.route.param('username'));
  }

  show(user: any) {
    super.show(user);

    this.load();
  }

  protected load(): void {
    // Never asks for a username. The endpoint answers with the actor's own
    // submissions whoever is asking, so there is nothing here to point at
    // somebody else.
    app.store
      .find<Submission[]>(SUBMISSION_RESOURCE)
      .then((submissions) => {
        this.submissions = submissions;
        m.redraw();
      })
      .catch(() => {
        this.submissions = [];
        m.redraw();
      });
  }

  content(): Mithril.Children {
    // The route is reachable by typing it. Somebody else's profile, or a
    // viewer who may not submit, gets the same nothing the tab would have
    // shown them.
    if (!canSubmit() || this.user?.id() !== app.session.user?.id()) {
      return <Placeholder text={trans('unavailable')} />;
    }

    return (
      <div className="SubmissionsUserPage">
        <div className="SubmissionsUserPage-header">
          <p className="helpText">{trans('help')}</p>
          <Button
            className="Button Button--primary"
            icon="fas fa-plus"
            onclick={() => app.modal.show(SubmissionModal, { onsaved: () => this.load() })}
          >
            {trans('create')}
          </Button>
        </div>

        {this.submissions === null ? <LoadingIndicator /> : this.list()}
      </div>
    );
  }

  protected list(): Mithril.Children {
    if (!this.submissions?.length) {
      return <Placeholder text={trans('none')} />;
    }

    // Newest first: unlike the staff queue, which is worked from the front,
    // this is somebody looking for the thing they just sent.
    const ordered = [...this.submissions].sort((a, b) => (b.createdAt()?.getTime() ?? 0) - (a.createdAt()?.getTime() ?? 0));

    return <ul className="SubmissionList">{ordered.map((submission) => this.row(submission))}</ul>;
  }

  protected row(submission: Submission): Mithril.Children {
    const status = submission.status();

    return (
      <li className="SubmissionList-item" key={String(submission.id())}>
        <div className="SubmissionList-summary">
          <span className="SubmissionList-name">{submission.name()}</span>
          <span className={`Badge Badge--${this.badge(status)}`}>{trans(`statuses.${status}`)}</span>
          {submission.createdAt() && <span className="SubmissionList-meta">{humanTime(submission.createdAt()!)}</span>}
        </div>

        {/* The reason is the whole value of a rejection to the person who
            wrote the advert. */}
        {status === 'rejected' && submission.reviewReason() && <div className="SubmissionList-reason">{submission.reviewReason()}</div>}

        <div className="SubmissionList-actions">
          <Button
            className="Button Button--text"
            icon="fas fa-pencil-alt"
            onclick={() => app.modal.show(SubmissionModal, { submission, onsaved: () => this.load() })}
          >
            {trans('edit')}
          </Button>

          <Button className="Button Button--text" icon="fas fa-times" onclick={() => this.remove(submission)}>
            {trans('delete')}
          </Button>
        </div>

        {/* Said on every row rather than once at the top, because it is the
            answer to "I edited it, why is it not showing again?" */}
        {status === 'approved' && <div className="SubmissionList-note helpText">{trans('approved_note')}</div>}
      </li>
    );
  }

  protected badge(status: string): string {
    if (status === 'approved') return 'success';
    if (status === 'rejected') return 'danger';

    return 'warning';
  }

  protected remove(submission: Submission): void {
    if (!confirm(String(trans('confirm_delete')))) return;

    submission.delete().then(() => this.load());
  }
}
