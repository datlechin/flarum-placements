import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
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
     * The link, held only for as long as this modal is open.
     *
     * The server sends it once, in the response to the request that asked for
     * it, and never again — so this is the only moment it can be copied.
     */
    protected issuedUrl: string | null;
    oninit(vnode: Mithril.Vnode<AdvertiserModalAttrs, this>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    /**
     * What the advertiser gets instead of a login.
     */
    protected reportLink(): Mithril.Children;
    /**
     * `true` issues a link, invalidating any previous one; `false` revokes.
     */
    protected issue(regenerate: boolean): void;
    onsubmit(e: SubmitEvent): void;
}
