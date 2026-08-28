import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';

export interface Series {
  label: string;
  /** CSS colour, taken from a core token by the caller. */
  colour: string;
  points: number[];
}

export interface DeliveryChartAttrs extends ComponentAttrs {
  labels: string[];
  series: Series[];
}

const WIDTH = 720;
const HEIGHT = 180;
const PADDING = { top: 10, right: 10, bottom: 22, left: 10 };

/**
 * Delivery over time, drawn by hand.
 *
 * No charting library: the shapes needed here are a line and an area, which is
 * about a hundred lines of SVG against forty to ninety kilobytes on every
 * admin page load. A library would also bring its own colour system, and the
 * whole point of drawing it ourselves is that it reads the forum's own tokens
 * and follows its colour scheme and dark mode without being told about either.
 *
 * flarum/statistics sets the same precedent in core.
 */
export default class DeliveryChart extends Component<DeliveryChartAttrs> {
  view(): Mithril.Children {
    const { labels, series } = this.attrs;

    if (labels.length < 2) {
      // A single point is not a trend, and a chart of one is misleading in a
      // way a table of one is not.
      return null;
    }

    const max = Math.max(1, ...series.flatMap((s) => s.points));

    return (
      <div className="DeliveryChart">
        <svg
          className="DeliveryChart-svg"
          viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
          preserveAspectRatio="none"
          role="img"
          aria-label={this.summary(labels, series, max)}
        >
          {this.grid()}
          {series.map((s) => this.line(s, labels.length, max))}
        </svg>

        <div className="DeliveryChart-axis">
          <span>{labels[0]}</span>
          <span>{labels[labels.length - 1]}</span>
        </div>

        <ul className="DeliveryChart-legend">
          {series.map((s) => (
            <li key={s.label}>
              <span className="DeliveryChart-swatch" style={{ background: s.colour }} />
              {s.label}
            </li>
          ))}
        </ul>
      </div>
    );
  }

  /**
   * What a screen reader is told, since the picture itself says nothing to one.
   */
  protected summary(labels: string[], series: Series[], max: number): string {
    const parts = series.map((s) => `${s.label}: ${s.points.reduce((a, b) => a + b, 0)} total, ${max} at peak`);

    return `${labels[0]} to ${labels[labels.length - 1]}. ${parts.join('. ')}.`;
  }

  protected grid(): Mithril.Children {
    const y = (fraction: number) => PADDING.top + (HEIGHT - PADDING.top - PADDING.bottom) * (1 - fraction);

    return [0, 0.5, 1].map((fraction) => (
      <line className="DeliveryChart-grid" key={fraction} x1={PADDING.left} x2={WIDTH - PADDING.right} y1={y(fraction)} y2={y(fraction)} />
    ));
  }

  protected line(series: Series, count: number, max: number): Mithril.Children {
    const usableWidth = WIDTH - PADDING.left - PADDING.right;
    const usableHeight = HEIGHT - PADDING.top - PADDING.bottom;

    const x = (i: number) => PADDING.left + (count === 1 ? usableWidth / 2 : (usableWidth * i) / (count - 1));
    const y = (value: number) => PADDING.top + usableHeight * (1 - value / max);

    const path = series.points.map((value, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(value).toFixed(1)}`).join(' ');

    return [
      <path
        className="DeliveryChart-area"
        key={`${series.label}-area`}
        d={`${path} L${x(count - 1).toFixed(1)},${y(0)} L${x(0).toFixed(1)},${y(0)} Z`}
        style={{ fill: series.colour }}
      />,
      <path className="DeliveryChart-line" key={series.label} d={path} style={{ stroke: series.colour }} />,
    ];
  }
}
