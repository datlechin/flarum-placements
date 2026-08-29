import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import type Creative from '../../models/Creative';
/**
 * What is waiting on somebody, and what was turned down.
 *
 * Rejected creatives are listed alongside the pending ones rather than
 * disappearing: a rejection is the start of a conversation with whoever
 * submitted it, and an administrator who cannot see what they turned down
 * cannot answer "why?" a week later.
 *
 * This is the one screen the rewrite left alone in substance. Its interaction
 * model was already right -- approve in one click, reject in two with a reason
 * that is kept -- and the only things changed are the ones that were wrong
 * everywhere: the status label is a pill rather than a misused `Badge`, and the
 * queue pages rather than silently stopping at fifty.
 */
export default class ReviewTab extends Component {
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
    oninit(vnode: Mithril.Vnode<{}, this>): void;
    view(): Mithril.Children;
    protected list(): Mithril.Children;
    protected row(creative: Creative): Mithril.Children;
    protected decisions(creative: Creative, rejected: boolean): Mithril.Children;
    protected rejectionForm(creative: Creative): Mithril.Children;
    protected edit(creative: Creative): void;
    protected decide(creative: Creative, status: string, reason?: string): void;
    /**
     * A decision changes both the queue and the count on the tab beside it, and
     * it can change what a campaign is serving, so all three are refreshed.
     */
    protected reload(): void;
}
