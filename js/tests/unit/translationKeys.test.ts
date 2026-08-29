import fs from 'fs';
import path from 'path';

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
const localePath = path.join(root, 'locale/en.yml');

const PREFIX = 'datlechin-placements.admin.';

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
  'campaigns.statuses': ['draft', 'scheduled', 'active', 'paused', 'archived'],
  'campaigns.tiers': ['sponsorship', 'guaranteed', 'standard', 'remnant', 'house'],
  'campaigns.frequency_windows': ['session', 'hour', 'day'],
  'campaigns.rate_types': ['cpm', 'cpc', 'flat', 'barter'],
  'campaigns.sources': ['direct', 'member'],
  'creatives.statuses': ['draft', 'pending', 'approved', 'rejected'],
  'daypart.days': ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
  reports: ['by_campaigns', 'by_creatives', 'by_placements', 'campaign', 'creative', 'slot'],
  slots: [
    'fallback_next_tier',
    'fallback_house',
    'fallback_passback',
    'fallback_collapse',
    'fallback_next_tier_help',
    'fallback_house_help',
    'fallback_passback_help',
    'fallback_collapse_help',
    'label_inherit',
    'label_always',
    'label_never',
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
    expect(defined.has('datlechin-placements.forum.label')).toBe(true);
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
