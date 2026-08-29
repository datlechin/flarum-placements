import RecordListState from './RecordListState';
import { RESOURCE } from '../config';
import type Creative from '../models/Creative';
import type { PaginatedListParams, PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';

export default class CreativeListState extends RecordListState<Creative> {
  constructor(params: PaginatedListParams = {}) {
    // In `params` rather than in `requestParams()` so that `getSort()`
    // reports it: the column headings read their arrow from there, and a
    // table sorted by the server while its headings all say "unsorted" is
    // a table whose controls are lying.
    super({ sort: '-createdAt', ...params });
  }

  get type(): string {
    return RESOURCE.creatives;
  }

  protected requestParams(): PaginatedListRequestParams {
    return {
      ...this.params,
      // The campaign is a column everywhere this list is shown except on the
      // campaign's own page, where it is already known. One include is cheaper
      // than branching on where the list is being drawn.
      include: ['campaign'],
    };
  }
}
