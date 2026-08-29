import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { TABS, tabRoute, trans } from '../../config';
import DeliveryChart from '../DeliveryChart';
import Figures, { RATE_THRESHOLD, rate } from '../Figures';

interface Row {
  key: string;
  /** The campaign or creative's own name; the key itself for a slot. */
  name: string;
  impressions: number;
  viewable: number;
  clicks: number;
}

interface Report {
  since: string;
  days: number;
  totals: { impressions: number; viewable: number; clicks: number; filtered: number };
  daily: Array<{ day: string; impressions: number; viewable: number; clicks: number }>;
  campaigns: Row[];
  creatives: Row[];
  placements: Row[];
}

type Breakdown = 'campaigns' | 'creatives' | 'placements';

type SortKey = 'name' | 'impressions' | 'viewable' | 'clicks' | 'ctr';

const RANGES = [7, 30, 90, 365];

/**
 * What was delivered.
 *
 * The range is in the address, so "the last quarter" is a link somebody can
 * send.
 */
export default class ReportsTab extends Component {
  protected report: Report | null = null;
  protected loading = true;

  protected sorts: Record<Breakdown, { key: SortKey; descending: boolean }> = {
    campaigns: { key: 'impressions', descending: true },
    creatives: { key: 'impressions', descending: true },
    placements: { key: 'impressions', descending: true },
  };

  oninit(vnode: Mithril.Vnode<{}, this>) {
    super.oninit(vnode);

    this.load();
  }

  protected days(): number {
    const asked = Number(m.route.param('days'));

    return RANGES.includes(asked) ? asked : 30;
  }

  protected load(): void {
    this.loading = true;

    app
      .request<Report>({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/placements/report`, params: { days: this.days() } })
      .then((report) => {
        this.report = report;
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view(): Mithril.Children {
    const days = this.days();

    return (
      <div className="PlacementsTab">
        <div className="PlacementToolbar">
          <div className="PlacementToolbar-filters">
            <label className="PlacementToolbar-choice">
              <span className="PlacementToolbar-choiceLabel">{trans('reports.range_label')}</span>
              <Select
                value={String(days)}
                options={Object.fromEntries(RANGES.map((d) => [String(d), extractText(trans('reports.last_days', { count: d }))]))}
                // Through the route rather than component state, so the range
                // is part of the address and survives being linked to.
                onchange={(value: string) => m.route.set(tabRoute(TABS.reports, { days: value }))}
              />
            </label>
          </div>

          <div className="PlacementToolbar-actions">
            <a className="Button" href={`${app.forum.attribute('apiUrl')}/placements/report?format=csv&days=${days}`} download>
              <Icon name="fas fa-download" className="Button-icon" />
              <span className="Button-label">{trans('reports.download')}</span>
            </a>
          </div>
        </div>

        {this.loading ? <LoadingIndicator /> : this.body()}
      </div>
    );
  }

  protected body(): Mithril.Children {
    if (!this.report) return <Placeholder text={trans('reports.unavailable')} />;

    const { totals } = this.report;

    if (!totals.impressions && !totals.clicks) {
      return <Placeholder text={trans('reports.none')} />;
    }

    return [
      <Figures
        figures={[
          { label: trans('reports.impressions'), value: totals.impressions.toLocaleString() },
          { label: trans('reports.viewable'), value: this.rate(totals.viewable, totals.impressions), help: trans('reports.viewable_help') },
          { label: trans('reports.clicks'), value: totals.clicks.toLocaleString() },
          { label: trans('reports.ctr'), value: this.rate(totals.clicks, totals.impressions), help: trans('reports.ctr_help') },
          // Shown even when zero: an advertiser asking why their impressions
          // dropped by a fifth deserves an answer.
          { label: trans('reports.filtered'), value: totals.filtered.toLocaleString(), help: trans('reports.filtered_help') },
        ]}
      />,

      <DeliveryChart
        labels={this.report.daily.map((d) => d.day)}
        series={[
          { label: String(trans('reports.impressions')), colour: 'var(--primary-color)', points: this.report.daily.map((d) => d.impressions) },
          { label: String(trans('reports.clicks')), colour: 'var(--control-success-color)', points: this.report.daily.map((d) => d.clicks) },
        ]}
      />,

      // Campaigns first: it is the breakdown somebody reconciling an invoice
      // opens the page for.
      this.table('campaigns', this.report.campaigns),
      this.table('creatives', this.report.creatives),
      this.table('placements', this.report.placements),
    ];
  }

  /**
   * A percentage, or a dash when there is not enough to divide by.
   *
   * Shared with the campaign pages rather than defined again here, so the same
   * two numbers cannot produce a rate on one screen and a dash on another.
   */
  protected rate(part: number, whole: number): Mithril.Children {
    return rate(part, whole);
  }

  /**
   * The breakdowns are sorted in the browser rather than by the server.
   *
   * The whole breakdown arrives in one payload -- the endpoint caps each at a
   * hundred rows -- so there is nothing to fetch to reorder it, and asking the
   * server would trade an instant reorder for a round trip and a spinner.
   */
  protected sorted(breakdown: Breakdown, rows: Row[]): Row[] {
    const { key, descending } = this.sorts[breakdown];

    const value = (row: Row): number | string => {
      switch (key) {
        case 'name':
          return row.name || row.key;
        case 'ctr':
          // A row below the threshold has no rate, and sorting it as zero puts
          // it among the genuinely worst performers. Negative keeps those rows
          // together at one end instead.
          return row.impressions < RATE_THRESHOLD ? -1 : row.clicks / row.impressions;
        default:
          return row[key];
      }
    };

    return [...rows].sort((a, b) => {
      const left = value(a);
      const right = value(b);

      const comparison = typeof left === 'string' ? left.localeCompare(right as string) : (left as number) - (right as number);

      return descending ? -comparison : comparison;
    });
  }

  protected table(breakdown: Breakdown, rows: Row[]): Mithril.Children {
    if (!rows.length) return null;

    const nameHeading = breakdown === 'placements' ? 'slot' : breakdown === 'campaigns' ? 'campaign' : 'creative';

    const columns: Array<{ key: SortKey; label: Mithril.Children; number: boolean }> = [
      { key: 'name', label: trans(`reports.${nameHeading}`), number: false },
      { key: 'impressions', label: trans('reports.impressions'), number: true },
      { key: 'viewable', label: trans('reports.viewable'), number: true },
      { key: 'clicks', label: trans('reports.clicks'), number: true },
      { key: 'ctr', label: trans('reports.ctr'), number: true },
    ];

    return (
      <div className="PlacementDetail-section" key={breakdown}>
        <h4>{trans(`reports.by_${breakdown}`)}</h4>

        <div className="Table-container">
          <table className="Table PlacementTable">
            <thead>
              <tr>
                {columns.map((column) => (
                  <th
                    key={column.key}
                    className={column.number ? 'PlacementTable-number' : undefined}
                    aria-sort={this.ariaSort(breakdown, column.key)}
                  >
                    <Button
                      className="Button Button--text PlacementTable-sort"
                      onclick={() => this.sortBy(breakdown, column.key)}
                      aria-label={extractText(trans('lists.sort_by', { column: column.label }))}
                    >
                      {column.label}
                      <Icon name={this.sortIcon(breakdown, column.key)} className="PlacementTable-sortIcon" />
                    </Button>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {this.sorted(breakdown, rows).map((row) => (
                <tr key={row.key}>
                  <td>
                    {/* A slot's key is the thing an administrator recognises
                        and is worth showing as code; a campaign or creative id
                        is not, and printing `7` where a name belongs is what
                        made this table unreadable. */}
                    {breakdown === 'placements' ? (
                      <code className="PlacementTable-code">{row.key}</code>
                    ) : (
                      row.name || <code className="PlacementTable-code">{row.key}</code>
                    )}
                  </td>
                  <td className="PlacementTable-number">{row.impressions.toLocaleString()}</td>
                  <td className="PlacementTable-number">{this.rate(row.viewable, row.impressions)}</td>
                  <td className="PlacementTable-number">{row.clicks.toLocaleString()}</td>
                  <td className="PlacementTable-number">{this.rate(row.clicks, row.impressions)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }

  protected sortBy(breakdown: Breakdown, key: SortKey): void {
    const current = this.sorts[breakdown];

    this.sorts[breakdown] =
      current.key === key
        ? { key, descending: !current.descending }
        : // A new column starts on the reading somebody wants first: biggest
          // number, or alphabetical for a name.
          { key, descending: key !== 'name' };
  }

  protected sortIcon(breakdown: Breakdown, key: SortKey): string {
    const current = this.sorts[breakdown];

    if (current.key !== key) return 'fas fa-sort';

    return current.descending ? 'fas fa-sort-down' : 'fas fa-sort-up';
  }

  protected ariaSort(breakdown: Breakdown, key: SortKey): string {
    const current = this.sorts[breakdown];

    if (current.key !== key) return 'none';

    return current.descending ? 'descending' : 'ascending';
  }
}
