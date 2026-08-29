import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Pagination from 'flarum/common/components/Pagination';
import Placeholder from 'flarum/common/components/Placeholder';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import type ItemList from 'flarum/common/utils/ItemList';
import type Model from 'flarum/common/Model';
import type Mithril from 'mithril';

import { trans } from '../config';
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
 * Nothing here is invented: it is core's own arrangement -- `Table-container`
 * around a `table.Table`, `loading-container` for a refresh that keeps the old
 * rows visible, `Placeholder` for genuinely empty, and `Pagination` underneath
 * -- assembled once instead of three times.
 *
 * Columns arrive as an `ItemList` so that each list declares what it shows and
 * in what order, and so another extension can add a column to any of these
 * tables without this file knowing about it.
 *
 * The states are the point. Every list on the old page reinvented them and one
 * of them collapsed "still loading" into "none": a slow request looked exactly
 * like an empty forum, which reads as a failed save.
 */
export default class RecordsTable<T extends Model> extends Component<RecordsTableAttrs<T>> {
  view(): Mithril.Children {
    const { state } = this.attrs;

    // Nothing has come back yet. Distinct from an empty result, which is why
    // this is checked before `isEmpty`.
    if (state.isInitialLoading()) {
      return <LoadingIndicator />;
    }

    if (state.isEmpty()) {
      return <Placeholder text={state.isNarrowed() ? this.attrs.emptyNarrowed ?? trans('lists.no_matches') : this.attrs.empty} />;
    }

    return [this.table(), this.pager()];
  }

  protected table(): Mithril.Children {
    const { state, columns } = this.attrs;
    const items = columns.toArray();

    return (
      <div
        className={classList('Table-container', {
          // Keeps the rows on screen and dims them while the next page
          // arrives, rather than blanking the table and losing the reader's
          // place for the length of a request.
          'loading-container': state.isLoading(),
        })}
      >
        <table className="Table PlacementTable">
          <thead>
            <tr>
              {items.map((column, index) => (
                <th key={index} className={column.className} aria-sort={this.ariaSort(column)}>
                  {this.heading(column)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {state.items().map((row) => (
              <tr key={row.id()}>
                {items.map((column, index) => (
                  <td key={index} className={column.className}>
                    {column.content(row)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>

        {state.isLoading() && <LoadingIndicator size="large" />}
      </div>
    );
  }

  /**
   * A column heading, as a button when the column can be sorted by.
   *
   * A button rather than a click handler on the cell: sorting a table is an
   * action, and an action has to be reachable from the keyboard.
   */
  protected heading(column: Column<T>): Mithril.Children {
    if (!column.sort) return column.label;

    const direction = this.direction(column);

    return (
      <Button
        className="Button Button--text PlacementTable-sort"
        onclick={() => this.attrs.state.changeSort(direction === 'asc' ? `-${column.sort}` : column.sort!)}
        aria-label={extractText(trans('lists.sort_by', { column: column.label }))}
      >
        {column.label}
        <Icon
          name={direction === null ? 'fas fa-sort' : direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down'}
          className="PlacementTable-sortIcon"
        />
      </Button>
    );
  }

  /**
   * Which way this column is currently ordering the list, or null if it is not
   * the column being ordered by.
   */
  protected direction(column: Column<T>): 'asc' | 'desc' | null {
    if (!column.sort) return null;

    const sort = this.attrs.state.getSort();

    if (sort === column.sort) return 'asc';
    if (sort === `-${column.sort}`) return 'desc';

    return null;
  }

  protected ariaSort(column: Column<T>): string | undefined {
    const direction = this.direction(column);

    if (!column.sort) return undefined;

    return direction === 'asc' ? 'ascending' : direction === 'desc' ? 'descending' : 'none';
  }

  /**
   * Hidden when everything fits on one page: a pager that can only ever say
   * "1 of 1" is furniture.
   */
  protected pager(): Mithril.Children {
    const { state } = this.attrs;
    const perPage = state.pageSize ?? 0;

    if (!perPage || state.total() <= perPage) return null;

    return <Pagination total={state.total()} perPage={perPage} currentPage={state.currentPage()} onChange={(page: number) => state.goto(page)} />;
  }
}
