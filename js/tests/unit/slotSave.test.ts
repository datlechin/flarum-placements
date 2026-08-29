import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';

import SlotsTab from '../../src/admin/components/tabs/SlotsTab';
import PlacementSetting from '../../src/admin/models/PlacementSetting';
import Creative from '../../src/admin/models/Creative';
import { RESOURCE } from '../../src/admin/config';

/**
 * Configuring a slot for the first time.
 *
 * A slot with no row is default-configured, not unconfigured, so the resource
 * has no create endpoint: `find()` hands back an unsaved model for any key the
 * registry knows and the first `PATCH` writes the row.
 *
 * Getting the method wrong is silent. Core's `Model.save()` picks `POST` when
 * `exists` is false -- which it is for anything the store did not receive from
 * the server -- while still building the URL from the id, so the request went
 * to `POST /placement-settings/{key}`, matched no route, and was swallowed by
 * the component's own `catch`. The switch sprang back and nothing was said.
 * Every slot on every forum was in that state until it had been saved once,
 * which is to say it could never be saved at all.
 *
 * @see tests/integration/ConfiguresASlotTest.php for the server half.
 */

let requests: Array<{ method: string; url: string }>;

beforeAll(() => {
  bootstrapAdmin();

  app.store.models[RESOURCE.settings] = PlacementSetting;
  app.store.models[RESOURCE.creatives] = Creative;
});

beforeEach(() => {
  requests = [];
  app.store.data = {};

  // `bootstrapAdmin` loads a forum resource but never boots the application,
  // so `app.forum` is not populated. Both the slot list and core's
  // `Model.save()` read attributes off it, so it is built here.
  app.forum = app.store.createRecord('forums');
  app.forum.pushData({
    id: '1',
    type: 'forums',
    attributes: {
      apiUrl: 'https://forum.test/api',
      baseUrl: 'https://forum.test',
      // One slot, declared the way the server declares them.
      placementSlots: [
        {
          key: 'index_above_list',
          group: 'index',
          label: 'Above the list',
          description: 'Above the discussion list.',
          allowedTypes: [],
          maxFill: 1,
          repeating: false,
          recommendedSize: null,
          reserve: {},
          fallback: 'next_tier',
          passbackCreativeId: null,
          labelMode: 'inherit',
          everyN: null,
          repeatLimit: null,
          sortOrder: 0,
        },
      ],
    },
  });

  // Nothing configured yet, and no creatives for the passback picker.
  app.store.find = () => Promise.resolve([]) as any;

  app.request = (options: any) => {
    requests.push({ method: options.method, url: options.url });

    // The shape `Model.save()` pushes back into the store.
    return Promise.resolve({
      data: { type: RESOURCE.settings, id: 'index_above_list', attributes: { enabled: false } },
    }) as any;
  };
});

async function render() {
  const rendered = mq(SlotsTab, {});

  await new Promise((resolve) => setTimeout(resolve, 0));
  rendered.redraw();

  return rendered;
}

describe('configuring a slot that has never been configured', () => {
  it('sends a PATCH to the slot, not a POST', async () => {
    const rendered = await render();

    // The enable switch on the only slot. Core's `Checkbox` listens on
    // `change`, not `click`, so a plain `click()` here fires nothing and the
    // assertion below would pass against a component that never saved at all.
    rendered.trigger('.PlacementSlots-switch input', 'change');

    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(requests).toHaveLength(1);
    expect(requests[0].method).toBe('PATCH');
    expect(requests[0].url).toMatch(/\/placement-settings\/index_above_list$/);
  });

  /**
   * The trap this exists for, asserted directly so the reason the line above
   * matters does not have to be taken on trust: the same model without
   * `exists` set produces the request that never worked.
   */
  it('would send an unroutable POST without the record being marked as existing', async () => {
    const record: any = app.store.createRecord(RESOURCE.settings);

    record.pushData({ id: 'index_above_list', type: RESOURCE.settings });

    expect(record.exists).toBe(false);

    await record.save({ enabled: false });

    expect(requests[0].method).toBe('POST');
    expect(requests[0].url).toMatch(/\/placement-settings\/index_above_list$/);
  });
});
