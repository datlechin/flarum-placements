import RecordListState from './RecordListState';
import { RESOURCE } from '../config';
import type Campaign from '../models/Campaign';
import type { PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';

export default class CampaignListState extends RecordListState<Campaign> {
  get type(): string {
    return RESOURCE.campaigns;
  }

  protected requestParams(): PaginatedListRequestParams {
    return {
      // Newest first. A campaign list is read to find what was just set up far
      // more often than to read down it alphabetically, and whoever wants the
      // alphabet can click the column.
      sort: '-createdAt',
      ...this.params,
      // The advertiser is a column, and it is what `isMemberSubmitted` is
      // computed from on the server, so asking for it here keeps that a
      // property of the row rather than a second request per row.
      include: ['advertiser'],
    };
  }
}
