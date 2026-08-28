import Extend from 'flarum/common/extenders';
import UserPageResolver from 'flarum/forum/resolvers/UserPageResolver';
import type DefaultResolver from 'flarum/common/resolvers/DefaultResolver';
import commonExtend from '../common/extend';

import CampaignStoppedNotification from './components/CampaignStoppedNotification';
import SubmissionsUserPage from './components/SubmissionsUserPage';
import Submission from './models/Submission';
import { SUBMISSION_RESOURCE } from './submissions';

export default [
  ...commonExtend,

  new Extend.Store().add(SUBMISSION_RESOURCE, Submission),

  // The blueprint has always sent these; nothing rendered them, so they
  // arrived as an empty row in the notification list.
  new Extend.Notification().add('datlechinPlacementCampaignStopped', CampaignStoppedNotification),

  // A profile tab, because that is where Flarum already keeps everything
  // belonging to one person -- and it comes with the routing and the layout.
  // The page itself refuses to draw for anybody but its owner: the route is
  // reachable by typing it.
  //
  // `UserPageResolver` is what every core profile tab uses. Without it,
  // moving between tabs remounts the page and refetches a user the store
  // already holds, so the profile header blinks out and back on each click.
  //
  // The cast is working around a typing bug in core rather than hiding one
  // here. `Routes.add()` types its resolver as `typeof DefaultResolver`,
  // whose constructor takes any component; `UserPageResolver` narrows that
  // to a UserPage. Constructor parameters are contravariant, so the narrower
  // resolver is not assignable, and every extension that adds a profile tab
  // hits it -- flarum/likes included, on the identical line, which typechecks
  // no better than this would. The usage is right; the signature cannot say so.
  new Extend.Routes() //
    .add('datlechin-placements.submissions', '/u/:username/submissions', SubmissionsUserPage, UserPageResolver as unknown as typeof DefaultResolver),
];
