import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
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
    view(): Mithril.Children;
    protected row(rule: TargetingRule, index: number): Mithril.Children;
    /**
     * A dimension that knows its values offers them; one that does not takes
     * free text.
     *
     * The quantity operators always take free text, because "at least 50 posts"
     * has no list to choose from.
     */
    protected value(rule: TargetingRule, index: number, options: Array<{
        value: string;
        label: string;
    }>): Mithril.Children;
    /**
     * Dimension options are translation keys where one exists and literals
     * otherwise: a tag is called whatever the forum called it.
     */
    protected label(label: string): Mithril.Children;
    protected firstOperator(dimensionKey: string): string;
    protected add(): void;
    protected change(index: number, patch: Partial<TargetingRule>): void;
    protected remove(index: number): void;
}
