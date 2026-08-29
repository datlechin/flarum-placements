import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import Link from 'flarum/common/components/Link';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import { TABS, currentTab, tabRoute, trans } from '../config';
import type { TabKey } from '../config';
import PlacementsState from '../states/PlacementsState';
import CampaignsTab from './tabs/CampaignsTab';
import AdvertisersTab from './tabs/AdvertisersTab';
import ReviewTab from './tabs/ReviewTab';
import ReportsTab from './tabs/ReportsTab';
import SlotsTab from './tabs/SlotsTab';
import SettingsTab from './tabs/SettingsTab';

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
  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);

    // Held on `app` rather than on this component: changing tab changes the
    // route, the route key includes the query string, and Flarum's resolver
    // rebuilds the component whenever that key changes. State kept here would
    // be discarded on every tab click.
    app.placements ||= new PlacementsState();

    // Adopted rather than started fresh, so an unsaved edit on the settings
    // tab survives a click on another tab and back.
    this.settings = app.placements.settingStreams;

    // The queue count is shown on the nav from every tab, so it is asked for
    // once here rather than by the tab that happens to display the queue.
    if (app.placements.pending() === null) {
      app.placements.countPending();
    }
  }

  content(): JSX.Element {
    return (
      <div className="ExtensionPage-settings PlacementsPage">
        <div className="container">
          <div className="Tabs PlacementsPage-tabs">
            <div className="Tabs-nav">{this.tabItems().toArray()}</div>
            <div className="Tabs-content PlacementsPage-content">{this.tabBody()}</div>
          </div>
        </div>
      </div>
    );
  }

  tabItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();
    const active = currentTab();

    const tab = (key: TabKey, label: Mithril.Children, badge?: Mithril.Children) => (
      // `Link` with a real boolean rather than core's `LinkButton`, for two
      // reasons that both bite here. `LinkButton` decides "active" by comparing
      // routes with the query string stripped off, and these tabs differ only
      // by query string, so every one of them would be active at once. It also
      // stringifies the flag -- `active="false"` -- and `.Tabs-nav > .Button`
      // is selected by `[active]`, which matches an attribute whatever its
      // value. A genuine boolean is removed from the DOM when false.
      <Link href={tabRoute(key)} className="Button Button--link" active={active === key}>
        {label}
        {badge}
      </Link>
    );

    items.add('campaigns', tab(TABS.campaigns, trans('tabs.campaigns')), 100);
    items.add('review', tab(TABS.review, trans('tabs.review'), this.pendingBadge()), 90);
    items.add('slots', tab(TABS.slots, trans('tabs.slots')), 80);
    items.add('reports', tab(TABS.reports, trans('tabs.reports')), 70);
    items.add('advertisers', tab(TABS.advertisers, trans('tabs.advertisers')), 60);
    items.add('settings', tab(TABS.settings, trans('tabs.settings')), 50);

    return items;
  }

  /**
   * How many creatives are waiting, shown on the tab that decides them.
   *
   * Absent rather than zero when the queue is clear: a permanent "0" is a
   * standing invitation to look at a screen with nothing on it.
   */
  protected pendingBadge(): Mithril.Children {
    const pending = app.placements.pending();

    if (!pending) return null;

    return <span className="PlacementsPage-count">{pending}</span>;
  }

  /**
   * Not `body()`: `ExtensionPage` already has one, and it is what renders the
   * page's sections. Overriding it here would have replaced the permission
   * grid with a tab.
   */
  protected tabBody(): Mithril.Children {
    switch (currentTab()) {
      case TABS.review:
        return <ReviewTab />;
      case TABS.slots:
        return <SlotsTab />;
      case TABS.reports:
        return <ReportsTab />;
      case TABS.advertisers:
        return <AdvertisersTab />;
      case TABS.settings:
        return <SettingsTab page={this} />;
      default:
        return <CampaignsTab />;
    }
  }
}
