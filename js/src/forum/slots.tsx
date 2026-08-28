import { extend, override } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import Footer from 'flarum/forum/components/Footer';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import IndexPage from 'flarum/forum/components/IndexPage';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Notices from 'flarum/forum/components/Notices';
import PageStructure from 'flarum/forum/components/PageStructure';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

import PlacementSlot from '../common/components/PlacementSlot';

type Items = ItemList<Mithril.Children>;

/**
 * `.Page-main` carries no rules of its own, so a slot placed there is
 * full-bleed and has to supply the page's own container.
 */
function contained(name: string): Mithril.Children {
  return (
    <div className="container">
      <PlacementSlot name={name} />
    </div>
  );
}

/**
 * Wires every built-in placement to the `ItemList` that renders it.
 *
 * Each `extend` below targets a real, priority-ordered extension point in
 * core; the priorities are chosen against the items core already puts in the
 * same list, which are named in the comments. There is not one `view()`
 * override here except the footer, which core gives no other seam for.
 *
 * `PostStream` is reached by module path rather than by import because it is
 * one of the twelve components core code-splits, and importing it would drag
 * the whole chunk into this extension's bundle. The registry calls the handler
 * immediately when the module is already loaded, so the string form is correct
 * whether or not it happens to be split.
 */
export default function registerSlots(): void {
  // Above the header on every page, in the strip core uses for its own
  // announcements. @see Notices.tsx — emailConfirmation sits at 100, and these
  // items are rendered raw rather than through listItems.
  extend(Notices.prototype, 'items', function (items: Items) {
    items.add('placement', <PlacementSlot name="notice" />, 95);
  });

  // @see HeaderSecondary.js — search 30, themeSwitcher 12, notifications 10,
  // session 0. HeaderPrimary is deliberately left alone: its view pipes items
  // through OverflowingList, which silently collapses overflow into a menu.
  extend(HeaderSecondary.prototype, 'items', function (items: Items) {
    items.add('placement', <PlacementSlot name="header" />, 25);
  });

  // @see PageStructure.tsx — skipToMainContent 200, hero 100, container 10.
  extend(PageStructure.prototype, 'mainItems', function (items: Items) {
    items.add('placementTop', contained('page_top'), 150);
    items.add('placementBottom', contained('page_bottom'), 5);
  });

  // @see PageStructure.tsx — the provided sidebar sits at 100. One extend
  // covers the index, discussion, user, posts and tags sidebars.
  extend(PageStructure.prototype, 'sidebarItems', function (items: Items) {
    items.add('placementTop', <PlacementSlot name="page_sidebar_top" />, 110);
    items.add('placementBottom', <PlacementSlot name="page_sidebar_bottom" />, 90);
  });

  // @see IndexPage.tsx — toolbar 100, discussionList 90. Also covers
  // discussion search results, which are the same route with a query set.
  extend(IndexPage.prototype, 'contentItems', function (items: Items) {
    items.add('placementAbove', <PlacementSlot name="index_above_list" />, 95);
    items.add('placementBelow', <PlacementSlot name="index_below_list" />, 85);
  });

  // @see IndexSidebar.tsx — newDiscussion and nav both use the implicit
  // priority, so an explicit negative one puts this below them.
  extend(IndexSidebar.prototype, 'items', function (items: Items) {
    items.add('placement', <PlacementSlot name="index_sidebar" />, -10);
  });

  // @see DiscussionPage.tsx — controls 100, scrubber -100. <li>-wrapped into
  // .DiscussionPage-nav, which is sticky from tablet up and only 180px wide.
  extend(DiscussionPage.prototype, 'sidebarItems', function (items: Items) {
    items.add('placement', <PlacementSlot name="discussion_sidebar" />, -150);
  });

  // The honest "an ad every nth post": inside the post's own footer rather
  // than as a standalone row, because core exposes no seam between posts.
  //
  // Keyed on post.number(), never a vnode index. flarum/realtime pushes new
  // posts into an open discussion with no route change and no remount, so
  // anything keyed on array position reshuffles under the reader every time
  // somebody replies.
  //
  // CommentPost rather than AbstractPost, for two reasons. It is where `post`
  // is actually typed — AbstractPost's own attrs interface is empty, and only
  // Post adds the model. And it excludes EventPost, so an ad never lands in
  // the footer of a one-line "X renamed the discussion" entry.
  //
  // @see AbstractPost.tsx — footerItems(), rendered into
  //      <footer class="Post-footer"><ul>, <li>-wrapped, and inherited by
  //      CommentPost through Post.
  extend(CommentPost.prototype, 'footerItems', function (this: CommentPost, items: Items) {
    const number = this.attrs.post?.number?.();

    if (typeof number !== 'number') return;

    items.add('placement', <PlacementSlot name="post_footer" position={number} />, 50);
  });

  // @see PostStream.js — afterFirstPostItems(), rendered inside
  // <div class="PostStream-item PostStream-afterFirstPost">. The highest-value
  // slot on a forum: search traffic lands on a discussion and reads the first
  // post.
  extend('flarum/forum/components/PostStream', 'afterFirstPostItems', function (items: Items) {
    items.add('placement', <PlacementSlot name="discussion_after_op" />, 100);
  });

  // @see PostStream.js — endItems(), spread into a *keyed* children array only
  // when the reader has reached the end, so anything added here must carry its
  // own key or Mithril throws.
  extend('flarum/forum/components/PostStream', 'endItems', function (items: Items) {
    items.add('placement', <PlacementSlot key="placement-stream-end" name="discussion_stream_end" />, 70);
  });

  // Core's Footer is seven lines whose view() returns null, and `extend` hands
  // the callback a return value to mutate in place — there is nothing to
  // mutate. Overriding is the only seam, so the original is called and its
  // result kept, which at least composes with another extension that does the
  // same. A five-line `Footer.items(): ItemList` upstream would remove the
  // need for this entirely, and is worth filing.
  override(Footer.prototype, 'view', function (original: () => Mithril.Children) {
    return [original(), <PlacementSlot name="footer" />];
  });
}

/**
 * The tag directory, which only exists when flarum/tags is enabled.
 *
 * A tag-filtered listing at /t/{slug} is still a plain IndexPage and is
 * already covered by index_above_list, so this is the directory page alone.
 */
export function registerTagSlots(): void {
  if (!('flarum-tags' in flarum.extensions)) return;

  // @see extensions/tags TagsPage.tsx — tagTiles 100, cloud 10.
  extend('ext:flarum/tags/forum/components/TagsPage', 'contentItems', function (items: Items) {
    items.add('placement', <PlacementSlot name="tags_page" />, 95);
  });
}
