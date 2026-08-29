import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type Creative from '../models/Creative';
interface Verdict {
    placement: string;
    reason: string;
    dimension?: string;
}
/**
 * Why one creative is not showing, slot by slot.
 *
 * The question this extension will otherwise be asked for ever. Every gate
 * that could answer it existed and refused in silence, and
 * `RuleEvaluator::firstFailure()` was computing the answer and throwing it
 * away.
 *
 * It reports for the administrator looking at it, which is the only honest
 * answer available: targeting depends on who is asking and dayparting on when.
 * The help text says so rather than letting somebody read "eligible" as a
 * promise about everybody.
 */
export default class DiagnoseModal extends Modal<DiagnoseModalAttrs> {
    protected verdicts: Verdict[] | null;
    oninit(vnode: Mithril.Vnode<DiagnoseModalAttrs, this>): void;
    className(): string;
    title(): Mithril.Children;
    protected assigned(): string[];
    protected check(): void;
    content(): Mithril.Children;
    protected list(): Mithril.Children;
}
export interface DiagnoseModalAttrs extends IInternalModalAttrs {
    creative: Creative;
}
export {};
