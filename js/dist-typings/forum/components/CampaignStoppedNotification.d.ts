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
    icon(): string;
    href(): string;
    content(): Mithril.Children;
    excerpt(): Mithril.Children;
}
