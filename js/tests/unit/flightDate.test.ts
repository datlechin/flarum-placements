import { readFileSync } from 'fs';

import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';

import flightDate from '../../src/admin/utils/flightDate';

/**
 * A campaign flight reads as a date, in both directions.
 *
 * Two separate failures live here, and both were on screen.
 *
 * Core's `humanTime` clamps anything in the future to the present, so that a
 * post timestamped a few seconds ahead of the reader's clock does not read "in
 * a few seconds". Applied to a flight -- where the end date is in the future
 * for every campaign currently running -- it reported that each one had
 * finished: "a month ago – a few seconds ago" for a campaign with six weeks
 * left on it.
 *
 * Then the replacement met the second: `ll` is a `localizedFormat` token, core
 * extends its own copy of dayjs with that plugin, and an extension is built
 * with a copy of its own. The unrecognised token came back as itself, so the
 * column read "ll – ll".
 */

beforeAll(() => {
  bootstrapAdmin();

  // The formats the helper asks for, exactly as core's locale file defines
  // them. `D MMM` is a plain dayjs pattern; `ll` needs the plugin.
  app.translator.addTranslations({
    'core.lib.datetime_formats.humanTimeShort': 'D MMM',
    'core.lib.datetime_formats.humanTimeLong': 'll',
    'core.lib.datetime_formats.fullTime': 'dddd, D MMMM YYYY HH:mm',
  });
});

const text = (date: Date) => mq({ view: () => flightDate(date) }).rootEl.textContent!.trim();

describe('a flight date', () => {
  it('names a date in the future rather than reporting it as past', () => {
    const soon = new Date();
    soon.setDate(soon.getDate() + 45);

    const rendered = text(soon);

    expect(rendered).not.toMatch(/ago/);
    expect(rendered).not.toMatch(/just now/i);
    // The day itself, so the reader can act on it.
    expect(rendered).toContain(String(soon.getDate()));
  });

  it('names a date in the past the same way', () => {
    const then = new Date();
    then.setDate(then.getDate() - 45);

    const rendered = text(then);

    expect(rendered).not.toMatch(/ago/);
    expect(rendered).toContain(String(then.getDate()));
  });

  it('resolves the localised format rather than printing the pattern', () => {
    // Another year, which is the branch that asks for `ll`.
    const old = new Date();
    old.setFullYear(old.getFullYear() - 2);

    const rendered = text(old);

    expect(rendered).not.toBe('ll');
    expect(rendered).not.toContain('ll');
    expect(rendered).toContain(String(old.getFullYear()));
  });

  /**
   * Asserted against the source, not the render, and deliberately so.
   *
   * The bug this guards is a property of the *bundle*: webpack gives the
   * extension its own copy of dayjs, separate from the one core extends. Jest
   * resolves every import to one shared module, and core's bootstrap extends
   * that one, so the plugin is present here whether or not this file asks for
   * it -- removing the import leaves all four assertions above passing while
   * the built admin panel prints `ll`.
   *
   * Checking the import is crude. It is also the only thing in this
   * environment that can tell the difference.
   */
  it('asks for the plugin its format depends on', () => {
    const source = readFileSync(new URL('../../src/admin/utils/flightDate.tsx', import.meta.url), 'utf8');

    // Anchored to the start of a line, so that commenting the call out is a
    // failure rather than a match on the comment's own text.
    expect(source).toMatch(/^import\s+localizedFormat\s+from\s+'dayjs\/plugin\/localizedFormat';$/m);
    expect(source).toMatch(/^dayjs\.extend\(localizedFormat\);$/m);
  });

  it('drops the year within the current year and keeps it outside', () => {
    const thisYear = new Date();
    thisYear.setMonth(0, 15);

    const otherYear = new Date();
    otherYear.setFullYear(otherYear.getFullYear() - 3);

    expect(text(thisYear)).not.toContain(String(thisYear.getFullYear()));
    expect(text(otherYear)).toContain(String(otherYear.getFullYear()));
  });
});
