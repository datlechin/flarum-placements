import RecordListState from './RecordListState';
import type Advertiser from '../models/Advertiser';
import type { PaginatedListParams, PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
export default class AdvertiserListState extends RecordListState<Advertiser> {
    constructor(params?: PaginatedListParams);
    get type(): string;
    protected requestParams(): PaginatedListRequestParams;
}
