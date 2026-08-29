import type { Candidate, PlacementPayload, SlotConfig } from '../types';
/**
 * What this page knows about placements.
 *
 * Everything here is synchronous and reads from the boot payload, so a slot
 * paints in the same frame as the page around it. Nothing in this class makes
 * a request.
 */
export default class PlacementState {
    protected payload: PlacementPayload | null;
    constructor(payload?: PlacementPayload | null);
    /**
     * Whether the server wrote anything for this viewer.
     *
     * False for a crawler and for anybody whose group was granted ad-free
     * browsing, in which case no slot renders and nothing is measured.
     */
    get active(): boolean;
    /**
     * Whether every slot should render a labelled sample instead of a creative.
     */
    get demo(): boolean;
    slot(key: string): SlotConfig | null;
    keys(): string[];
    /**
     * The reserved heights, as custom properties for the stylesheet to pick up
     * at each breakpoint.
     *
     * Inline custom properties rather than generated CSS on purpose. Feeding
     * these through Flarum's LESS config variables would mark assets dirty on
     * every save, and `Assets::flushCss()` rebuilds one bundle per locale — so
     * nudging a slot height on an eight-language forum would trigger sixteen
     * recompiles. A media query in our own stylesheet reads these instead.
     */
    reserveVariables(slot: SlotConfig): Record<string, string>;
    /**
     * Which occurrence of a repeating slot falls at this position, counting from
     * one, or null when nothing belongs here.
     *
     * `position` is a post number or a row index, never a vnode index. That
     * distinction is the whole point: flarum/realtime pushes new posts into an
     * open discussion over a websocket with no route change and no remount, so
     * anything keyed on array position reshuffles under the reader every time
     * somebody replies.
     */
    occurrenceAt(slot: SlotConfig, position: number): number | null;
    repeatsAt(slot: SlotConfig, position: number): boolean;
    /**
     * The candidates in the best tier that has any.
     *
     * Tiers are never mixed. A sponsorship and a remnant filler competing in the
     * same weighted draw is what makes "why didn't my paid campaign show?"
     * unanswerable, and the server has already sorted them, so this is the
     * leading run and nothing more.
     */
    /**
     * Narrow the eligible candidates to what this slot's fallback allows.
     *
     * Falling from one tier to the next is what the engine does by default, and
     * for most slots it is what an administrator wants. The setting exists for
     * the two cases where it is not:
     *
     * `collapse` means this slot is for its best tier or for nobody -- a
     * sponsorship position that quietly fills with remnant is worth less than an
     * empty one, and the sponsor notices.
     *
     * `house` means the opposite: never leave a hole, but do not let a paid
     * campaign of a lower tier take a slot the top tier was sold; drop straight
     * to the forum's own adverts instead.
     *
     * @param eligible What survived the frequency cap.
     * @param inventory Everything that was sent, before the cap.
     */
    protected forFallback(slot: SlotConfig, eligible: Candidate[], inventory: Candidate[]): Candidate[];
    topTier(candidates: Candidate[]): Candidate[];
    /**
     * Draw up to `maxFill` creatives for a slot, by weight, without repeats.
     *
     * `random` is injectable so the draw can be tested; nothing else should pass
     * it. Note the caller is expected to do this once and keep the result:
     * Mithril redraws on every event and every model change, and flarum/realtime
     * redraws an open discussion whenever anybody posts to it, so drawing inside
     * `view()` would reshuffle the ads under the reader.
     */
    pick(slot: SlotConfig, random?: () => number): Candidate[];
    /**
     * A CSS-safe suffix for a slot's modifier class.
     *
     * Third-party keys are namespaced with dots, which are legal in a class
     * attribute but would need escaping in every selector that matched them.
     */
    static modifier(key: string): string;
}
