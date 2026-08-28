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

// Jest runs these as ES modules, so there is no `__dirname`. The working
// directory is the `js` folder, both locally and in CI (`frontend_directory`).
const bundlePath = path.resolve(process.cwd(), 'dist/forum.js');

describe('the compiled forum bundle', () => {
  let bundle: string;

  beforeAll(() => {
    if (!fs.existsSync(bundlePath)) {
      throw new Error(`No bundle at ${bundlePath}. Run \`npm run build\` before the tests.`);
    }

    bundle = fs.readFileSync(bundlePath, 'utf8');
  });

  it.each(CODE_SPLIT)('does not eagerly import core\'s %s', (component) => {
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
    ['IndexPage', 'PageStructure', 'Notices', 'HeaderSecondary', 'IndexSidebar', 'DiscussionPage', 'CommentPost'].forEach(
      (component) => {
        expect(bundle).toContain(`reg.get("core","forum/components/${component}")`);
      }
    );
  });
});
