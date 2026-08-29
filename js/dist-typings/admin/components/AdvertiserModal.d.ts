import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type User from 'flarum/common/models/User';
import type Mithril from 'mithril';
import type Advertiser from '../models/Advertiser';
export interface AdvertiserModalAttrs extends IFormModalAttrs {
    advertiser?: Advertiser;
    onsaved?: () => void;
}
export default class AdvertiserModal extends FormModal<AdvertiserModalAttrs> {
    protected name: Stream<string>;
    protected contactEmail: Stream<string>;
    protected notes: Stream<string>;
    /**
     * The forum account this advertiser is, if any.
     *
     * Set automatically when a member submits an advert. It was writable over the
     * API and had no control, so an advertiser who already had a login could not
     * be connected to it by hand -- only by submitting something.
     */
    protected user: Stream<User | null>;
    /**
     * The link, held only for as long as this modal is open.
     *
     * The server sends it once, in the response to the request that asked for it,
     * and never again -- so this is the only moment it can be copied.
     */
    protected issuedUrl: string | null;
    oninit(vnode: Mithril.Vnode<AdvertiserModalAttrs, this>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    protected userField(): Mithril.Children;
    protected chooseUser(): void;
    /**
     * What the advertiser gets instead of a login.
     */
    protected reportLink(): Mithril.Children;
    /**
     * Put the link on the clipboard, and say so either way.
     *
     * `navigator.clipboard` is undefined on any origin the browser does not
     * consider secure, so a forum served over plain HTTP got a button that did
     * nothing at all and said nothing about it. The link is shown exactly once,
     * so the cost of that silence is a revoke-and-reissue cycle that breaks the
     * URL the advertiser is already holding.
     */
    protected copy(): void;
    /**
     * `true` issues a link, invalidating any previous one; `false` revokes.
     *
     * Both are confirmed first. Each destroys a URL that cannot be recovered,
     * which is a heavier thing than deleting a campaign a backup can restore.
     */
    protected issue(regenerate: boolean): void;
    onsubmit(e: SubmitEvent): void;
}
