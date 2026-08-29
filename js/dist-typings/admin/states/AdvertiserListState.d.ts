import RecordListState from './RecordListState';
import type Advertiser from '../models/Advertiser';
import type { PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
export default class AdvertiserListState extends RecordListState<Advertiser> {
    get type(): string;
    protected requestParams(): PaginatedListRequestParams;
}
