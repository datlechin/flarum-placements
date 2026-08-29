import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';

import type Campaign from './Campaign';

export default class Advertiser extends Model {
  name = Model.attribute<string>('name');
  contactEmail = Model.attribute<string | null>('contactEmail');
  notes = Model.attribute<string | null>('notes');

  /**
   * Whether a report link exists, never the token itself. The token is the one
   * credential here that reaches somebody outside the forum, so it leaves the
   * server exactly once: in the response to the request that created it.
   */
  hasReportToken = Model.attribute<boolean>('hasReportToken');
  reportTokenExpiresAt = Model.attribute<Date | null, string | null>('reportTokenExpiresAt', Model.transformDate);

  /**
   * The forum account this advertiser is, when it is one.
   *
   * Set automatically when a member submits an advert -- that is what makes the
   * record exist at all -- and settable by hand, so an advertiser who already
   * has a login can be connected to it and see their own submissions.
   */
  user = Model.hasOne<User | null>('user');

  campaigns = Model.hasMany<Campaign>('campaigns');
}
