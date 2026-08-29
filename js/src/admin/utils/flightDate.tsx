import app from 'flarum/admin/app';
import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import type Mithril from 'mithril';

// Core extends *its* dayjs with this plugin, and an extension is bundled with
// a copy of its own -- `flarum/*` is external to the build, npm packages are
// not. `Translator.formatDateTime` formats using whichever object it is
// handed, so a date built from this copy met `ll` as an unknown token and
// dayjs handed the two letters straight back: the campaign list read "ll – ll"
// where the flight should have been. Extending is idempotent.
dayjs.extend(localizedFormat);

/**
 * One end of a campaign's flight, as a date somebody can act on.
 *
 * Not core's `humanTime`. That helper deliberately clamps anything in the
 * future to the present -- see the comment in `common/utils/humanTime`: it
 * exists to stop a post whose timestamp is a few seconds ahead of the reader's
 * clock reading "in a few seconds". Every date it is given is meant to be in
 * the past.
 *
 * A flight is the opposite case. `starts_at` and `ends_at` are routinely in the
 * future, and that clamp turned every one of them into "a few seconds ago": a
 * campaign scheduled to run for the next six weeks reported that it had already
 * finished, on both the campaign list and the campaign's own page.
 *
 * Absolute is also simply the more useful form here. Somebody reconciling an
 * invoice, or answering "when does this come off the forum?", wants the date,
 * not a distance from now.
 */
export default function flightDate(date: Date): Mithril.Vnode {
  const d = dayjs(date);

  // The same two formats core uses for dates it has decided not to humanise,
  // so a flight reads like the rest of the panel: the year is dropped within
  // this year and kept outside it.
  const format = d.isSame(dayjs(), 'year') ? 'core.lib.datetime_formats.humanTimeShort' : 'core.lib.datetime_formats.humanTimeLong';

  return (
    <time datetime={d.format()} title={app.translator.formatDateTime(d, 'core.lib.datetime_formats.fullTime')}>
      {app.translator.formatDateTime(d, format)}
    </time>
  );
}
