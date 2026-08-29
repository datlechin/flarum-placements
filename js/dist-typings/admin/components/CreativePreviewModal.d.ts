import Modal from 'flarum/common/components/Modal';
import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
import type Creative from '../models/Creative';
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
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
}
