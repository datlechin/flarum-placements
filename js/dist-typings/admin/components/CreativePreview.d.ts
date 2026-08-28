import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type { Candidate } from '../../common/types';
export interface CreativePreviewAttrs extends ComponentAttrs {
    type: string;
    payload: Record<string, unknown>;
    destinationUrl?: string | null;
    label?: string | null;
}
/**
 * What a reader would see, drawn with the reader's own renderers.
 *
 * `admin/index.ts` has always registered them for exactly this and nothing had
 * ever asked for one, so staff approved creatives they had not seen. An
 * approximation would be worse than nothing here: the point of looking is to
 * catch the advert that renders wrong, and a preview that renders differently
 * cannot.
 *
 * It reports nothing. A preview is not an impression, and the beacon would
 * refuse it anyway -- there is no signed token, because the server only issues
 * one when it decides to serve something to somebody.
 */
export default class CreativePreview extends Component<CreativePreviewAttrs> {
    view(): Mithril.Children;
    /**
     * A candidate shaped like the serving path's, with the measurement fields
     * left empty because nothing here is measured.
     */
    protected candidate(): Candidate;
}
