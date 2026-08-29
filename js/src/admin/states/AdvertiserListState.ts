import RecordListState from './RecordListState';
import { RESOURCE } from '../config';
import type Advertiser from '../models/Advertiser';
import type { PaginatedListParams, PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';

export default class AdvertiserListState extends RecordListState<Advertiser> {
  constructor(params: PaginatedListParams = {}) {
    // In `params` rather than in `requestParams()` so that `getSort()`
    // reports it: the column headings read their arrow from there, and a
    // table sorted by the server while its headings all say "unsorted" is
    // a table whose controls are lying.
    super({ sort: 'name', ...params });
  }

  get type(): string {
    return RESOURCE.advertisers;
  }

  protected requestParams(): PaginatedListRequestParams {
    return {
      ...this.params,
      // Which advertisers are forum accounts, so the list can say which of
      // these records exist because a member submitted an advert rather than
      // because somebody sold one.
      include: ['user'],
    };
  }
}
