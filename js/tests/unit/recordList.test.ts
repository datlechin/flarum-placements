import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';

import CampaignListState from '../../src/admin/states/CampaignListState';
import Campaign from '../../src/admin/models/Campaign';
import { RESOURCE } from '../../src/admin/config';

/**
 * The paging behaviour the admin tables depend on.
 *
 * @see RecordListState
 */

beforeAll(() => {
  bootstrapAdmin();

  app.store.models[RESOURCE.campaigns] = Campaign;
});

/** Requests the stub has served, so the paging can be asserted on. */
let offsets: number[];

/**
 * A server holding `total` campaigns, answering with the slice asked for.
 */
function serve(total: () => number) {
  app.store.find = ((_type: string, params: any) => {
    const offset = params?.page?.offset ?? 0;
    const limit = params?.page?.limit ?? 50;

    offsets.push(offset);

    const rows: any = [];

    for (let i = offset; i < Math.min(offset + limit, total()); i++) {
      rows.push({ id: () => String(i + 1) });
    }

    rows.payload = { meta: { page: { total: total() }, perPage: limit } };

    return Promise.resolve(rows);
  }) as any;
}

beforeEach(() => {
  offsets = [];
  app.store.data = {};
});

describe('a paginated admin list', () => {
  it('asks for the page it is on, at the size the endpoints page at', async () => {
    serve(() => 120);

    const list = new CampaignListState();
    await list.ensureLoaded();

    expect(offsets).toEqual([0]);
    expect(list.total()).toBe(120);
    expect(list.items()).toHaveLength(50);

    await list.goto(3);

    expect(offsets).toEqual([0, 100]);
    expect(list.currentPage()).toBe(3);
  });

  it('only loads once however many times a tab asks it to', async () => {
    serve(() => 10);

    const list = new CampaignListState();

    await list.ensureLoaded();
    await list.ensureLoaded();
    await list.ensureLoaded();

    expect(offsets).toEqual([0]);
  });

  /**
   * The case that used to strand somebody. Deleting the only row on the last
   * page leaves that page valid arithmetic but past the end of a now-shorter
   * list, so the server answers with nothing -- and since the total dropped
   * too, the pager hides and the table shows "no campaigns yet" while a full
   * page of them sits behind, with no control on screen to reach it.
   */
  it('steps back a page when a delete empties the one being shown', async () => {
    let count = 51;
    serve(() => count);

    const list = new CampaignListState();
    await list.ensureLoaded();
    await list.goto(2);

    expect(list.items()).toHaveLength(1);

    // The row on page two is deleted.
    count = 50;
    offsets = [];

    await list.reload();

    expect(list.currentPage()).toBe(1);
    expect(list.items()).toHaveLength(50);
    // The empty page two was asked for first, then page one.
    expect(offsets).toEqual([50, 0]);
  });

  it('stays where it is when the page still has rows on it', async () => {
    let count = 60;
    serve(() => count);

    const list = new CampaignListState();
    await list.ensureLoaded();
    await list.goto(2);

    count = 55;
    offsets = [];

    await list.reload();

    expect(list.currentPage()).toBe(2);
    expect(offsets).toEqual([50]);
  });

  /**
   * Searching filters as you type. Routing that through the base class's
   * `refresh()` would call `clear()` and raise the initial-loading flag before
   * asking the server anything, so the table would blank and show a spinner on
   * every keystroke -- the opposite of what the loading overlay is for.
   */
  it('keeps the rows on screen while a filter change is in flight', async () => {
    serve(() => 60);

    const list = new CampaignListState();
    await list.ensureLoaded();

    expect(list.items()).toHaveLength(50);

    const inFlight = list.refreshParams({ sort: '-createdAt', filter: { q: 'summer' } }, 1);

    expect(list.isInitialLoading()).toBe(false);
    expect(list.items()).toHaveLength(50);
    expect(list.isLoading()).toBe(true);

    await inFlight;

    expect(list.isLoading()).toBe(false);
  });

  it('reports the default sort, so the column headings can show it', async () => {
    serve(() => 5);

    const list = new CampaignListState();
    await list.ensureLoaded();

    // Declared in `params`, not only in `requestParams()`: the headings read
    // their arrow from `getSort()`.
    expect(list.getSort()).toBe('-createdAt');
  });

  /**
   * A filter the screen applies to define itself is not somebody narrowing the
   * list, and the empty state has to read differently for the two. "Nothing
   * matches that" on a campaign that simply has no creatives yet is the wrong
   * sentence.
   */
  it('does not call a structural filter a narrowing', async () => {
    serve(() => 0);

    const list = new CampaignListState();
    list.structuralFilters = ['campaign'];

    await list.refreshParams({ filter: { campaign: '7' } }, 1);
    expect(list.isNarrowed()).toBe(false);

    list.filterBy('status', 'active');
    expect(list.isNarrowed()).toBe(true);
  });
});
