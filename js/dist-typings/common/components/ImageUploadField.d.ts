import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export interface ImageUploadFieldAttrs extends ComponentAttrs {
    value: string;
    onchange: (url: string) => void;
    placeholder?: string;
    required?: boolean;
}
/**
 * An address, and a way to get one without leaving the page.
 *
 * The address stays editable, because plenty of creatives point at an
 * advertiser's own CDN and always will; the upload is for the case where
 * somebody has a file and nowhere to put it. Before this existed, that case
 * had no answer at all, and it is the case a member submitting an advert is
 * always in.
 */
export default class ImageUploadField extends Component<ImageUploadFieldAttrs> {
    protected uploading: boolean;
    protected error: string | null;
    view(): Mithril.Children;
    protected hide(e: Event): void;
    protected show(e: Event): void;
    protected choose(e: InputEvent): void;
}
