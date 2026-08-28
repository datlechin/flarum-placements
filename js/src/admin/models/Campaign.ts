import Model from 'flarum/common/Model';

import type Advertiser from './Advertiser';
import type Creative from './Creative';

export interface TargetingRule {
  dimension: string;
  operator: string;
  value: string;
}

export default class Campaign extends Model {
  name = Model.attribute<string>('name');
  status = Model.attribute<string>('status');
  tier = Model.attribute<number>('tier');
  isHouse = Model.attribute<boolean>('isHouse');

  startsAt = Model.attribute<Date | null, string | null>('startsAt', Model.transformDate);
  endsAt = Model.attribute<Date | null, string | null>('endsAt', Model.transformDate);

  maxImpressions = Model.attribute<number | null>('maxImpressions');
  maxClicks = Model.attribute<number | null>('maxClicks');
  pacing = Model.attribute<string>('pacing');
  frequencyCap = Model.attribute<number | null>('frequencyCap');
  frequencyWindow = Model.attribute<string>('frequencyWindow');

  /** 42 hex characters: 168 bits, one per hour of the week. Null means always. */
  daypartMask = Model.attribute<string | null>('daypartMask');

  rateType = Model.attribute<string | null>('rateType');
  rateAmount = Model.attribute<string | null>('rateAmount');
  rateCurrency = Model.attribute<string | null>('rateCurrency');
  contractNotes = Model.attribute<string | null>('contractNotes');

  impressions = Model.attribute<number>('impressions');
  clicks = Model.attribute<number>('clicks');

  /**
   * Computed on the server from the dates and the caps, so it disagrees with
   * `status` whenever an administrator's intent and reality have parted ways —
   * which is the moment worth showing them.
   */
  isLive = Model.attribute<boolean>('isLive');

  createdAt = Model.attribute<Date | null, string>('createdAt', Model.transformDate);

  rules = Model.attribute<TargetingRule[]>('rules');

  advertiser = Model.hasOne<Advertiser | null>('advertiser');
  creatives = Model.hasMany<Creative>('creatives');
}
