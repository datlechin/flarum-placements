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
 * The preview used to open underneath a row in the creative list. That worked
 * while the list was a stack of `div`s; in a table a row cannot grow a second
 * body without the columns of every other row moving with it. A modal keeps the
 * table honest and gives the preview the width it needs -- an advert is
 * frequently wider than a table cell.
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
