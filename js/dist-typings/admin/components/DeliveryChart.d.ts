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
    view(): Mithril.Children;
    /**
     * What a screen reader is told, since the picture itself says nothing to one.
     */
    protected summary(labels: string[], series: Series[], max: number): string;
    protected grid(): Mithril.Children;
    protected line(series: Series, count: number, max: number): Mithril.Children;
}
