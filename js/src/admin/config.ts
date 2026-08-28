import app from 'flarum/admin/app';
import type Mithril from 'mithril';

import type { SlotConfig } from '../common/types';
import type Advertiser from './models/Advertiser';

export const EXTENSION = 'datlechin-placement';

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
