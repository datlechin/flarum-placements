import UserPage from 'flarum/forum/components/UserPage';
import type Mithril from 'mithril';
import type Submission from '../models/Submission';
/**
 * A member's own adverts, and what became of them.
 *
 * On the profile page because that is where Flarum already puts everything
 * belonging to one person, and it comes with the routing and the layout. The
 * tab is only ever drawn on your own profile: everything here is between one
 * member and the staff.
 */
export default class SubmissionsUserPage extends UserPage {
    protected submissions: Submission[] | null;
    oninit(vnode: Mithril.Vnode<any, this>): void;
    show(user: any): void;
    protected load(): void;
    content(): Mithril.Children;
    protected list(): Mithril.Children;
    protected row(submission: Submission): Mithril.Children;
    protected badge(status: string): string;
    protected remove(submission: Submission): void;
}
