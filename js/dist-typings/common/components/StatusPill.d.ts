import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
export type PillTone = 'neutral' | 'success' | 'warning' | 'danger';
export interface StatusPillAttrs extends ComponentAttrs {
    tone?: PillTone;
    icon?: string;
    className?: string;
}
/**
 * A short piece of status text, as a pill.
 *
 * Not `Badge`, which is the tempting one. A `Badge` is not a text label: it
 * is a fixed 22-pixel circle whose only intended child is an icon, and whose
 * `.Badge-label` is `display: none` because any wording belongs in a tooltip.
 * `Badge--important` does not exist in core at all, and `Badge--warning` is
 * only defined nested inside `.AdminNav` and `.ExtensionWidget`, so elsewhere
 * it styles nothing.
 *
 * `Pill` is the primitive core provides for exactly this, and it takes its
 * colours from `--pill-bg` / `--pill-color`. The tones below set that pair from
 * core's own semantic tokens, so they follow the active theme rather than
 * declaring colours of their own.
 */
export default class StatusPill extends Component<StatusPillAttrs> {
    view(vnode: Mithril.Vnode<StatusPillAttrs, this>): Mithril.Children;
}
/**
 * The tone a creative's review status should read in.
 *
 * One mapping, used by the queue and by every list that shows a creative. There
 * were two before, written separately and agreeing only by coincidence.
 */
export declare function creativeTone(status: string): PillTone;
/**
 * The tone a campaign's status should read in.
 *
 * `live` is the server's computed answer, not the stored status, and it is what
 * decides the tone: a campaign an administrator marked active whose flight has
 * ended or whose cap is spent is not running, and colouring it as though it
 * were is the one thing this pill must not do.
 */
export declare function campaignTone(status: string, live: boolean): PillTone;
