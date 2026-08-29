import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import type ItemList from 'flarum/common/utils/ItemList';
import type RequestError from 'flarum/common/utils/RequestError';
import type Mithril from 'mithril';
export interface ModalTab {
    label: Mithril.Children;
    /**
     * The API field names this tab contains.
     *
     * This is what makes a validation error reachable. The server answers 422
     * with a JSON:API pointer at the field it rejected; without knowing which tab
     * holds it, the modal would show "the name is required" over a tab that has
     * no name field on it.
     */
    fields: string[];
    content: () => Mithril.Children;
}
/**
 * A form modal divided into tabs.
 *
 * The two forms here are long -- a campaign has a flight, caps, pacing,
 * frequency, a 168-cell dayparting grid and an open-ended targeting editor;
 * a creative has a different sub-form for each of six types plus a slot picker
 * -- and presenting either as one unheaded column meant scrolling a wall of
 * inputs to find the one field being changed.
 *
 * The cost of tabs is that a field can be wrong on a tab nobody is looking at,
 * and a form that reports an error you cannot see is worse than a long form.
 * So `onerror` finds the tab holding the rejected field, switches to it, and
 * only then focuses the field.
 */
export default abstract class TabbedFormModal<ModalAttrs extends IFormModalAttrs = IFormModalAttrs> extends FormModal<ModalAttrs> {
    protected activeTab: string;
    abstract tabs(): ItemList<ModalTab>;
    oninit(vnode: Mithril.Vnode<ModalAttrs, this>): void;
    /**
     * The tab strip and whichever tab is open.
     *
     * Only the open tab is rendered. Rendering them all and hiding the rest would
     * put duplicate `name` attributes in the document and leave hidden inputs
     * participating in the browser's own form validation, which is how a form
     * refuses to submit with nothing on screen explaining why.
     */
    protected tabbedContent(): Mithril.Children;
    onerror(error: RequestError): void;
    /**
     * The field the server rejected, from the JSON:API pointer it answered with.
     */
    protected rejectedField(error: RequestError): string | null;
    /**
     * The tab a field the server rejected is edited on.
     *
     * Falls back to `fallbackTab()` for a name no tab claims, and that fallback
     * is what makes this work at all for a form whose fields are not fixed. The
     * creative types validate their own payloads, so the server answers with the
     * name from the type's own rules -- `/data/attributes/html`,
     * `/data/attributes/asset`, `/data/attributes/logos.0.asset` -- never
     * `/data/attributes/payload`. Listing those names here would mean this file
     * knowing every field of every creative type, including ones another
     * extension registers, which it cannot.
     */
    protected tabHolding(field: string): string | null;
    /**
     * Where to go for a rejected field no tab claims. Null to stay put.
     */
    protected fallbackTab(): string | null;
}
