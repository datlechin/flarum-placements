import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import m from 'mithril';

import ReviewTab from '../../src/admin/components/tabs/ReviewTab';
import PlacementsState from '../../src/admin/states/PlacementsState';
import Creative from '../../src/admin/models/Creative';
import Campaign from '../../src/admin/models/Campaign';
import { RESOURCE } from '../../src/admin/config';

beforeAll(() => {
  bootstrapAdmin();

  // Registered here rather than by the extension's initializer, which also
  // registers routes and an extension page this has no use for.
  app.store.models[RESOURCE.creatives] = Creative;
  app.store.models[RESOURCE.campaigns] = Campaign;
});

let saved: Array<Record<string, unknown>>;

function seed(rows: Array<{ id: number; name: string; status: string; createdAt: string; reviewReason?: string }>) {
  app.store.pushPayload({
    data: [{ id: '1', type: RESOURCE.campaigns, attributes: { name: 'Acme' } }],
  });

  app.store.pushPayload({
    data: rows.map((row) => ({
      id: String(row.id),
      type: RESOURCE.creatives,
      attributes: {
        name: row.name,
        type: 'image',
        status: row.status,
        createdAt: row.createdAt,
        reviewReason: row.reviewReason ?? null,
      },
      relationships: { campaign: { data: { id: '1', type: RESOURCE.campaigns } } },
    })),
  });

  const creatives = rows.map((row) => app.store.getById(RESOURCE.creatives, String(row.id))!);

  // Every creative records what it was asked to save instead of talking to a
  // server, so a decision can be asserted on rather than inferred from a
  // redraw.
  creatives.forEach((creative) => {
    (creative as any).save = (attributes: Record<string, unknown>) => {
      saved.push(attributes);

      return Promise.resolve(creative);
    };
  });

  app.store.find = () => Promise.resolve(creatives) as any;

  return creatives;
}

beforeEach(() => {
  saved = [];
  app.store.data = {};

  // The queue now reads a state held on `app` rather than loading into itself,
  // because the page is rebuilt on every tab change. A fresh one per test, so
  // one test's queue is never another's.
  app.placements = new PlacementsState();
});

/**
 * Waits for the load in `oninit` to settle, then redraws. Without it every
 * assertion runs against the loading indicator.
 *
 * A macrotask rather than `await Promise.resolve()`: the state's own `.then()`
 * is queued behind the one being awaited, so a single microtask tick leaves it
 * still loading.
 */
async function render() {
  const rendered = mq(ReviewTab, {});

  await new Promise((resolve) => setTimeout(resolve, 0));
  rendered.redraw();

  return rendered;
}

describe('the review queue', () => {
  it('shows nothing waiting when the queue is empty', async () => {
    seed([]);

    expect(await render()).toHaveElement('.Placeholder');
  });

  /**
   * A queue is worked from the front, and the thing that has been waiting
   * longest is the one somebody is waiting on. The server is asked for this
   * order as well; sorting again in the browser means a server that ignored
   * the sort cannot quietly reorder the queue.
   */
  it('lists the oldest first, whatever order the server sent', async () => {
    seed([
      { id: 1, name: 'Newest', status: 'pending', createdAt: '2026-03-01T00:00:00+00:00' },
      { id: 2, name: 'Oldest', status: 'pending', createdAt: '2026-01-01T00:00:00+00:00' },
      { id: 3, name: 'Middle', status: 'pending', createdAt: '2026-02-01T00:00:00+00:00' },
    ]);

    const names = Array.from((await render()).rootEl.querySelectorAll('.PlacementReview-name')).map((el: any) => el.textContent);

    expect(names).toEqual(['Oldest', 'Middle', 'Newest']);
  });

  /**
   * Rejected creatives stay in the list -- a rejection is the start of a
   * conversation -- so the queue holds both.
   */
  it('keeps the ones that were turned down alongside the ones still waiting', async () => {
    seed([
      { id: 1, name: 'Waiting', status: 'pending', createdAt: '2026-01-01T00:00:00+00:00' },
      { id: 2, name: 'Also waiting', status: 'pending', createdAt: '2026-01-02T00:00:00+00:00' },
      { id: 3, name: 'Turned down', status: 'rejected', createdAt: '2026-01-03T00:00:00+00:00', reviewReason: 'Not for us.' },
    ]);

    expect((await render()).find('.PlacementReview-item')).toHaveLength(3);
  });

  it('shows the reason a creative was turned down', async () => {
    seed([{ id: 1, name: 'Turned down', status: 'rejected', createdAt: '2026-01-01T00:00:00+00:00', reviewReason: 'Not for us.' }]);

    expect((await render()).rootEl.querySelector('.PlacementReview-reason')!.textContent).toBe('Not for us.');
  });

  /**
   * The status is a `Pill`, not a `Badge`. A `Badge` is a fixed 22-pixel circle
   * whose `.Badge-label` is `display: none` -- text in one spills out of it --
   * and two of the modifiers this used to ask for (`Badge--important`,
   * `Badge--warning`) do not exist in core at all, so the badges were
   * uncoloured as well as misshapen.
   */
  it('draws the status as a pill rather than a badge', async () => {
    seed([{ id: 1, name: 'Waiting', status: 'pending', createdAt: '2026-01-01T00:00:00+00:00' }]);

    const rendered = await render();

    expect(rendered).toHaveElement('.PlacementPill--warning');
    // `find` rather than `querySelector`: this DOM shim answers `undefined`
    // for no match, so a `toBeNull` would pass against a missing element and
    // against a shim that never matched anything at all.
    expect(rendered.find('.Badge')).toHaveLength(0);
  });

  it('approves without asking for anything', async () => {
    seed([{ id: 1, name: 'Waiting', status: 'pending', createdAt: '2026-01-01T00:00:00+00:00' }]);

    const rendered = await render();
    rendered.click('.PlacementReview-actions .Button--primary');

    expect(saved).toEqual([{ status: 'approved', reviewReason: null }]);
  });

  /**
   * A rejection with no reason is one somebody resubmits unchanged.
   */
  it('will not reject until a reason has been written', async () => {
    seed([{ id: 1, name: 'Waiting', status: 'pending', createdAt: '2026-01-01T00:00:00+00:00' }]);

    const rendered = await render();
    rendered.click('.PlacementReview-actions .Button:not(.Button--primary)');
    rendered.redraw();

    const confirm = rendered.rootEl.querySelector('.Button--danger') as HTMLButtonElement;

    expect(confirm.disabled).toBe(true);

    rendered.setValue('.PlacementReview-rejection .FormControl', 'The landing page asks for a password.');
    rendered.redraw();
    rendered.click('.Button--danger');

    expect(saved).toEqual([{ status: 'rejected', reviewReason: 'The landing page asks for a password.' }]);
  });

  /**
   * The reason is sent as null on an approval as well. The server clears it,
   * but leaving it out of the request means the store goes on showing the old
   * reason next to something that was accepted.
   */
  it('clears the reason when a rejected creative is approved', async () => {
    seed([{ id: 1, name: 'Turned down', status: 'rejected', createdAt: '2026-01-01T00:00:00+00:00', reviewReason: 'Not for us.' }]);

    const rendered = await render();
    rendered.click('.PlacementReview-actions .Button--primary');

    expect(saved).toEqual([{ status: 'approved', reviewReason: null }]);
  });
});

/**
 * The count on the tab beside the queue.
 *
 * It used to be counted from the loaded rows, which was wrong twice over: the
 * queue holds rejected creatives as well as pending ones, and it holds one page
 * of them -- so the number under-reported exactly when the queue was long
 * enough for it to matter.
 */
describe('the count of what is waiting', () => {
  it('is the server total for pending alone, not the length of the queue', async () => {
    let asked: any = null;

    app.store.find = (type: string, params: any) => {
      asked = params;

      const results: any = [];
      results.payload = { meta: { page: { total: 42 } } };

      return Promise.resolve(results);
    };

    await app.placements.countPending();

    expect(asked.filter).toEqual({ status: 'pending' });
    expect(app.placements.pending()).toBe(42);
  });

  it('falls back to the rows it got when the server sends no total', async () => {
    app.store.find = () => Promise.resolve([{}, {}] as any);

    await app.placements.countPending();

    expect(app.placements.pending()).toBe(2);
  });
});
