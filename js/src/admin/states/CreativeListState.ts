import RecordListState from './RecordListState';
import { RESOURCE } from '../config';
import type Creative from '../models/Creative';
import type { PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';

export default class CreativeListState extends RecordListState<Creative> {
  get type(): string {
    return RESOURCE.creatives;
  }

  protected requestParams(): PaginatedListRequestParams {
    return {
      sort: '-createdAt',
      ...this.params,
      // The campaign is a column everywhere this list is shown except on the
      // campaign's own page, where it is already known. One include is cheaper
      // than branching on where the list is being drawn.
      include: ['campaign'],
    };
  }
}
