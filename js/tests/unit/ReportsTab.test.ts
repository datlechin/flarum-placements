import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';

import ReportsTab from '../../src/admin/components/tabs/ReportsTab';

/**
 * The reports tab renders at all.
 *
 * It did not. Mithril refuses a fragment whose children are only partly
 * keyed, and this tab returned exactly that: unkeyed figures and chart
 * followed by four breakdown sections that each carried a `key`. It threw
 * `In fragments, vnodes must either all have keys or none have keys` and the
 * tab painted nothing but a spinner.
 *
 * Nothing caught it, for a reason worth writing down: the array is only built
 * once there is something to report, so the failure needed a forum with
 * statistics on it. Every fixture had none, took the "nothing yet" branch,
 * and passed. This test therefore asserts against a report with figures in
 * it -- an empty one would reproduce the old blind spot exactly.
 */

beforeAll(() => {
  bootstrapAdmin();
});

const REPORT = {
  since: '2026-08-01',
  days: 30,
  totals: { impressions: 128440, viewable: 96331, clicks: 1104, filtered: 5140 },
  daily: [
    { day: '2026-08-26', impressions: 4100, viewable: 3020, clicks: 36 },
    { day: '2026-08-27', impressions: 4420, viewable: 3260, clicks: 41 },
    { day: '2026-08-28', impressions: 3980, viewable: 2910, clicks: 33 },
  ],
  campaigns: [{ key: '1', name: 'Cloudforge annual sponsorship', impressions: 90120, viewable: 68110, clicks: 812 }],
  creatives: [{ key: '1', name: 'Cloudforge leaderboard', impressions: 60110, viewable: 45200, clicks: 540 }],
  placements: [{ key: 'index_above_list', name: 'index_above_list', impressions: 51220, viewable: 39110, clicks: 470 }],
  devices: [
    { key: 'desktop', name: 'desktop', impressions: 70110, viewable: 55020, clicks: 620 },
    { key: 'phone', name: 'phone', impressions: 48210, viewable: 33100, clicks: 401 },
  ],
};

/** Renders the tab with a report already in hand, bypassing the request. */
function render(report: unknown) {
  const tab = new (ReportsTab as any)();

  // The component fetches on `oninit`; the test is about what it draws, not
  // about how it asks, so the state is placed directly.
  tab.report = report;
  tab.loading = false;
  tab.sorts = {
    campaigns: { key: 'impressions', descending: true },
    creatives: { key: 'impressions', descending: true },
    placements: { key: 'impressions', descending: true },
    devices: { key: 'impressions', descending: true },
  };

  return mq({ view: () => tab.body() });
}

describe('the reports tab', () => {
  it('renders every breakdown when there is something to report', () => {
    const out = render(REPORT);

    // Four breakdowns, plus the figures and the chart that sit above them.
    expect(out.find('.PlacementDetail-section').length).toBe(4);
    expect(out.find('.PlacementTable').length).toBe(4);
    expect(out.should.have('.PlacementFigures'));

    // The row content actually arrived, rather than four empty tables.
    expect(out.find('tbody tr').length).toBe(5);

    const text = out.rootEl.textContent;
    expect(text).toContain('Cloudforge annual sponsorship');
    expect(text).toContain('index_above_list');
  });

  it('draws the chart from the daily series', () => {
    const out = render(REPORT);

    expect(out.should.have('.DeliveryChart'));
    // One area and one line for each of impressions and clicks.
    expect(out.find('.DeliveryChart-line').length).toBe(2);
  });

  it('says so rather than drawing an empty report', () => {
    const out = render({ ...REPORT, totals: { impressions: 0, viewable: 0, clicks: 0, filtered: 0 } });

    expect(out.find('.PlacementDetail-section').length).toBe(0);
    expect(out.should.have('.Placeholder'));
  });

  it('leaves a breakdown out when it has no rows, without disturbing the others', () => {
    const out = render({ ...REPORT, devices: [] });

    expect(out.find('.PlacementDetail-section').length).toBe(3);
  });
});
