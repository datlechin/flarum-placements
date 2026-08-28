import Model from 'flarum/common/Model';

/**
 * A member's own advert.
 *
 * Deliberately smaller than the admin-side Creative: there is no weight, no
 * campaign, no slot, and no advertiser on it, because none of those are the
 * member's to see or set.
 */
export default class Submission extends Model {
  name = Model.attribute<string>('name');
  type = Model.attribute<string>('type');
  payload = Model.attribute<Record<string, unknown>>('payload');
  destinationUrl = Model.attribute<string | null>('destinationUrl');

  /** Read-only: a member says what their advert is, the forum says whether it runs. */
  status = Model.attribute<string>('status');
  reviewReason = Model.attribute<string | null>('reviewReason');
  reviewedAt = Model.attribute<Date | null, string>('reviewedAt', Model.transformDate);

  createdAt = Model.attribute<Date | null, string>('createdAt', Model.transformDate);
  updatedAt = Model.attribute<Date | null, string>('updatedAt', Model.transformDate);
}
