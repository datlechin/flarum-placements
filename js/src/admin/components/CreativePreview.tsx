import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import Placeholder from 'flarum/common/components/Placeholder';
import type Mithril from 'mithril';

import { rendererFor } from '../../common/renderers';
import type { Candidate } from '../../common/types';
import { trans } from '../config';

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
  view(): Mithril.Children {
    const renderer = rendererFor(this.attrs.type);

    if (!renderer) {
      return <Placeholder text={trans('creatives.preview_unknown_type', { type: this.attrs.type })} />;
    }

    const content = renderer(this.candidate());

    // The same emptiness the forum treats as "nothing drew". Saying so is the
    // useful answer: a network container waiting on consent looks identical to
    // a broken payload until somebody tells you which it is.
    if (content === null || content === undefined || content === false || content === '') {
      return <Placeholder text={trans('creatives.preview_empty')} />;
    }

    return (
      <div className="CreativePreview">
        <span className="CreativePreview-caption">{trans('creatives.preview')}</span>

        {/* `.Placement` so the forum's own slot styles apply. `Placement--demo`
            is deliberately absent: this is the real thing, not the sample. */}
        <aside className="Placement CreativePreview-slot">
          <span className="Placement-label">{this.attrs.label || app.translator.trans('datlechin-placements.forum.label')}</span>
          <div className="Placement-creative">{content}</div>
        </aside>
      </div>
    );
  }

  /**
   * A candidate shaped like the serving path's, with the measurement fields
   * left empty because nothing here is measured.
   */
  protected candidate(): Candidate {
    return {
      creative: 0,
      campaign: 0,
      tier: 0,
      weight: 0,
      type: this.attrs.type,
      payload: this.attrs.payload ?? {},
      url: this.attrs.destinationUrl ?? null,
      label: this.attrs.label ?? null,
      token: null,
      nonce: null,
      issued: null,
      cap: null,
      window: 'day',
    } as unknown as Candidate;
  }
}
