import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
/**
 * Where advertising is managed.
 *
 * A custom page rather than a settings list, because campaigns are records
 * rather than settings -- and because writing one through the settings API
 * would dispatch `Settings\Event\Saved` and bounce every queue worker on the
 * forum each time somebody renamed a banner.
 *
 * The page is divided into tabs rather than stacked into one scroll. It used to
 * be seven unrelated concerns in a fixed vertical order, so reaching the slot
 * settings meant scrolling past the entire review queue and the entire campaign
 * list, and there was no way to tell a colleague where to look because every
 * one of them lived at the same URL.
 *
 * The tab is a query parameter on the extension's own route rather than a route
 * of its own. That keeps the page where every Flarum administrator already
 * looks for an extension, keeps the enable switch and the permission grid on
 * screen, and still gives every tab a link. Registering separate routes would
 * have worked -- `Extend.Routes` does apply to the admin app -- but core's
 * `AdminNav` cannot be added to, so those pages would exist with no way to
 * navigate to them and the extension's own chrome left behind.
 */
export default class PlacementsPage extends ExtensionPage<ExtensionPageAttrs> {
    oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>): void;
    content(): JSX.Element;
    tabItems(): ItemList<Mithril.Children>;
    /**
     * How many creatives are waiting, shown on the tab that decides them.
     *
     * Absent rather than zero when the queue is clear: a permanent "0" is a
     * standing invitation to look at a screen with nothing on it.
     */
    protected pendingBadge(): Mithril.Children;
    /**
     * Not `body()`: `ExtensionPage` already has one, and it is what renders the
     * page's sections. Overriding it here would have replaced the permission
     * grid with a tab.
     */
    protected tabBody(): Mithril.Children;
}
