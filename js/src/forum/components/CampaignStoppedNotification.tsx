import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import type Mithril from 'mithril';

/**
 * A campaign has reached the number it was given.
 *
 * The blueprint has always sent these and nothing rendered them, so they
 * arrived as an empty row in the notification list: the reader could tell
 * something had happened and not what.
 *
 * There is no link. The admin panel is a single page with no route to one
 * campaign, and sending somebody to `/admin` from a notification that may have
 * arrived on their phone is worse than sending them nowhere.
 */
export default class CampaignStoppedNotification extends Notification {
  icon() {
    return 'fas fa-circle-stop';
  }

  href() {
    return '';
  }

  content(): Mithril.Children {
    const notification = this.attrs.notification;
    // `subject()` answers false when the relationship was not loaded, and the
    // campaign may since have been deleted, so the name has to survive both.
    const campaign = notification.subject() as { name?: () => string } | false | null;
    const reason = (notification.content() as { reason?: string } | null)?.reason;

    return app.translator.trans(`datlechin-placements.forum.notifications.campaign_stopped.${reason === 'clicks' ? 'clicks' : 'impressions'}`, {
      campaign: (campaign && campaign.name?.()) || app.translator.trans('datlechin-placements.forum.notifications.campaign_stopped.unnamed'),
    });
  }

  excerpt(): Mithril.Children {
    return null;
  }
}
