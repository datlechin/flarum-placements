import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';

import type Campaign from './Campaign';

export default class Creative extends Model {
  name = Model.attribute<string>('name');
  type = Model.attribute<string>('type');
  status = Model.attribute<string>('status');
  weight = Model.attribute<number>('weight');

  destinationUrl = Model.attribute<string | null>('destinationUrl');
  labelOverride = Model.attribute<string | null>('labelOverride');
  variantGroup = Model.attribute<string | null>('variantGroup');

  payload = Model.attribute<Record<string, unknown>>('payload');

  /**
   * Placement key to weight. A null weight means "use the creative's own".
   */
  placements = Model.attribute<Record<string, number | null>>('placements');

  impressions = Model.attribute<number>('impressions');
  viewableImpressions = Model.attribute<number>('viewableImpressions');
  clicks = Model.attribute<number>('clicks');

  /**
   * Why a creative was rejected. Written by whoever rejected it and shown to
   * whoever submitted it: a rejection with no reason is one somebody resubmits
   * unchanged.
   */
  reviewReason = Model.attribute<string | null>('reviewReason');
  reviewedAt = Model.attribute<Date | null, string>('reviewedAt', Model.transformDate);

  createdAt = Model.attribute<Date | null, string>('createdAt', Model.transformDate);

  campaign = Model.hasOne<Campaign>('campaign');
  reviewer = Model.hasOne<User>('reviewer');
}
