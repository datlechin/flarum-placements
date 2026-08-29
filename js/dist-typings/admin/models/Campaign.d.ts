import Model from 'flarum/common/Model';
import type Advertiser from './Advertiser';
import type Creative from './Creative';
export interface TargetingRule {
    dimension: string;
    operator: string;
    value: string;
}
export default class Campaign extends Model {
    name: () => string;
    status: () => string;
    tier: () => number;
    isHouse: () => boolean;
    startsAt: () => Date | null;
    endsAt: () => Date | null;
    maxImpressions: () => number | null;
    maxClicks: () => number | null;
    pacing: () => string;
    frequencyCap: () => number | null;
    frequencyWindow: () => string;
    /** 42 hex characters: 168 bits, one per hour of the week. Null means always. */
    daypartMask: () => string | null;
    rateType: () => string | null;
    rateAmount: () => string | null;
    rateCurrency: () => string | null;
    contractNotes: () => string | null;
    impressions: () => number;
    clicks: () => number;
    /**
     * Computed on the server from the dates and the caps, so it disagrees with
     * `status` whenever an administrator's intent and reality have parted ways —
     * which is the moment worth showing them.
     */
    isLive: () => boolean;
    /**
     * Whether this campaign exists because a member submitted an advert rather
     * than because somebody sold one.
     *
     * A member submitting gets an advertiser and a campaign provisioned for them,
     * both named after them. Without this the two kinds sit in the same list
     * looking identical, and the only way to tell them apart is to already know
     * that mechanism exists.
     */
    isMemberSubmitted: () => boolean;
    createdAt: () => Date | null;
    updatedAt: () => Date | null;
    rules: () => TargetingRule[];
    advertiser: () => false | Advertiser | null;
    creatives: () => false | (Creative | undefined)[];
}
