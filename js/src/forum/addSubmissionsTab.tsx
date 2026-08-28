import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import { canSubmit, trans } from './submissions';

/**
 * The tab that leads to a member's own adverts.
 *
 * Only on your own profile. Everything behind it is between one member and the
 * staff -- what they submitted, and what the staff said about it -- so it has
 * no business being announced on a page anybody can read.
 */
export default function addSubmissionsTab(): void {
  extend(UserPage.prototype, 'navItems', function (items: ItemList<Mithril.Children>) {
    if (!canSubmit()) return;

    const user = this.user;

    if (!user || user.id() !== app.session.user?.id()) return;

    items.add(
      'datlechin-placements-submissions',
      <LinkButton href={app.route('datlechin-placements.submissions', { username: user.slug() })} icon="fas fa-bullhorn">
        {trans('tab')}
      </LinkButton>,
      // Below the tabs core puts there, which are what somebody came to a
      // profile to read.
      70
    );
  });
}
