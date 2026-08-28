import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import m from 'mithril';

import ReviewSection from '../../src/admin/components/ReviewSection';
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
});

/**
 * Waits for the `find` promise in `oninit` to settle, then redraws. Without it
 * every assertion runs against the loading indicator.
 *
 * A macrotask rather than `await Promise.resolve()`: the component's own
 * `.then()` is queued behind the one being awaited, so a single microtask tick
 * leaves it still loading.
 */
async function render() {
  const rendered = mq(ReviewSection, {});

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
   * longest is the one somebody is waiting on.
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
   * conversation -- but only the pending ones are what anybody still has to
   * decide, so only they are counted.
   */
  it('counts only the ones nobody has decided on', async () => {
    seed([
      { id: 1, name: 'Waiting', status: 'pending', createdAt: '2026-01-01T00:00:00+00:00' },
      { id: 2, name: 'Also waiting', status: 'pending', createdAt: '2026-01-02T00:00:00+00:00' },
      { id: 3, name: 'Turned down', status: 'rejected', createdAt: '2026-01-03T00:00:00+00:00', reviewReason: 'Not for us.' },
    ]);

    const rendered = await render();

    expect(rendered.rootEl.querySelector('.Badge--important')!.textContent).toBe('2');
    expect(rendered.find('.PlacementReview-item')).toHaveLength(3);
  });

  it('shows the reason a creative was turned down', async () => {
    seed([{ id: 1, name: 'Turned down', status: 'rejected', createdAt: '2026-01-01T00:00:00+00:00', reviewReason: 'Not for us.' }]);

    expect((await render()).rootEl.querySelector('.PlacementReview-reason')!.textContent).toBe('Not for us.');
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
