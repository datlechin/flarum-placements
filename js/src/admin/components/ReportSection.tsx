import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';

import { trans } from '../config';
import DeliveryChart from './DeliveryChart';

interface Row {
  key: string;
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

/**
 * Below how many impressions a rate is not reported.
 *
 * One click on eight impressions is not a 12.5% click-through rate, it is one
 * click. Printing the number anyway is how an administrator ends up quoting it
 * to a sponsor.
 */
const RATE_THRESHOLD = 1000;

export default class ReportSection extends Component<ComponentAttrs> {
  protected report: Report | null = null;
  protected loading = true;
  protected days = 30;

  oninit(vnode: Mithril.Vnode<ComponentAttrs, this>) {
    super.oninit(vnode);

    this.load();
  }

  protected load(): void {
    this.loading = true;

    app
      .request<Report>({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/placements/report`, params: { days: this.days } })
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
    return (
      <section className="PlacementSection container">
        <div className="PlacementSection-header">
          <h2>{trans('reports.title')}</h2>
          <a className="Button Button--link" href={`${app.forum.attribute('apiUrl')}/placements/report?format=csv&days=${this.days}`} download>
            {trans('reports.download')}
          </a>

          <Select
            value={String(this.days)}
            options={Object.fromEntries([7, 30, 90, 365].map((d) => [String(d), trans('reports.last_days', { count: d })]))}
            onchange={(value: string) => {
              this.days = Number(value);
              this.load();
            }}
          />
        </div>

        {this.loading ? <LoadingIndicator /> : this.body()}
      </section>
    );
  }

  protected body(): Mithril.Children {
    if (!this.report) return <Placeholder text={trans('reports.unavailable')} />;

    const { totals } = this.report;

    if (!totals.impressions && !totals.clicks) {
      return <Placeholder text={trans('reports.none')} />;
    }

    return [
      this.figures(totals),
      <DeliveryChart
        labels={this.report.daily.map((d) => d.day)}
        series={[
          { label: String(trans('reports.impressions')), colour: 'var(--primary-color)', points: this.report.daily.map((d) => d.impressions) },
          { label: String(trans('reports.clicks')), colour: 'var(--control-success-color)', points: this.report.daily.map((d) => d.clicks) },
        ]}
      />,
      this.table('placements', this.report.placements),
      this.table('creatives', this.report.creatives),
    ];
  }

  protected figures(totals: Report['totals']): Mithril.Children {
    return (
      <ul className="PlacementFigures">
        {this.figure('impressions', totals.impressions.toLocaleString())}
        {this.figure('viewable', this.rate(totals.viewable, totals.impressions), trans('reports.viewable_help'))}
        {this.figure('clicks', totals.clicks.toLocaleString())}
        {this.figure('ctr', this.rate(totals.clicks, totals.impressions))}
        {/* Shown even when zero: an advertiser asking why their impressions
            dropped by a fifth deserves an answer. */}
        {this.figure('filtered', totals.filtered.toLocaleString(), trans('reports.filtered_help'))}
      </ul>
    );
  }

  protected figure(key: string, value: Mithril.Children, help?: Mithril.Children): Mithril.Children {
    return (
      <li className="PlacementFigure" key={key}>
        <span className="PlacementFigure-value">{value}</span>
        <span className="PlacementFigure-label">{trans(`reports.${key}`)}</span>
        {help && <span className="helpText">{help}</span>}
      </li>
    );
  }

  /**
   * A percentage, or a dash when there is not enough to divide by.
   */
  protected rate(part: number, whole: number): string {
    if (whole < RATE_THRESHOLD) return '—';

    return `${((part / whole) * 100).toFixed(1)}%`;
  }

  protected table(key: 'placements' | 'creatives', rows: Row[]): Mithril.Children {
    if (!rows.length) return null;

    return (
      <div className="PlacementReportTable" key={key}>
        <h3>{trans(`reports.by_${key}`)}</h3>
        <div className="PlacementReportTable-scroll">
          <table>
            <thead>
              <tr>
                <th>{trans(`reports.${key === 'placements' ? 'slot' : 'creative'}`)}</th>
                <th>{trans('reports.impressions')}</th>
                <th>{trans('reports.viewable')}</th>
                <th>{trans('reports.clicks')}</th>
                <th>{trans('reports.ctr')}</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.key}>
                  <td>
                    <code>{row.key}</code>
                  </td>
                  <td className="num">{row.impressions.toLocaleString()}</td>
                  <td className="num">{this.rate(row.viewable, row.impressions)}</td>
                  <td className="num">{row.clicks.toLocaleString()}</td>
                  <td className="num">{this.rate(row.clicks, row.impressions)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    );
  }
}
