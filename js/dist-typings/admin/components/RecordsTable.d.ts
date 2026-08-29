import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type ItemList from 'flarum/common/utils/ItemList';
import type Model from 'flarum/common/Model';
import type Mithril from 'mithril';
import type RecordListState from '../states/RecordListState';
export interface Column<T> {
    label: Mithril.Children;
    content: (row: T) => Mithril.Children;
    /**
     * The field this column can order the list by, if it can.
     *
     * The name the API knows, without a leading minus: the direction is the
     * table's business, not the column's.
     */
    sort?: string;
    /** Applied to both the heading and every cell, e.g. to align numbers. */
    className?: string;
}
export interface RecordsTableAttrs<T extends Model> extends ComponentAttrs {
    state: RecordListState<T>;
    columns: ItemList<Column<T>>;
    /** What to say when there is genuinely nothing, rather than nothing yet. */
    empty: Mithril.Children;
    /** What to say when a search or filter is what emptied it. */
    emptyNarrowed?: Mithril.Children;
}
/**
 * A table of records, with the three states a table has to have.
 *
 * The look is core's, but `.Table()` and `.loading-container()` are parametric
 * LESS mixins -- the parentheses mean Less emits no class of that name -- so
 * `less/admin.less` calls them into `.PlacementTable` rather than the markup
 * naming them. `Placeholder` and `Pagination` are real components.
 *
 * Columns arrive as an `ItemList` so that each list declares what it shows and
 * in what order, and so another extension can add a column to any of these
 * tables without this file knowing about it.
 *
 * The three states are the point, and "still loading" must never collapse
 * into "none": a slow request would then look exactly like an empty forum,
 * which reads as a failed save.
 */
export default class RecordsTable<T extends Model> extends Component<RecordsTableAttrs<T>> {
    view(): Mithril.Children;
    protected table(): Mithril.Children;
    /**
     * A column heading, as a button when the column can be sorted by.
     *
     * A button rather than a click handler on the cell: sorting a table is an
     * action, and an action has to be reachable from the keyboard.
     */
    protected heading(column: Column<T>): Mithril.Children;
    /**
     * Which way this column is currently ordering the list, or null if it is not
     * the column being ordered by.
     */
    protected direction(column: Column<T>): 'asc' | 'desc' | null;
    protected ariaSort(column: Column<T>): string | undefined;
    /**
     * Hidden when everything fits on one page: a pager that can only ever say
     * "1 of 1" is furniture.
     */
    protected pager(): Mithril.Children;
}
