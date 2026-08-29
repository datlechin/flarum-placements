import Stream from 'flarum/common/utils/Stream';
import CampaignListState from './CampaignListState';
import CreativeListState from './CreativeListState';
import AdvertiserListState from './AdvertiserListState';
/**
 * Everything the admin screens are looking at, held outside the page.
 *
 * The page is reached at `/extension/datlechin-placements?tab=…`, and moving
 * between tabs changes the route, which tears the page component down and
 * builds it again. State kept on the component would therefore be discarded
 * every time somebody clicked a tab: each visit would re-request the same list,
 * and a search or a page number would be lost on the way out and back.
 *
 * So it lives here, on `app`, for the lifetime of the admin session. This is
 * the same arrangement core's own extensions use -- `app.tagList`,
 * `app.extensionManager` -- rather than anything invented for this.
 */
export default class PlacementsState {
    readonly campaigns: CampaignListState;
    readonly advertisers: AdvertiserListState;
    /**
     * The review queue: waiting and turned-down, oldest first.
     *
     * Rejected creatives stay in view deliberately. A rejection is the start of a
     * conversation with whoever submitted it, and a queue that hid them would
     * make the reason unreachable the moment it was given.
     */
    readonly review: CreativeListState;
    /**
     * The creatives of whichever campaign is open, if one is.
     *
     * A single list re-pointed at each campaign rather than one per campaign: an
     * administrator looks at one campaign at a time, and keeping every campaign's
     * creatives would grow without bound over a long session.
     */
    readonly creatives: CreativeListState;
    /** Which campaign `creatives` currently holds, so it is not re-fetched. */
    protected creativesFor: string | null;
    constructor();
    /**
     * What the settings tab reports next to the retention field. Null until it
     * has been asked for, which is when that tab is first opened.
     */
    storage: Stream<StorageReport | null>;
    /**
     * How many creatives are waiting for a decision, for the badge on the nav.
     *
     * Its own request rather than a count of the loaded queue. The queue holds
     * rejected creatives as well as pending ones, and it holds one page of them,
     * so counting what is on screen would report the wrong number twice over --
     * and would under-report exactly when the queue is long enough for the badge
     * to matter.
     */
    pending: Stream<number | null>;
    countPending(): Promise<void>;
    /**
     * Point the creative list at a campaign, loading it if it is not already
     * there.
     */
    creativesOf(campaignId: string): CreativeListState;
    /**
     * Force the next `creativesOf` to re-fetch.
     *
     * Called after a creative is saved or deleted: the list is still pointed at
     * the same campaign, so nothing else would tell it the rows had changed.
     */
    forgetCreatives(): void;
}
export interface StorageReport {
    retentionDays: number;
    buckets: number;
    expiring: number;
    oldest: string | null;
    orphanedImages: number;
}
