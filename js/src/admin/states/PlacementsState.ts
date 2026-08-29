import app from 'flarum/admin/app';
import Stream from 'flarum/common/utils/Stream';
import type { ApiResponsePlural } from 'flarum/common/Store';

import CampaignListState from './CampaignListState';
import CreativeListState from './CreativeListState';
import AdvertiserListState from './AdvertiserListState';
import { CREATIVE_STATUS, RESOURCE } from '../config';
import type Creative from '../models/Creative';

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
  public readonly campaigns = new CampaignListState();
  public readonly advertisers = new AdvertiserListState();

  /**
   * The review queue: waiting and turned-down, oldest first.
   *
   * Rejected creatives stay in view deliberately. A rejection is the start of a
   * conversation with whoever submitted it, and a queue that hid them would
   * make the reason unreachable the moment it was given.
   */
  public readonly review = new CreativeListState({
    filter: { status: [CREATIVE_STATUS.pending, CREATIVE_STATUS.rejected] },
    sort: 'createdAt',
  });

  /**
   * The creatives of whichever campaign is open, if one is.
   *
   * A single list re-pointed at each campaign rather than one per campaign: an
   * administrator looks at one campaign at a time, and keeping every campaign's
   * creatives would grow without bound over a long session.
   */
  public readonly creatives = new CreativeListState();

  /** Which campaign `creatives` currently holds, so it is not re-fetched. */
  protected creativesFor: string | null = null;

  constructor() {
    // Neither of these filters is something somebody chose, so neither should
    // turn an empty result into "nothing matches that".
    this.review.structuralFilters = ['status'];
    this.creatives.structuralFilters = ['campaign'];
  }

  /**
   * The setting streams the settings tab binds to.
   *
   * `AdminPage.setting()` memoises them on the page, and the page is rebuilt
   * on every tab change -- so typing into `ads.txt` and then clicking another
   * tab silently discarded the edit. They live here and the page adopts them.
   */
  public readonly settingStreams: Record<string, Stream<string>> = {};

  /**
   * What the settings tab reports next to the retention field. Null until it
   * has been asked for, which is when that tab is first opened.
   */
  public storage: Stream<StorageReport | null> = Stream(null);

  /**
   * How many creatives are waiting for a decision, for the badge on the nav.
   *
   * Its own request rather than a count of the loaded queue. The queue holds
   * rejected creatives as well as pending ones, and it holds one page of them,
   * so counting what is on screen would report the wrong number twice over --
   * and would under-report exactly when the queue is long enough for the badge
   * to matter.
   */
  public pending: Stream<number | null> = Stream(null);

  public countPending(): Promise<void> {
    return app.store
      .find<Creative[]>(RESOURCE.creatives, {
        filter: { status: CREATIVE_STATUS.pending },
        // One row, because only the total is wanted. Zero is not allowed by
        // every driver's `LIMIT`, and the total comes back either way.
        page: { limit: 1 },
      })
      .then((results) => {
        const payload = (results as unknown as ApiResponsePlural<Creative>).payload;

        this.pending(payload?.meta?.page?.total ?? results.length);

        m.redraw();
      });
  }

  public creativesOf(campaignId: string): CreativeListState {
    if (this.creativesFor !== campaignId) {
      this.creativesFor = campaignId;
      this.creatives.refreshParams({ filter: { campaign: campaignId }, sort: '-createdAt' }, 1);
    }

    return this.creatives;
  }

  /**
   * Force the next `creativesOf` to re-fetch.
   *
   * Called after a creative is saved or deleted: the list is still pointed at
   * the same campaign, so nothing else would tell it the rows had changed.
   */
  public forgetCreatives(): void {
    this.creativesFor = null;
  }
}

export interface StorageReport {
  retentionDays: number;
  buckets: number;
  expiring: number;
  oldest: string | null;
  orphanedImages: number;
}
