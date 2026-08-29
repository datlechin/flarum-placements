import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Input from 'flarum/common/components/Input';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';

import { trans } from '../config';
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
  view(): Mithril.Children {
    const { state, searchLabel, choices = [], actions } = this.attrs;

    return (
      <div className="PlacementToolbar">
        <div className="PlacementToolbar-filters">
          <Input
            className="PlacementToolbar-search"
            type="search"
            prefixIcon="fas fa-search"
            clearable
            placeholder={extractText(searchLabel)}
            ariaLabel={extractText(searchLabel)}
            value={state.query}
            loading={state.isLoading()}
            onchange={(value: string) => state.search(value)}
          />

          {choices.map((choice) => this.choice(choice))}
        </div>

        {actions && <div className="PlacementToolbar-actions">{actions}</div>}
      </div>
    );
  }

  protected choice(choice: Choice): Mithril.Children {
    const { state } = this.attrs;

    return (
      <label className="PlacementToolbar-choice">
        {/* Visible rather than a placeholder inside the control: a select
            showing "Any" says nothing about what it selects among. */}
        <span className="PlacementToolbar-choiceLabel">{choice.label}</span>
        <Select
          value={state.currentFilter(choice.key)}
          options={{
            '': extractText(trans('lists.any')),
            ...choice.options,
          }}
          onchange={(value: string) => state.filterBy(choice.key, value)}
        />
      </label>
    );
  }
}
