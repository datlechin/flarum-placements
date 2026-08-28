<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements;

/**
 * The placements this extension ships.
 *
 * Every one of them is a real, priority-ordered `ItemList` in Flarum 2.x or in
 * a bundled extension — the `@see` on each says which, so that when core moves
 * something there is one file to check. Nothing here is reached by overriding
 * a `view()` or splicing a vnode tree.
 *
 * Two placements people ask for constantly are absent, because 2.x exposes no
 * seam for either: a standalone row between arbitrary posts, and a row between
 * discussions in the list. `PostStream.view()` builds its children from a bare
 * `posts.map()` and `DiscussionList.view()` builds `<li>`s inline. Splicing
 * into either is not merely inelegant: `PostStream` keys its scroll targets,
 * scrubber maths and load-more off `.PostStream-item[data-index]` and watches
 * new items with a `ResizeObserver`, so an ad iframe that resizes after load
 * drags the reader's viewport. `post_footer` is the honest substitute, and it
 * keys off `post.number()` rather than a vnode index, so it does not reshuffle
 * when older posts load in or when flarum/realtime pushes a new post over the
 * websocket.
 *
 * On sizes: `--sidebar-width` is 190px, widening to 260px and 280px at the
 * larger breakpoints, and DiscussionPage narrows it to 180px. A 300x250 MPU
 * fits none of them, so every sidebar slot here recommends a 160x600 wide
 * skyscraper instead.
 */
final class BuiltInPlacements
{
    /**
     * Placements that are always available.
     *
     * @return list<Placement>
     */
    public static function all(): array
    {
        return [
            ...self::global(),
            ...self::page(),
            ...self::index(),
            ...self::discussion(),
            ...self::user(),
        ];
    }

    /**
     * Mounted once, outside the routed subtree, so these survive every
     * client-side navigation and must be refreshed by our own signal rather
     * than by Mithril tearing them down.
     *
     * @return list<Placement>
     */
    public static function global(): array
    {
        return [
            // @see forum/components/Notices.tsx:17 — items(), rendered raw into .App-notices.
            //      Mounted at ForumApplication.tsx into #notices, which is outside the m.route() root.
            new Placement(
                key: 'notice',
                group: Placement::GROUP_GLOBAL,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),

            // @see forum/components/HeaderSecondary.js:27 — items(), <li>-wrapped into .Header-controls.
            //      HeaderPrimary is deliberately not used: its view pipes items through OverflowingList,
            //      which silently collapses overflow into a menu.
            new Placement(
                key: 'header',
                group: Placement::GROUP_GLOBAL,
                recommendedSize: [160, 30],
                reserveDesktop: 30,
            ),

            // Footer has no ItemList: forum/components/Footer.tsx is seven lines and view() returns null,
            // and extend() hands the callback a return value to mutate in place. Served by writing into
            // $document->foot instead, which needs no override and cannot clobber another extension.
            new Placement(
                key: 'footer',
                group: Placement::GROUP_GLOBAL,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),
        ];
    }

    /**
     * Every page, full width. Gate on the page's own class name to narrow one
     * of these to a single route.
     *
     * @return list<Placement>
     */
    public static function page(): array
    {
        return [
            // @see forum/components/PageStructure.tsx:38 — mainItems() into .Page-main.
            //      Defaults there are skipToMainContent 200, hero 100, container 10, so 150 sits
            //      below the hero and above everything else. .Page-main carries no LESS rules, so
            //      the slot is full-bleed and supplies its own .container.
            new Placement(
                key: 'page_top',
                group: Placement::GROUP_PAGE,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),

            // @see forum/components/PageStructure.tsx:38 — mainItems() at priority 5, below the container.
            //      This is the only clean below-everything slot, and the right answer for DiscussionPage
            //      and PostsPage, neither of which exposes a content ItemList of its own.
            new Placement(
                key: 'page_bottom',
                group: Placement::GROUP_PAGE,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),

            // @see forum/components/PageStructure.tsx:83 — sidebarItems() into .Page-sidebar, not <li>-wrapped.
            //      One extend covers the index, discussion, user, posts and tags sidebars.
            new Placement(
                key: 'page_sidebar_top',
                group: Placement::GROUP_PAGE,
                recommendedSize: [160, 600],
                reserveTablet: 600,
                reserveDesktop: 600,
            ),

            new Placement(
                key: 'page_sidebar_bottom',
                group: Placement::GROUP_PAGE,
                recommendedSize: [160, 600],
                reserveTablet: 600,
                reserveDesktop: 600,
            ),
        ];
    }

    /**
     * The discussion list. Also covers discussion search results, which are
     * the same route with a query set.
     *
     * @return list<Placement>
     */
    public static function index(): array
    {
        return [
            // @see forum/components/IndexPage.tsx:72 — contentItems(); toolbar 100, discussionList 90.
            //      Priority 95 lands between them. In-repo precedent for exactly this number:
            //      extensions/pusher/js/src/forum/index.tsx and realtime's NewActivity.ts.
            new Placement(
                key: 'index_above_list',
                group: Placement::GROUP_INDEX,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),

            // @see forum/components/IndexPage.tsx:72 — contentItems() at priority 85, below the list.
            new Placement(
                key: 'index_below_list',
                group: Placement::GROUP_INDEX,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),

            // @see forum/components/IndexSidebar.tsx:28 — items(); newDiscussion and nav both use the
            //      implicit priority, so always pass an explicit one. Reused verbatim by PostsPage and
            //      by the tags extension's TagsPage, so one extend covers three pages.
            new Placement(
                key: 'index_sidebar',
                group: Placement::GROUP_INDEX,
                recommendedSize: [160, 600],
                reserveTablet: 600,
                reserveDesktop: 600,
            ),
        ];
    }

    /**
     * The discussion page and the post stream.
     *
     * @return list<Placement>
     */
    public static function discussion(): array
    {
        return [
            // @see forum/components/PostStream.js:136 — afterFirstPostItems(), rendered at :87 inside
            //      <div class="PostStream-item PostStream-afterFirstPost">. The highest-value slot on
            //      the forum: search traffic lands on a discussion and reads the first post.
            //
            //      Two caveats it inherits: it only fires when firstPost is a loaded relationship, and
            //      only when the original post is inside the current window — deep-linking to /d/1/400
            //      shows nothing.
            new Placement(
                key: 'discussion_after_op',
                group: Placement::GROUP_DISCUSSION,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),

            // @see forum/components/PostStream.js:145 — endItems(), spread at :111 only when viewingEnd.
            //      Items are spread into a keyed children array, so anything added here must carry its
            //      own key.
            new Placement(
                key: 'discussion_stream_end',
                group: Placement::GROUP_DISCUSSION,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),

            // @see forum/components/DiscussionPage.tsx:302 — sidebarItems(); controls 100, scrubber -100.
            //      <li>-wrapped into .DiscussionPage-nav, which is position: sticky on tablet and up
            //      with --sidebar-width: 180px (less/forum/DiscussionPage.less:27). Narrower than every
            //      other sidebar on the forum.
            new Placement(
                key: 'discussion_sidebar',
                group: Placement::GROUP_DISCUSSION,
                recommendedSize: [160, 600],
                reserveTablet: 600,
                reserveDesktop: 600,
            ),

            // @see forum/components/AbstractPost.tsx:137 — footerItems(), rendered at :79 into
            //      <footer class="Post-footer"><ul>, <li>-wrapped. Extended on CommentPost rather
            //      than AbstractPost: that is where `post` is actually typed, and it keeps ads out
            //      of the footer of a one-line "X renamed the discussion" event post.
            //
            //      This is the honest "an ad every Nth post": the creative sits inside the post's own
            //      footer rather than as a standalone row. Keyed off post.number(), never a vnode index,
            //      so it does not reshuffle when older posts load or when realtime pushes a new post.
            //      AbstractPost uses a SubtreeRetainer, so the slot will not redraw on external state —
            //      the creative is chosen once at mount, which is what we want.
            new Placement(
                key: 'post_footer',
                group: Placement::GROUP_DISCUSSION,
                repeating: true,
                recommendedSize: [468, 60],
                reservePhone: 50,
                reserveTablet: 60,
                reserveDesktop: 60,
            ),
        ];
    }

    /**
     * The user profile.
     *
     * @return list<Placement>
     */
    public static function user(): array
    {
        return [
            // @see forum/components/UserPage.tsx:130 — sidebarItems(); 'nav' is added with the implicit
            //      priority, so -100 puts this below it. AffixedSidebar re-measures on resize, so a unit
            //      taller than the viewport behaves badly here.
            new Placement(
                key: 'profile_sidebar',
                group: Placement::GROUP_USER,
                recommendedSize: [160, 600],
                reserveTablet: 600,
                reserveDesktop: 600,
            ),
        ];
    }

    /**
     * Registered only when flarum/tags is enabled.
     *
     * A tag-filtered listing at /t/{slug} is still a plain IndexPage, so
     * `index_above_list` already covers it. This is the tag *directory*.
     *
     * @return list<Placement>
     */
    public static function tags(): array
    {
        return [
            // @see extensions/tags/js/src/forum/components/TagsPage.tsx:70 — contentItems();
            //      tagTiles 100, cloud 10. The page carries Page--vertical, which turns .Page-sidebar
            //      into a full-width horizontal strip (less/forum/PageStructure.less:50), so sidebar
            //      creatives lay out horizontally there.
            new Placement(
                key: 'tags_page',
                group: Placement::GROUP_TAGS,
                recommendedSize: [728, 90],
                reservePhone: 100,
                reserveTablet: 90,
                reserveDesktop: 90,
            ),
        ];
    }
}
