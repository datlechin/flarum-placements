import RecordListState from './RecordListState';
import type Campaign from '../models/Campaign';
import type { PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
export default class CampaignListState extends RecordListState<Campaign> {
    get type(): string;
    protected requestParams(): PaginatedListRequestParams;
}
