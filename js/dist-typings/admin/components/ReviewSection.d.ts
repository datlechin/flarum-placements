import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Creative from '../models/Creative';
export interface ReviewSectionAttrs extends ComponentAttrs {
    /** Called after a decision, so the page can reload what it lists. */
    ondecided?: () => void;
}
/**
 * What is waiting on somebody, and what was turned down.
 *
 * Rejected creatives are listed alongside the pending ones rather than
 * disappearing: a rejection is the start of a conversation with whoever
 * submitted it, and an administrator who cannot see what they turned down
 * cannot answer "why?" a week later.
 */
export default class ReviewSection extends Component<ReviewSectionAttrs> {
    protected creatives: Creative[] | null;
    /**
     * The creative whose rejection reason is being written, and the reason.
     *
     * Inline rather than in a modal: rejecting is a sentence, and a dialogue box
     * for a sentence puts the queue behind the thing being decided about.
     */
    protected rejecting: string | null;
    protected reason: any;
    /** Ids currently being saved, so a row cannot be decided twice. */
    protected saving: Set<string>;
    oninit(vnode: Mithril.Vnode<ReviewSectionAttrs, this>): void;
    protected load(): void;
    view(): Mithril.Children;
    protected pendingCount(): number;
    protected list(): Mithril.Children;
    protected row(creative: Creative): Mithril.Children;
    protected decisions(creative: Creative, rejected: boolean): Mithril.Children;
    protected rejectionForm(creative: Creative): Mithril.Children;
    protected edit(creative: Creative): void;
    protected decide(creative: Creative, status: 'approved' | 'rejected', reason?: string): void;
}
