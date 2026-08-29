import RecordListState from './RecordListState';
import { RESOURCE } from '../config';
import type Advertiser from '../models/Advertiser';
import type { PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';

export default class AdvertiserListState extends RecordListState<Advertiser> {
  get type(): string {
    return RESOURCE.advertisers;
  }

  protected requestParams(): PaginatedListRequestParams {
    return {
      // Alphabetical, unlike the other two. An advertiser list is a directory:
      // it is read to find one you already know the name of.
      sort: 'name',
      ...this.params,
      // Which advertisers are forum accounts, so the list can say which of
      // these records exist because a member submitted an advert rather than
      // because somebody sold one.
      include: ['user'],
    };
  }
}
