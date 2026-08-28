import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface DaypartGridAttrs extends ComponentAttrs {
    /** 42 hex characters, or null for "every hour". */
    mask: string | null;
    onchange: (mask: string | null) => void;
}
/**
 * The seven-by-twenty-four grid a schedule is drawn on.
 *
 * The hours are the forum's, not the reader's — Flarum stores no timezone for
 * anybody, so the alternative would be a schedule that could not be enforced.
 * Which timezone the forum keeps is a setting, and the label says which one is
 * in force so nobody has to guess.
 */
export default class DaypartGrid extends Component<DaypartGridAttrs> {
    /** Set while a pointer is down, so a schedule can be painted in one gesture. */
    protected painting: boolean | null;
    view(): Mithril.Children;
    protected cell(index: number, on: Set<number>): Mithril.Children;
    /**
     * A null mask means every hour, so the grid shows everything on rather than
     * everything off — which is what "no schedule" actually means.
     */
    protected hours(): Set<number>;
    protected toggle(index: number, on: boolean): void;
    protected set(hours: Set<number>): void;
    protected officeHours(): Set<number>;
}
