import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';

import SubmissionsPage from './components/SubmissionsPage';
import Submission from './models/Submission';
import { SUBMISSION_RESOURCE } from './submissions';

export default [
  ...commonExtend,

  new Extend.Store().add(SUBMISSION_RESOURCE, Submission),

  // A profile tab, because that is where Flarum already keeps everything
  // belonging to one person -- and it comes with the routing and the layout.
  // The page itself refuses to draw for anybody but its owner: the route is
  // reachable by typing it.
  new Extend.Routes().add('datlechin-placement.submissions', '/u/:username/adverts', SubmissionsPage),
];
