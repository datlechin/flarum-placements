import Model from 'flarum/common/Model';

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

  createdAt = Model.attribute<Date | null, string>('createdAt', Model.transformDate);

  campaign = Model.hasOne<Campaign>('campaign');
}
