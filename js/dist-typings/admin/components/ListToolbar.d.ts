import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type RecordListState from '../states/RecordListState';
export interface Choice {
    /** The filter key on the API, e.g. `status`. */
    key: string;
    label: Mithril.Children;
    /** Value to option label. The empty value is added as "any". */
    options: Record<string, Mithril.Children>;
}
export interface ListToolbarAttrs<T> extends ComponentAttrs {
    state: RecordListState<any>;
    searchLabel: Mithril.Children;
    choices?: Choice[];
    /** The primary action for this list, e.g. "New campaign". */
    actions?: Mithril.Children;
}
/**
 * The row above a table: search, the filters that list offers, and its primary
 * action.
 *
 * Searching goes through the state, which waits for a pause in typing before it
 * asks the server -- so the box responds to every keystroke and the network
 * does not.
 */
export default class ListToolbar<T> extends Component<ListToolbarAttrs<T>> {
    view(): Mithril.Children;
    protected choice(choice: Choice): Mithril.Children;
}
