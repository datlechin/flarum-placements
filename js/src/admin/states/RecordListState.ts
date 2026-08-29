import PaginatedListState from 'flarum/common/states/PaginatedListState';
import type Model from 'flarum/common/Model';
import type { PaginatedListParams } from 'flarum/common/states/PaginatedListState';

/**
 * One administered list of records: campaigns, creatives or advertisers.
 *
 * Everything here is core's `PaginatedListState`, which already knows how to
 * page, sort, filter and tell the three loading states apart. This adds only
 * what the admin tables need on top: a public accessor for the rows, the page
 * size the server actually pages at, and a search that waits for somebody to
 * stop typing.
 *
 * The lists used to be plain arrays fetched once with no page parameter. The
 * server pages at fifty, so the fifty-first campaign and everything after it
 * was unreachable and nothing on screen said so.
 */
export default abstract class RecordListState<T extends Model> extends PaginatedListState<T> {
  /**
   * Matches the `paginate(50)` on every one of these endpoints.
   *
   * Asking for fewer would be reasonable for a table, but the server echoes its
   * own page size back and `PaginatedListState` adopts it, so a mismatch would
   * leave the pager computing offsets against one number while the server used
   * another.
   */
  public static readonly PER_PAGE = 50;

  /**
   * Long enough that typing a word is one request rather than five, short
   * enough that the table has already moved by the time somebody looks up.
   * The same figure core's user list uses.
   */
  protected static readonly TYPING_PAUSE = 250;

  protected searchTimeout?: ReturnType<typeof setTimeout>;

  /** What is in the search box, which is not yet what was searched for. */
  public query: string = '';

  /**
   * Whether this list has ever been asked for.
   *
   * `isEmpty()` cannot answer this: it reports "no rows and not currently
   * loading", which is equally true of a list nobody has requested yet and one
   * that came back empty. Telling them apart is what stops a tab re-fetching
   * every time it is opened, and what stops an unopened tab claiming to be
   * empty.
   */
  protected everLoaded: boolean = false;

  constructor(params: PaginatedListParams = {}, page: number = 1) {
    super(params, page, RecordListState.PER_PAGE);
  }

  /**
   * The rows on the page being shown.
   *
   * `getAllItems` is protected on the base class because a list that pages by
   * appending has more than one page loaded at once. These tables replace the
   * page rather than appending to it, so "all items" and "this page" are the
   * same set.
   */
  public items(): T[] {
    return this.getAllItems();
  }

  public currentPage(): number {
    return this.getLocation().page;
  }

  public total(): number {
    // Falls back to what is on screen rather than to zero: a server that sent
    // no total should give a pager that looks like one page, not one that looks
    // like an empty list.
    return this.totalItems ?? this.items().length;
  }

  /**
   * Filters that are part of what this list *is*, rather than something
   * somebody asked for.
   *
   * The review queue is always filtered to two statuses; a campaign's creative
   * list is always filtered to that campaign. Counting those as narrowing
   * would make both say "nothing matches that" when they are simply empty --
   * which is the opposite of the message somebody needs on a campaign with no
   * creatives yet.
   */
  public structuralFilters: string[] = [];

  /**
   * Whether this list is showing the result of a search or a filter somebody
   * chose, rather than everything it could show.
   *
   * The distinction matters for the empty state: "no campaigns yet" invites
   * somebody to create one, and is the wrong thing to say to a person who has
   * just searched for a name that does not exist.
   */
  public isNarrowed(): boolean {
    const filter = this.getParams().filter ?? {};

    return Object.entries(filter).some(
      ([key, value]) => !this.structuralFilters.includes(key) && value !== '' && value !== undefined && value !== null
    );
  }

  /**
   * Type into the search box.
   *
   * The box is updated at once so typing feels immediate; the request waits.
   */
  public search(query: string): void {
    this.query = query;

    clearTimeout(this.searchTimeout);

    this.searchTimeout = setTimeout(() => {
      this.changeFilter('q', query || undefined);
    }, RecordListState.TYPING_PAUSE);
  }

  /**
   * Apply a filter from a dropdown, discarding any pending keystroke.
   *
   * Without the clear, choosing a status within a quarter second of typing
   * would fire the queued search afterwards and quietly undo the choice.
   */
  public filterBy(key: string, value: string | undefined): void {
    clearTimeout(this.searchTimeout);

    // Changing a filter loads the list, so anything that would later load it
    // again on the grounds that it had never been loaded must not.
    this.everLoaded = true;

    this.changeFilter(key, value || undefined);
  }

  public currentFilter(key: string): string {
    return (this.getParams().filter ?? {})[key] ?? '';
  }

  /**
   * Load this list if nothing has yet.
   *
   * Called when a tab is opened. Because the page component is rebuilt on every
   * tab change -- the route key includes the query string -- an unguarded load
   * in `oninit` would re-request the list each time somebody clicked back to
   * it.
   */
  public ensureLoaded(): Promise<void> {
    if (this.everLoaded) return Promise.resolve();

    this.everLoaded = true;

    return this.refresh();
  }

  /**
   * Reload the page currently being shown.
   *
   * After a save or a delete, and deliberately not `refresh()`: refresh returns
   * to page one, which would move somebody editing a campaign on page three
   * back to the top of the list.
   */
  public reload(): Promise<void> {
    return this.goto(this.currentPage());
  }
}
