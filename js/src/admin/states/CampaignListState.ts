import RecordListState from './RecordListState';
import { RESOURCE } from '../config';
import type Campaign from '../models/Campaign';
import type { PaginatedListParams, PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';

export default class CampaignListState extends RecordListState<Campaign> {
  constructor(params: PaginatedListParams = {}) {
    // In `params` rather than in `requestParams()` so that `getSort()`
    // reports it: the column headings read their arrow from there, and a
    // table sorted by the server while its headings all say "unsorted" is
    // a table whose controls are lying.
    super({ sort: '-createdAt', ...params });
  }

  get type(): string {
    return RESOURCE.campaigns;
  }

  protected requestParams(): PaginatedListRequestParams {
    return {
      ...this.params,
      // The advertiser is a column, and it is what `isMemberSubmitted` is
      // computed from on the server, so asking for it here keeps that a
      // property of the row rather than a second request per row.
      include: ['advertiser'],
    };
  }
}
