import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';

import { trans } from '../config';
import type Creative from '../models/Creative';
import CreativePreview from './CreativePreview';

export interface CreativePreviewModalAttrs extends IInternalModalAttrs {
  creative: Creative;
}

/**
 * What a reader would see, on its own.
 *
 * A modal rather than an expanding row: a table row cannot grow a second body
 * without moving the columns of every other row, and an advert is frequently
 * wider than a table cell anyway.
 */
export default class CreativePreviewModal extends Modal<CreativePreviewModalAttrs> {
  className(): string {
    return 'CreativePreviewModal Modal--medium';
  }

  title(): Mithril.Children {
    return this.attrs.creative.name();
  }

  content(): Mithril.Children {
    const creative = this.attrs.creative;

    return (
      <div className="Modal-body">
        <CreativePreview
          type={creative.type()}
          payload={creative.payload()}
          destinationUrl={creative.destinationUrl()}
          label={creative.labelOverride()}
        />
      </div>
    );
  }
}
