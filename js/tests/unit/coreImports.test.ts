import fs from 'fs';
import path from 'path';

/**
 * Guards against statically importing a component core deliberately
 * code-splits.
 *
 * Doing so drags the whole chunk into this extension's bundle, so every
 * visitor downloads the post stream and the composer on the index page. It is
 * silent: the build succeeds, the extension works, and the only symptom is a
 * bundle several times larger than it should be.
 *
 * The list is core's `js/dist/forum/components` directory, which is where
 * webpack puts the split chunks. Regenerate it with:
 *
 *   ls framework/framework/core/js/dist/forum/components/*.js | xargs -n1 basename
 */
const CODE_SPLIT = [
  'Composer',
  'DiscussionComposer',
  'DiscussionsUserPage',
  'EditPostComposer',
  'LogInModal',
  'NotificationsPage',
  'PostStream',
  'PostStreamScrubber',
  'ReplyComposer',
  'SettingsPage',
  'SignUpModal',
  'UserSecurityPage',
];

/**
 * The same trap on the admin side.
 *
 * Regenerate with:
 *
 *   ls framework/framework/core/js/dist/admin/components/*.js | xargs -n1 basename
 *   ls framework/framework/core/js/dist/common/components/*.js | xargs -n1 basename
 *
 * The common ones are listed here rather than with the forum bundle because
 * the admin screens are the ones at risk of reaching for them: an advertiser
 * is linked to a forum account through `UserSelectionModal`, which sits right
 * next to `EditUserModal` in the same directory.
 */
const ADMIN_CODE_SPLIT = ['FailedJobsModal', 'FontAwesomePreviewModal', 'ResetExtensionSettingsModal'];
const COMMON_CODE_SPLIT = ['EditUserModal', 'SearchModal'];

// Jest runs these as ES modules, so there is no `__dirname`. The working
// directory is the `js` folder, both locally and in CI (`frontend_directory`).
const bundlePath = path.resolve(process.cwd(), 'dist/forum.js');
const adminBundlePath = path.resolve(process.cwd(), 'dist/admin.js');

describe('the compiled forum bundle', () => {
  let bundle: string;

  beforeAll(() => {
    if (!fs.existsSync(bundlePath)) {
      throw new Error(`No bundle at ${bundlePath}. Run \`npm run build\` before the tests.`);
    }

    bundle = fs.readFileSync(bundlePath, 'utf8');
  });

  it.each(CODE_SPLIT)("does not eagerly import core's %s", (component) => {
    // The eager form the compiler emits for `import X from 'flarum/...'`.
    // Matching this exactly matters: an earlier version of this test looked
    // for the bare module path, which also matched the *lazy* reference and
    // therefore passed no matter what.
    expect(bundle).not.toContain(`reg.get("core","forum/components/${component}")`);
  });

  it('still reaches PostStream, by module path', () => {
    // Proves the assertions above are not passing simply because nothing
    // touches PostStream at all.
    expect(bundle).toContain('"flarum/forum/components/PostStream"');
  });

  it('reaches the optional tags page by module path too', () => {
    // A static import would make the whole extension fail to load on a forum
    // without flarum/tags.
    expect(bundle).toContain('"ext:flarum/tags/forum/components/TagsPage"');
  });

  it('eagerly imports the components that are not split', () => {
    // The other half of the check: these are cheap and expected, and if they
    // ever move into a chunk this test starts failing and tells us.
    ['IndexPage', 'PageStructure', 'Notices', 'HeaderSecondary', 'IndexSidebar', 'DiscussionPage', 'CommentPost'].forEach((component) => {
      expect(bundle).toContain(`reg.get("core","forum/components/${component}")`);
    });
  });
});

/**
 * The admin bundle had no guard at all, which is how the admin page came to be
 * missing from it entirely for a while: `extend.ts` shadowed `extend.tsx`, the
 * build succeeded, every test passed, and nothing looked at what was actually
 * shipped.
 */
describe('the compiled admin bundle', () => {
  let bundle: string;

  beforeAll(() => {
    if (!fs.existsSync(adminBundlePath)) {
      throw new Error(`No bundle at ${adminBundlePath}. Run \`npm run build\` before the tests.`);
    }

    bundle = fs.readFileSync(adminBundlePath, 'utf8');
  });

  it.each(ADMIN_CODE_SPLIT)("does not eagerly import core's %s", (component) => {
    expect(bundle).not.toContain(`reg.get("core","admin/components/${component}")`);
  });

  it.each(COMMON_CODE_SPLIT)("does not eagerly import core's %s", (component) => {
    expect(bundle).not.toContain(`reg.get("core","common/components/${component}")`);
  });

  /**
   * Proves the assertions above are not passing because nothing is reached
   * through the registry at all -- which is exactly what a bundle missing its
   * admin page looks like.
   */
  it('reaches the core components the admin screens are built from', () => {
    ['ExtensionPage'].forEach((component) => {
      expect(bundle).toContain(`reg.get("core","admin/components/${component}")`);
    });

    ['Pill', 'Pagination', 'Placeholder', 'FieldSet', 'InfoTile', 'UserSelectionModal'].forEach((component) => {
      expect(bundle).toContain(`reg.get("core","common/components/${component}")`);
    });
  });

  /**
   * The screens themselves, by class names only they contain. The bundle can
   * compile and pass every other check here while carrying no admin page at
   * all -- that has happened once already.
   *
   * Class names rather than translation keys: `trans()` assembles its key from
   * a template literal, so the full key never appears in the output and an
   * assertion on one would fail whatever the bundle held.
   */
  it.each(['PlacementsPage-tabs', 'PlacementTable', 'PlacementReview-item', 'PlacementToolbar'])('carries the %s markup', (marker) => {
    expect(bundle).toContain(marker);
  });
});
