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
     * `true` issues a link, invalidating any previous one; `false` revokes.
     *
     * Both are confirmed first, and both used to fire the moment the button was
     * pressed. Each destroys a URL that cannot be recovered -- the extension's
     * own help text says so -- while deleting a campaign, which is recoverable
     * from a backup, has always asked. That asymmetry was the wrong way round.
     */
    protected issue(regenerate: boolean): void;
    onsubmit(e: SubmitEvent): void;
}
