import FormModal from 'flarum/common/components/FormModal';
import type { IFormModalAttrs } from 'flarum/common/components/FormModal';
import Stream from 'flarum/common/utils/Stream';
import type Mithril from 'mithril';
import type Campaign from '../models/Campaign';
import type Creative from '../models/Creative';
export interface CreativeModalAttrs extends IFormModalAttrs {
    campaign: Campaign;
    creative?: Creative;
    onsaved?: () => void;
}
export default class CreativeModal extends FormModal<CreativeModalAttrs> {
    protected name: Stream<string>;
    protected type: Stream<string>;
    protected status: Stream<string>;
    protected weight: Stream<string>;
    protected url: Stream<string>;
    /**
     * Values are `unknown` rather than `string` because not every payload is
     * flat: a logo wall holds a list of rows. `field()` reads the scalar cases
     * back out for the inputs that expect one.
     */
    protected payload: Stream<Record<string, unknown>>;
    protected placements: Stream<Record<string, number | null>>;
    /**
     * A network container's attributes, held as ordered pairs while they are
     * being typed. See `attributePairs()`.
     */
    protected attributes: Stream<Array<[string, string]>>;
    oninit(vnode: Mithril.Vnode<CreativeModalAttrs, this>): void;
    className(): string;
    title(): Mithril.Children;
    content(): Mithril.Children;
    /**
     * The fields belonging to the chosen type.
     *
     * A type this build does not know how to draw a form for gets a plain JSON
     * field rather than nothing: an extension can register a type on the server
     * before anybody has written its form, and the creative should still be
     * editable.
     */
    protected typeFields(): Mithril.Children;
    /**
     * Raw HTML runs inside `<iframe sandbox="allow-scripts">`, never in the page.
     * The height is asked for because a sandboxed frame cannot measure itself
     * without being allowed to talk to the page.
     */
    protected rawHtmlFields(): Mithril.Children;
    protected imageFields(): Mithril.Children;
    protected textFields(): Mithril.Children;
    /**
     * Only the source is edited. The markup is the server's, rendered through
     * the forum's own formatter on save, so the syntax here is the syntax of a
     * post on this forum -- whatever formatting extensions happen to be on.
     */
    protected richTextFields(): Mithril.Children;
    protected logoWallFields(): Mithril.Children;
    /**
     * A container an external network fills.
     *
     * The attributes are typed in as name/value pairs rather than pasted as a
     * snippet, because that is how they are stored and how they are rendered:
     * as real attributes on a real element, never through `innerHTML`. The
     * server keeps only `data-*`, `class`, `id` and `style`, which is said here
     * rather than discovered by having a save silently drop half the form.
     */
    protected networkFields(): Mithril.Children;
    /**
     * The attributes being edited, as an ordered list of pairs.
     *
     * A map cannot be edited in place. Renaming a key means deleting one and
     * adding another, so the row would jump or vanish under the cursor as it was
     * typed, and two rows briefly sharing a blank name would collapse into one.
     * The list is turned back into a map on save, and only then.
     */
    protected attributePairs(): Array<[string, string]>;
    protected setAttributes(pairs: Array<[string, string]>): void;
    protected setAttributeAt(index: number, name: string, value: string): void;
    /**
     * @return The logo rows currently being edited, always a real array so that
     *         the form works the same on a new creative and an existing one.
     */
    protected logos(): Array<Record<string, string>>;
    protected setLogos(logos: Array<Record<string, string>>): void;
    protected logoInput(index: number, key: string): (e: InputEvent) => void;
    /**
     * A payload value as a string, for the inputs that hold one.
     *
     * Anything structured reads back as empty rather than as `[object Object]`,
     * which is what a naive cast would put into the field.
     */
    protected field(key: string): string;
    /**
     * Which slots this creative runs in.
     *
     * Only slots the server declared appear, because a key nothing renders would
     * be an assignment that silently never shows and is indistinguishable from a
     * targeting problem.
     */
    protected slotPicker(): Mithril.Children;
    protected toggle(key: string, on: boolean): void;
    protected payloadInput(key: string): (e: InputEvent) => void;
    protected group(key: string, control: Mithril.Children, help?: Mithril.Children): Mithril.Children;
    /**
     * Numbers are sent as numbers and empty strings are dropped, so a payload
     * never carries `"width": ""` for the renderer to guess at.
     */
    protected cleanPayload(): Record<string, unknown>;
    onsubmit(e: SubmitEvent): void;
}
