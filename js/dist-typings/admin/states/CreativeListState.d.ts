import RecordListState from './RecordListState';
import type Creative from '../models/Creative';
import type { PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
export default class CreativeListState extends RecordListState<Creative> {
    get type(): string;
    protected requestParams(): PaginatedListRequestParams;
}
