import type Mithril from 'mithril';
import type { SlotConfig } from '../common/types';
import type Advertiser from './models/Advertiser';
export declare const EXTENSION = "datlechin-placements";
export declare const PERMISSIONS: {
    viewWithoutAds: string;
    manage: string;
    submit: string;
    authorHtml: string;
};
export declare const RESOURCE: {
    campaigns: string;
    creatives: string;
    advertisers: string;
    settings: string;
};
export declare const SETTING: {
    timezone: string;
    retentionDays: string;
    adsTxt: string;
    networkScripts: string;
};
/**
 * The tabs the admin screen is divided into.
 *
 * One job each. They used to be seven sections stacked on a single scroll, in a
 * fixed order, with no way to link to one: reading a report meant scrolling
 * past the whole review queue and the whole campaign list, and telling somebody
 * else where to look meant describing it.
 */
export declare const TABS: {
    readonly campaigns: "campaigns";
    readonly review: "review";
    readonly slots: "slots";
    readonly reports: "reports";
    readonly advertisers: "advertisers";
    readonly settings: "settings";
};
export type TabKey = (typeof TABS)[keyof typeof TABS];
export declare const DEFAULT_TAB: TabKey;
export declare const CAMPAIGN_STATUS: {
    readonly draft: "draft";
    readonly scheduled: "scheduled";
    readonly active: "active";
    readonly paused: "paused";
    readonly archived: "archived";
};
export declare const CREATIVE_STATUS: {
    readonly draft: "draft";
    readonly pending: "pending";
    readonly approved: "approved";
    readonly rejected: "rejected";
};
/**
 * Tier number to the name it is known by.
 *
 * The numbers are the priority order the serving engine uses, which is why they
 * are stored rather than the names: ordering by tier is ordering by importance.
 */
export declare const TIERS: Array<{
    value: number;
    key: string;
}>;
export declare function tierKey(tier: number): string;
/**
 * A link into one of the tabs.
 *
 * `app.route` puts anything it did not consume as a path segment into the query
 * string, so this needs no route of its own: the page stays where every Flarum
 * administrator already looks for an extension, and the tab is still somewhere
 * a link can point at.
 */
export declare function tabRoute(tab: TabKey, params?: Record<string, string | undefined>): string;
/**
 * The tab being shown.
 *
 * An unrecognised tab falls back rather than rendering nothing, so a stale
 * bookmark from before a tab was renamed opens the page instead of a blank.
 */
export declare function currentTab(): TabKey;
/**
 * The campaign whose own page is open within the campaigns tab, if any.
 */
export declare function currentCampaignId(): string | null;
export declare function trans(key: string, params?: Record<string, unknown>): Mithril.Children;
/**
 * The slots this forum has, as the server declared them.
 *
 * Placements are code, not rows, so there is nothing to fetch: they arrive on
 * the forum resource, gated on the manage permission.
 */
export declare function slots(): SlotConfig[];
/**
 * Advertisers already in the store.
 *
 * The page loads them alongside the campaigns, so the picker needs no request
 * of its own.
 */
export declare function advertisers(): Advertiser[];
export interface CreativeTypeConfig {
    key: string;
    label: string;
}
/**
 * The creative types this administrator may author.
 *
 * Read from the server rather than listed here, so a type another extension
 * registered appears — and so raw HTML is absent until both the permission and
 * the `config.php` flag are in place.
 */
export declare function creativeTypes(): CreativeTypeConfig[];
export interface DimensionConfig {
    key: string;
    label: string;
    operators: string[];
    serverSide: boolean;
    options: Array<{
        value: string;
        label: string;
    }>;
}
export declare function dimensions(): DimensionConfig[];
/**
 * Slots grouped the way the settings list renders them, in the order the
 * server sent them.
 */
export declare function slotsByGroup(): Array<[string, SlotConfig[]]>;
