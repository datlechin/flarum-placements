import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Submission from '../models/Submission';
export interface SubmissionModalAttrs extends IFormModalAttrs {
    submission?: Submission;
    onsaved?: () => void;
}
/**
 * Where a member writes their advert.
 *
 * Only the fields that are theirs: what it says, where it goes, and what kind
 * of thing it is. Where it runs and how often are the forum's decisions, made
 * after somebody has read it.
 */
export default class SubmissionModal extends FormModal<SubmissionModalAttrs> {
    protected name: Stream<string>;
    protected type: Stream<string>;
    protected url: Stream<string>;
    protected payload: Stream<Record<string, string>>;
    oninit(vnode: Mithril.Vnode<SubmissionModalAttrs, this>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    /**
     * A type this build has no form for gets nothing rather than a JSON box:
     * the admin side offers raw JSON as an escape hatch, which is not something
     * to put in front of a member.
     */
    protected typeFields(): Mithril.Children;
    protected field(key: string): string;
    protected input(key: string): (e: InputEvent) => void;
    protected group(key: string, control: Mithril.Children, help?: Mithril.Children): Mithril.Children;
    onsubmit(e: SubmitEvent): void;
}
