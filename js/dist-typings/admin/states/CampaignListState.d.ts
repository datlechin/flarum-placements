import RecordListState from './RecordListState';
import type Campaign from '../models/Campaign';
import type { PaginatedListParams, PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
export default class CampaignListState extends RecordListState<Campaign> {
    constructor(params?: PaginatedListParams);
    get type(): string;
    protected requestParams(): PaginatedListRequestParams;
}
