import fs from 'fs';
import path from 'path';

import { CAMPAIGN_STATUS, FALLBACK, LABEL_MODE, ROTATION } from '../../src/admin/config';

/**
 * Every translation key the admin screens ask for has to exist.
 *
 * A missing key is silent in the worst way: Flarum renders the key itself, so
 * a button reads `datlechin-placements.admin.campaigns.tab_identity` and the
 * build, the type check and every other test still pass. Renaming a section of
 * `en.yml`, or adding a control and forgetting its label, both produce exactly
 * that and nothing catches it.
 *
 * The keys built by string interpolation cannot be found this way, so the
 * families they draw from are listed explicitly below.
 */

// Jest runs these as ES modules, so there is no `__dirname`. The working
// directory is the `js` folder, both locally and in CI.
const root = path.resolve(process.cwd(), '..');
const adminSrc = path.join(root, 'js/src/admin');
const commonSrc = path.join(root, 'js/src/common');
const forumSrc = path.join(root, 'js/src/forum');
const localePath = path.join(root, 'locale/en.yml');

const PREFIX = 'datlechin-placements.admin.';

/**
 * The namespaces each frontend is actually served.
 *
 * Flarum builds one locale bundle per frontend and filters it with
 * `/^.+(?:\.|::)(?:<frontend>|lib)\./` -- see `Flarum\Frontend\AddTranslations`.
 * So the admin bundle contains `…admin.` and `…lib.` keys and nothing else: a
 * `forum.` key asked for in the admin panel is not merely untranslated, it was
 * never sent, and Flarum renders the key itself on screen.
 *
 * That is what put `datlechin-placements.forum.label` into the creative
 * preview and the review queue as literal text. It type-checked, it built, and
 * every test passed, because the key does exist -- just not anywhere the admin
 * frontend can see it.
 */
const REACHABLE: Record<string, string[]> = {
  admin: ['admin', 'lib'],
  forum: ['forum', 'lib'],
  // Rendered by both, so only the shared namespace is safe.
  common: ['lib'],
};

/**
 * Records a key the way the call site meant it.
 *
 * The regex matches `app.translator.trans('…')` as well as the extension's own
 * `trans('…')` helper, and those take different kinds of key: the helper takes
 * a key relative to this extension's admin namespace, the translator takes a
 * complete one. Prefixing both is how an earlier version of this test invented
 * `datlechin-placements.admin.datlechin-placements.forum.label` and then
 * reported it missing. Core's own keys are somebody else's file to check.
 */
function add(keys: Set<string>, key: string): void {
  if (key.startsWith('core.')) return;

  keys.add(key.startsWith('datlechin-placements.') ? key : PREFIX + key);
}

function sources(dir: string, found: string[] = []): string[] {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);

    if (entry.isDirectory()) sources(full, found);
    else if (/\.tsx?$/.test(entry.name)) found.push(full);
  }

  return found;
}

/**
 * The keys `en.yml` defines, flattened to dotted paths.
 *
 * A two-space-indented subset of YAML, which is all this file is and all
 * Flarum's own locale files are. Written out rather than pulled in as a
 * dependency, and checked below against keys known to be present and known to
 * be absent -- a parser that quietly matched nothing would make this whole
 * test report everything as missing, and one that quietly matched everything
 * would make it prove nothing at all.
 */
function definedKeys(): Set<string> {
  const keys = new Set<string>();
  const stack: string[] = [];

  for (const line of fs.readFileSync(localePath, 'utf8').split('\n')) {
    if (!line.trim() || line.trim().startsWith('#')) continue;

    // Continuation lines of a folded block have no `key:` of their own.
    const match = line.match(/^(\s*)([A-Za-z0-9_.-]+):(.*)$/);
    if (!match) continue;

    const [, indent, key, rest] = match;
    const depth = indent.length / 2;

    stack.length = depth;
    stack[depth] = key;

    // A key with a value on the same line is a leaf; `>` opens a folded
    // scalar, which is also a leaf.
    if (rest.trim() !== '') keys.add(stack.slice(0, depth + 1).join('.'));
  }

  return keys;
}

/**
 * Keys assembled at runtime, which no scan of the source can find.
 *
 * Each entry is a family and the values it is indexed by. They come from the
 * same lists the code draws from, so adding a status or a tier without a label
 * fails here.
 */
const DYNAMIC: Record<string, string[]> = {
  // Taken from the constant the screens themselves read, not written out
  // again here. A hand-copied list is only ever checked against itself: this
  // file claimed the label modes were `inherit`, `always` and `never`, the
  // code agreed, and nothing connected either to the value a row could
  // actually hold.
  'campaigns.statuses': Object.values(CAMPAIGN_STATUS),
  'campaigns.tiers': ['sponsorship', 'guaranteed', 'standard', 'remnant', 'house'],
  'campaigns.frequency_windows': ['session', 'hour', 'day'],
  'campaigns.rate_types': ['cpm', 'cpc', 'flat', 'barter'],
  'campaigns.sources': ['direct', 'member'],
  'creatives.statuses': ['draft', 'pending', 'approved', 'rejected'],
  'diagnose.reasons': [
    'eligible',
    'slot_unknown',
    'slot_disabled',
    'not_assigned',
    'type_not_accepted',
    'not_approved',
    'no_campaign',
    'campaign_not_running',
    'not_in_plan',
    'campaign_not_live',
    'campaign_capped',
    'outside_daypart',
    'targeted_out',
    'paced',
    'unavailable',
  ],
  'daypart.days': ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
  reports: ['by_campaigns', 'by_creatives', 'by_placements', 'campaign', 'creative', 'slot'],
  slots: [
    // Every fallback needs both a label and the help text under the select,
    // and every label mode and rotation needs a label. Derived so that adding
    // one to the constant fails here until it has been named.
    ...Object.values(FALLBACK).map((value) => `fallback_${value}`),
    ...Object.values(FALLBACK).map((value) => `fallback_${value}_help`),
    ...Object.values(LABEL_MODE).map((value) => `label_${value}`),
    ...Object.values(ROTATION).map((value) => `rotation_${value}`),
    'reserve_phone',
    'reserve_tablet',
    'reserve_desktop',
  ],
  // Reached through the `field()` and `group()` helpers, which build the key
  // from a bare word and so hide it from the scan.
  campaigns: ['name', 'status', 'advertiser', 'tier', 'starts_at', 'ends_at', 'max_impressions', 'max_clicks', 'pacing', 'rate_type', 'contract_notes'],
  creatives: ['name', 'type', 'weight', 'status', 'destination_url', 'label_override', 'variant_group'],
};

describe('the admin translations', () => {
  const defined = definedKeys();

  it('parses the locale file it is checking against', () => {
    // Without this, a parser that matched nothing would report every key as
    // missing, and a parser that matched everything would pass whatever the
    // file said.
    expect(defined.has('datlechin-placements.admin.campaigns.title')).toBe(true);
    expect(defined.has('datlechin-placements.admin.tabs.campaigns')).toBe(true);
    expect(defined.has('datlechin-placements.lib.label')).toBe(true);
    expect(defined.has('datlechin-placements.admin.campaigns.no_such_key')).toBe(false);
    // A parent with children is not itself a translation.
    expect(defined.has('datlechin-placements.admin.campaigns')).toBe(false);
  });

  it('defines every key the admin screens ask for by name', () => {
    const used = new Set<string>();

    for (const file of sources(adminSrc)) {
      const code = fs.readFileSync(file, 'utf8');

      // Only literal calls. An interpolated one is covered by DYNAMIC.
      for (const match of code.matchAll(/\btrans\(\s*'([^'${}]+)'/g)) add(used, match[1]);
      for (const match of code.matchAll(/\btrans\(\s*`([^`${}]+)`/g)) add(used, match[1]);
    }

    // A scan that found nothing would pass this test without checking a thing.
    expect(used.size).toBeGreaterThan(100);

    expect([...used].filter((key) => !defined.has(key)).sort()).toEqual([]);
  });

  it('only asks each frontend for keys that frontend is served', () => {
    const wrong: string[] = [];
    let scanned = 0;

    for (const [frontend, dir] of [
      ['admin', adminSrc],
      ['forum', forumSrc],
      ['common', commonSrc],
    ] as const) {
      const allowed = REACHABLE[frontend];

      for (const file of sources(dir)) {
        const code = fs.readFileSync(file, 'utf8');

        // Anchored on `trans(` rather than on the string alone: the extension
        // prefix is also how storage keys and route names are namespaced
        // (`datlechin-placements.seen`, `datlechin-placements.submissions`),
        // and those are not translations to look up.
        for (const match of code.matchAll(/\btrans\(\s*'(datlechin-placements\.[a-z0-9_.-]+)'/g)) {
          scanned++;

          const namespace = match[1].split('.')[1];

          if (!allowed.includes(namespace)) {
            wrong.push(`${path.relative(root, file)} asks for ${match[1]}, which the ${frontend} frontend is not served`);
          }
        }
      }
    }

    // A scan that matched nothing would pass this without checking anything.
    expect(scanned).toBeGreaterThan(5);

    expect(wrong.sort()).toEqual([]);
  });

  it('defines every key the admin screens assemble at runtime', () => {
    const missing: string[] = [];

    for (const [family, values] of Object.entries(DYNAMIC)) {
      for (const value of values) {
        const key = `${PREFIX}${family}.${value}`;

        if (!defined.has(key)) missing.push(key);
      }
    }

    expect(missing).toEqual([]);
  });
});
