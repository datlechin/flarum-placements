import Component from 'flarum/common/Component';
import type { ComponentAttrs } from 'flarum/common/Component';
import type Mithril from 'mithril';
import type PlacementsPage from '../PlacementsPage';
export interface SettingsTabAttrs extends ComponentAttrs {
    /** The page, for its setting streams and its save button. */
    page: PlacementsPage;
}
/**
 * The four things that really are settings. Everything else here is a record.
 *
 * Grouped rather than listed: a timezone, a retention window, an `ads.txt` file
 * and a list of loader scripts have nothing to do with one another, and running
 * them together as four unlabelled fields makes each one look like a
 * continuation of the last.
 */
export default class SettingsTab extends Component<SettingsTabAttrs> {
    oninit(vnode: Mithril.Vnode<SettingsTabAttrs, this>): void;
    protected loadStorage(): void;
    view(): Mithril.Children;
    /**
     * What the retention setting is currently holding.
     *
     * The field alone is a number nobody can judge: it does not say how much is
     * stored, how far back the history goes, or what lowering it would throw
     * away. It also does nothing by itself -- the scheduled prune acts on it --
     * so on a forum whose scheduler was never set up the field reads "90" while
     * every bucket ever recorded is still on disk. That is the case worth
     * naming, and it is the one this makes visible.
     */
    protected storage(): Mithril.Children;
}
