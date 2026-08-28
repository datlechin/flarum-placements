import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';

import { dimensions, trans } from '../config';
import type { TargetingRule } from '../models/Campaign';

export interface RulesEditorAttrs extends ComponentAttrs {
  rules: TargetingRule[];
  onchange: (rules: TargetingRule[]) => void;
}

/**
 * The targeting rules on a campaign.
 *
 * One row per value, which is what the table stores, so what an administrator
 * sees and what the evaluator reads are the same shape. The combining rules
 * are stated above the list rather than left to be inferred, because "AND
 * across axes, OR within one, and an exclusion always wins" is not guessable
 * from a list of rows.
 */
export default class RulesEditor extends Component<RulesEditorAttrs> {
  view(): Mithril.Children {
    const all = dimensions();

    if (!all.length) return null;

    return (
      <div className="PlacementRules">
        <div className="helpText">{trans('rules.help')}</div>

        {this.attrs.rules.map((rule, index) => this.row(rule, index))}

        <Button className="Button Button--link" icon="fas fa-plus" onclick={() => this.add()}>
          {trans('rules.add')}
        </Button>
      </div>
    );
  }

  protected row(rule: TargetingRule, index: number): Mithril.Children {
    const dimension = dimensions().find((d) => d.key === rule.dimension);

    return (
      <div className="PlacementRules-row" key={index}>
        <Select
          value={rule.dimension}
          options={Object.fromEntries(dimensions().map((d) => [d.key, app.translator.trans(d.label)]))}
          onchange={(value: string) => this.change(index, { dimension: value, operator: this.firstOperator(value), value: '' })}
        />

        <Select
          value={rule.operator}
          options={Object.fromEntries((dimension?.operators ?? []).map((op) => [op, trans(`rules.operators.${op}`)]))}
          onchange={(value: string) => this.change(index, { operator: value })}
        />

        {this.value(rule, index, dimension?.options ?? [])}

        <Button
          className="Button Button--icon Button--link"
          icon="fas fa-times"
          onclick={() => this.remove(index)}
          aria-label={trans('rules.remove')}
        />
      </div>
    );
  }

  /**
   * A dimension that knows its values offers them; one that does not takes
   * free text.
   *
   * The quantity operators always take free text, because "at least 50 posts"
   * has no list to choose from.
   */
  protected value(rule: TargetingRule, index: number, options: Array<{ value: string; label: string }>): Mithril.Children {
    const isQuantity = rule.operator === 'gte' || rule.operator === 'lte';

    if (options.length && !isQuantity) {
      return (
        <Select
          value={rule.value}
          options={Object.fromEntries(options.map((option) => [option.value, this.label(option.label)]))}
          onchange={(value: string) => this.change(index, { value })}
        />
      );
    }

    return (
      <input
        className="FormControl"
        type={isQuantity ? 'number' : 'text'}
        value={rule.value}
        oninput={(e: InputEvent) => this.change(index, { value: (e.target as HTMLInputElement).value })}
      />
    );
  }

  /**
   * Dimension options are translation keys where one exists and literals
   * otherwise: a tag is called whatever the forum called it.
   */
  protected label(label: string): Mithril.Children {
    return label.includes('.') && !label.includes(' ') ? app.translator.trans(label) : label;
  }

  protected firstOperator(dimensionKey: string): string {
    return dimensions().find((d) => d.key === dimensionKey)?.operators[0] ?? 'is';
  }

  protected add(): void {
    const first = dimensions()[0];

    this.attrs.onchange([...this.attrs.rules, { dimension: first.key, operator: first.operators[0], value: '' }]);
  }

  protected change(index: number, patch: Partial<TargetingRule>): void {
    this.attrs.onchange(this.attrs.rules.map((rule, i) => (i === index ? { ...rule, ...patch } : rule)));
  }

  protected remove(index: number): void {
    this.attrs.onchange(this.attrs.rules.filter((_, i) => i !== index));
  }
}
