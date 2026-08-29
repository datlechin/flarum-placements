import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface Figure {
    label: Mithril.Children;
    value: Mithril.Children;
    /** Shown on hover, for a figure whose definition is not obvious. */
    help?: Mithril.Children;
}
export interface FiguresAttrs extends ComponentAttrs {
    figures: Figure[];
}
/**
 * A row of headline numbers.
 *
 * One of the few things here with no core equivalent: `InfoTile` is an icon
 * beside a sentence, `LabelValue` is an inline "label: value", and neither is a
 * figure meant to be read at a glance. So this is bespoke, but it is bespoke
 * layout over core's tokens rather than a second visual language -- the numbers
 * use `font-variant-numeric: tabular-nums` so a column of them lines up.
 */
export default class Figures extends Component<FiguresAttrs> {
    view(): Mithril.Children;
}
/**
 * Below how many impressions a rate is not reported.
 *
 * One click on eight impressions is not a 12.5% click-through rate, it is one
 * click. Printing the number anyway is how an administrator ends up quoting it
 * to a sponsor.
 */
export declare const RATE_THRESHOLD = 1000;
/**
 * A click-through rate, or a dash when there is not enough delivery for one to
 * mean anything.
 *
 * The threshold is the default rather than an argument callers pass, because
 * the first version of this took one and a caller left it at zero -- so a
 * campaign's own page printed a confident "50.00%" against two impressions
 * while the report a scroll away, using the same figures, correctly showed a
 * dash. Two answers to one question is worse than either.
 */
export declare function rate(numerator: number, denominator: number, threshold?: number): Mithril.Children;
