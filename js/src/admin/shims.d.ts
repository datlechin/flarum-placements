import PlacementsState from './states/PlacementsState';

/**
 * The admin screens keep their lists on `app` rather than on the page.
 *
 * Moving between tabs changes the query string, the route key includes it, and
 * Flarum's resolver rebuilds the page component whenever that key changes --
 * so anything held on the component would be thrown away on every tab click.
 *
 * Same arrangement as `app.extensionManager` and `app.tagList`.
 */
declare module 'flarum/admin/AdminApplication' {
  export default interface AdminApplication {
    placements: PlacementsState;
  }
}
