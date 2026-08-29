import app from 'flarum/admin/app';
import type Mithril from 'mithril';

import type { SlotConfig } from '../common/types';
import type Advertiser from './models/Advertiser';

export const EXTENSION = 'datlechin-placements';

export const PERMISSIONS = {
  viewWithoutAds: `${EXTENSION}.viewWithoutAds`,
  manage: `${EXTENSION}.manage`,
  submit: `${EXTENSION}.submit`,
  authorHtml: `${EXTENSION}.authorHtml`,
};

export const RESOURCE = {
  campaigns: 'placement-campaigns',
  creatives: 'placement-creatives',
  advertisers: 'placement-advertisers',
  settings: 'placement-settings',
};

export const SETTING = {
  timezone: `${EXTENSION}.timezone`,
  retentionDays: `${EXTENSION}.retention_days`,
  adsTxt: `${EXTENSION}.ads_txt`,
  networkScripts: `${EXTENSION}.network_scripts`,
};

/**
 * The tabs the admin screen is divided into.
 *
 * One job each, and each one addressable: a report is a link somebody can
 * send rather than a place described over somebody's shoulder.
 */
export const TABS = {
  campaigns: 'campaigns',
  review: 'review',
  slots: 'slots',
  reports: 'reports',
  advertisers: 'advertisers',
  settings: 'settings',
} as const;

export type TabKey = (typeof TABS)[keyof typeof TABS];

export const DEFAULT_TAB: TabKey = TABS.campaigns;

export const CAMPAIGN_STATUS = {
  draft: 'draft',
  scheduled: 'scheduled',
  active: 'active',
  paused: 'paused',
  archived: 'archived',
} as const;

/**
 * A slot's own settings, as the server defines them.
 *
 * Named here rather than spelled out at each `<Select>` because three
 * different things have to agree on them: the control that offers the
 * choices, the summary that turns a stored value into a label, and the test
 * that checks every one of those labels exists. When the summary interpolated
 * a bare column value into a translation key with nothing tying it to this
 * list, a value the UI never offered rendered its key on screen.
 */
export const FALLBACK = {
  next_tier: 'next_tier',
  house: 'house',
  passback: 'passback',
  collapse: 'collapse',
} as const;

export const ROTATION = {
  random: 'random',
  sticky: 'sticky',
} as const;

export const LABEL_MODE = {
  inherit: 'inherit',
  always: 'always',
  never: 'never',
} as const;

/**
 * Whether a value stored against a slot is one this build knows how to name.
 *
 * A row can hold anything the database accepted: a configuration imported from
 * a newer version, or a column written by hand. Naming only what is known
 * keeps an unrecognised value out of the summary rather than printing a
 * translation key where a label should be.
 */
export function isKnown<T extends Record<string, string>>(set: T, value: string | null): value is T[keyof T] {
  return value !== null && Object.prototype.hasOwnProperty.call(set, value);
}

/**
 * The status a pause or resume control would move a campaign to, or null when
 * neither applies.
 *
 * Stopping a running campaign is what a sponsor asks for by email in the
 * middle of a week, and it was five interactions through a modal to change one
 * field. Draft, scheduled and archived campaigns get no control: pausing
 * something that is not running says nothing.
 */
export function toggledStatus(status: string): string | null {
  if (status === CAMPAIGN_STATUS.active) return CAMPAIGN_STATUS.paused;
  if (status === CAMPAIGN_STATUS.paused) return CAMPAIGN_STATUS.active;

  return null;
}

export const CREATIVE_STATUS = {
  draft: 'draft',
  pending: 'pending',
  approved: 'approved',
  rejected: 'rejected',
} as const;

/**
 * Tier number to the name it is known by.
 *
 * The numbers are the priority order the serving engine uses, which is why they
 * are stored rather than the names: ordering by tier is ordering by importance.
 */
export const TIERS: Array<{ value: number; key: string }> = [
  { value: 10, key: 'sponsorship' },
  { value: 30, key: 'guaranteed' },
  { value: 50, key: 'standard' },
  { value: 70, key: 'remnant' },
  { value: 90, key: 'house' },
];

export function tierKey(tier: number): string {
  return TIERS.find((entry) => entry.value === tier)?.key ?? String(tier);
}

/**
 * A link into one of the tabs.
 *
 * `app.route` puts anything it did not consume as a path segment into the query
 * string, so this needs no route of its own: the page stays where every Flarum
 * administrator already looks for an extension, and the tab is still somewhere
 * a link can point at.
 */
export function tabRoute(tab: TabKey, params: Record<string, string | undefined> = {}): string {
  return app.route('extension', { id: EXTENSION, tab, ...params });
}

/**
 * The tab being shown.
 *
 * An unrecognised tab falls back rather than rendering nothing, so a stale
 * bookmark from before a tab was renamed opens the page instead of a blank.
 */
export function currentTab(): TabKey {
  const tab = m.route.param('tab');

  return (Object.values(TABS) as string[]).includes(tab) ? (tab as TabKey) : DEFAULT_TAB;
}

/**
 * The campaign whose own page is open within the campaigns tab, if any.
 */
export function currentCampaignId(): string | null {
  return m.route.param('campaign') ?? null;
}

export function trans(key: string, params: Record<string, unknown> = {}): Mithril.Children {
  return app.translator.trans(`${EXTENSION}.admin.${key}`, params);
}

/**
 * The slots this forum has, as the server declared them.
 *
 * Placements are code, not rows, so there is nothing to fetch: they arrive on
 * the forum resource, gated on the manage permission.
 */
export function slots(): SlotConfig[] {
  return (app.forum.attribute('placementSlots') as SlotConfig[] | undefined) ?? [];
}

/**
 * Advertisers already in the store.
 *
 * The page loads them alongside the campaigns, so the picker needs no request
 * of its own.
 */
export function advertisers(): Advertiser[] {
  return app.store.all<Advertiser>(RESOURCE.advertisers);
}

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
export function creativeTypes(): CreativeTypeConfig[] {
  return (app.forum.attribute('placementCreativeTypes') as CreativeTypeConfig[] | undefined) ?? [];
}

export interface DimensionConfig {
  key: string;
  label: string;
  operators: string[];
  serverSide: boolean;
  options: Array<{ value: string; label: string }>;
}

export function dimensions(): DimensionConfig[] {
  return (app.forum.attribute('placementDimensions') as DimensionConfig[] | undefined) ?? [];
}

/**
 * Slots grouped the way the settings list renders them, in the order the
 * server sent them.
 */
export function slotsByGroup(): Array<[string, SlotConfig[]]> {
  const groups = new Map<string, SlotConfig[]>();

  slots().forEach((slot) => {
    groups.set(slot.group, [...(groups.get(slot.group) ?? []), slot]);
  });

  return [...groups.entries()];
}
