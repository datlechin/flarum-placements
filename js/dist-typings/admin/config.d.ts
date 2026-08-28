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
