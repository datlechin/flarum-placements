import RecordListState from './RecordListState';
import type Creative from '../models/Creative';
import type { PaginatedListParams, PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
export default class CreativeListState extends RecordListState<Creative> {
    constructor(params?: PaginatedListParams);
    get type(): string;
    protected requestParams(): PaginatedListRequestParams;
}
