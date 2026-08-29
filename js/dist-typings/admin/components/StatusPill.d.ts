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
 * This exists because the obvious thing was wrong. The page used to write
 * status text inside core's `Badge`, and a `Badge` is not a text label: it is a
 * fixed 22-pixel circle -- `width: var(--size); height: var(--size);
 * border-radius: 50%` -- whose only intended child is an icon, and whose
 * `.Badge-label` is `display: none` precisely because any wording belongs in a
 * tooltip. "Pending review" in one was a circle with the words spilling out of
 * it.
 *
 * Two of the modifiers being asked for did not exist either. `Badge--important`
 * appears nowhere in core at all, and `Badge--warning` is only ever defined
 * nested inside `.AdminNav` and `.ExtensionWidget`, so outside those two places
 * it styles nothing. The badges were uncoloured as well as misshapen.
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
