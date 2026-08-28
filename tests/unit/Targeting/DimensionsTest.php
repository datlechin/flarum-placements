<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Targeting;

use Carbon\Carbon;
use Datlechin\Placements\Targeting\Dimension\AccountAgeDimension;
use Datlechin\Placements\Targeting\Dimension\DiscussionDimension;
use Datlechin\Placements\Targeting\Dimension\GroupDimension;
use Datlechin\Placements\Targeting\Dimension\LocaleDimension;
use Datlechin\Placements\Targeting\Dimension\PostCountDimension;
use Datlechin\Placements\Targeting\Dimension\RouteDimension;
use Datlechin\Placements\Targeting\Dimension\TagDimension;
use Datlechin\Placements\Targeting\Dimension\VisitorDimension;
use Datlechin\Placements\Targeting\DimensionInterface;
use Datlechin\Placements\Tests\unit\ConnectsModels;
use Flarum\Group\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DimensionsTest extends TestCase
{
    use ConnectsModels;
    use MakesContexts;

    protected function setUp(): void
    {
        $this->connectModels();
    }

    /**
     * @return list<DimensionInterface>
     */
    private function all(): array
    {
        return [
            new VisitorDimension(),
            new GroupDimension(),
            new RouteDimension(),
            new DiscussionDimension(),
            new LocaleDimension(),
            new PostCountDimension(),
            new AccountAgeDimension(),
            new TagDimension(),
        ];
    }

    #[Test]
    public function every_dimension_has_a_unique_key_in_this_extension_namespace(): void
    {
        $keys = array_map(fn (DimensionInterface $d) => $d->key(), $this->all());

        $this->assertSame($keys, array_values(array_unique($keys)));

        foreach ($this->all() as $dimension) {
            $this->assertStringStartsWith('datlechin-placements.', $dimension->label());
            $this->assertNotSame([], $dimension->operators());
        }
    }

    #[Test]
    public function every_option_is_a_value_and_a_label(): void
    {
        // A map would have PHP silently retype a numeric group id as an int.
        foreach ([new VisitorDimension(), new RouteDimension()] as $dimension) {
            foreach ($dimension->options() as $option) {
                $this->assertSame(['value', 'label'], array_keys($option));
                $this->assertIsString($option['value']);
                $this->assertIsString($option['label']);
            }
        }
    }

    #[Test]
    public function a_visitor_is_a_guest_or_a_member(): void
    {
        $dimension = new VisitorDimension();

        $this->assertSame('guest', $dimension->resolve($this->context()));
        $this->assertSame('member', $dimension->resolve($this->context($this->member())));
    }

    #[Test]
    public function groups_include_the_synthetic_ones_so_exclude_members_needs_no_real_group(): void
    {
        $dimension = new GroupDimension();

        $guest = $dimension->resolve($this->context());
        $this->assertSame([(string) Group::GUEST_ID], $guest);

        $member = $dimension->resolve($this->context($this->member([7, 9])));
        $this->assertContains((string) Group::MEMBER_ID, $member);
        $this->assertContains('7', $member);
        $this->assertContains('9', $member);
    }

    #[Test]
    public function group_ids_are_strings_so_they_compare_against_rule_values(): void
    {
        // Rule values live in a varchar column. Returning ints here would make
        // every group rule silently never match.
        foreach ((new GroupDimension())->resolve($this->context($this->member([7]))) as $id) {
            $this->assertIsString($id);
        }
    }

    #[Test]
    public function the_route_is_read_from_the_attribute_middleware_set(): void
    {
        $dimension = new RouteDimension();

        $this->assertSame('discussion', $dimension->resolve($this->context(null, ['routeName' => 'discussion'])));
        $this->assertNull($dimension->resolve($this->context()));
    }

    #[Test]
    public function the_dead_end_routes_are_offered_for_exclusion(): void
    {
        // Serving on a settings screen or a notifications list is a Google
        // publisher policy violation, not a matter of taste.
        $values = array_column((new RouteDimension())->options(), 'value');

        foreach (RouteDimension::DEAD_ENDS as $route) {
            $this->assertContains($route, $values);
        }
    }

    #[Test]
    public function a_discussion_id_is_split_off_the_slug(): void
    {
        // The route matches `12-some-title`, and a rule written against the
        // slug would break the moment somebody renamed the discussion.
        $dimension = new DiscussionDimension();

        $this->assertSame('12', $dimension->resolve($this->context(null, [
            'routeName' => 'discussion',
            'routeParameters' => ['id' => '12-how-to-sharpen-a-chisel'],
        ])));

        $this->assertSame('12', $dimension->resolve($this->context(null, [
            'routeName' => 'discussion',
            'routeParameters' => ['id' => '12'],
        ])));
    }

    #[Test]
    public function there_is_no_discussion_anywhere_but_a_discussion(): void
    {
        $dimension = new DiscussionDimension();

        $this->assertNull($dimension->resolve($this->context(null, ['routeName' => 'index'])));
        $this->assertNull($dimension->resolve($this->context(null, ['routeName' => 'discussion'])));
    }

    #[Test]
    public function a_tag_listing_is_read_from_the_route(): void
    {
        $dimension = new TagDimension();

        $this->assertSame('woodworking', $dimension->resolve($this->context(null, [
            'routeName' => 'tag',
            'routeParameters' => ['slug' => 'woodworking'],
        ])));
    }

    #[Test]
    public function a_discussions_tags_come_from_the_document_the_page_is_already_sending(): void
    {
        $dimension = new TagDimension();

        $resolved = $dimension->resolve($this->context(
            null,
            ['routeName' => 'discussion'],
            ['included' => [
                ['type' => 'tags', 'attributes' => ['slug' => 'support']],
                ['type' => 'tags', 'attributes' => ['slug' => 'billing']],
                ['type' => 'users', 'attributes' => ['slug' => 'not-a-tag']],
            ]]
        ));

        $this->assertSame(['support', 'billing'], $resolved);
    }

    #[Test]
    public function the_discussion_list_has_no_tags_of_its_own(): void
    {
        // The tags included alongside a list belong to dozens of different
        // discussions. Treating them as "the tags of this page" would put a
        // campaign targeted at one small tag on the forum's front page.
        $dimension = new TagDimension();

        $this->assertNull($dimension->resolve($this->context(
            null,
            ['routeName' => 'index'],
            ['included' => [['type' => 'tags', 'attributes' => ['slug' => 'support']]]]
        )));
    }

    #[Test]
    public function a_discussion_with_no_tags_resolves_to_nothing_rather_than_an_empty_list(): void
    {
        // An empty list would read as "we know this page has no tags", and an
        // exclusion would then fire on it. Null reads as "unknown", which is
        // what lets an excluded campaign still run there.
        $dimension = new TagDimension();

        $this->assertNull($dimension->resolve($this->context(null, ['routeName' => 'discussion'], ['included' => []])));
    }

    #[Test]
    public function the_locale_is_read_from_the_request(): void
    {
        $dimension = new LocaleDimension();

        $this->assertSame('de', $dimension->resolve($this->context(null, ['locale' => 'de'])));
        $this->assertNull($dimension->resolve($this->context()));
        $this->assertNull($dimension->resolve($this->context(null, ['locale' => ''])));
    }

    #[Test]
    public function post_count_and_account_age_are_quantities(): void
    {
        $this->assertSame([DimensionInterface::GTE, DimensionInterface::LTE], (new PostCountDimension())->operators());
        $this->assertSame([DimensionInterface::GTE, DimensionInterface::LTE], (new AccountAgeDimension())->operators());
    }

    #[Test]
    public function a_members_post_count_is_read_off_the_actor(): void
    {
        $dimension = new PostCountDimension();

        $this->assertSame(42, $dimension->resolve($this->context($this->member([], ['comment_count' => 42]))));
        $this->assertSame(0, $dimension->resolve($this->context($this->member([], ['comment_count' => 0]))));
    }

    #[Test]
    public function a_guest_has_an_unknown_post_count_rather_than_zero(): void
    {
        // Otherwise "at most 0 posts" would quietly become a campaign that
        // runs for every visitor on the forum.
        $this->assertNull((new PostCountDimension())->resolve($this->context()));
    }

    #[Test]
    public function account_age_is_in_whole_days(): void
    {
        $dimension = new AccountAgeDimension();

        Carbon::setTestNow(Carbon::parse('2026-08-27 12:00:00'));

        try {
            $member = $this->member([], ['joined_at' => Carbon::parse('2026-08-20 12:00:00')]);
            $this->assertSame(7, $dimension->resolve($this->context($member)));

            // Truncated, not rounded: somebody 6.9 days old has not been a
            // member for a week.
            $nearly = $this->member([], ['joined_at' => Carbon::parse('2026-08-20 14:00:00')]);
            $this->assertSame(6, $dimension->resolve($this->context($nearly)));

            $today = $this->member([], ['joined_at' => Carbon::parse('2026-08-27 09:00:00')]);
            $this->assertSame(0, $dimension->resolve($this->context($today)));
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function a_guest_has_an_unknown_account_age(): void
    {
        $this->assertNull((new AccountAgeDimension())->resolve($this->context()));
    }

    #[Test]
    public function every_dimension_shipped_here_is_decided_on_the_server(): void
    {
        // Which is what keeps group targeting correct: Flarum strips hidden
        // groups out of the payload, so a client-side reading would silently
        // stop matching for the groups forums most often target.
        foreach ($this->all() as $dimension) {
            $this->assertTrue($dimension->isServerSide(), $dimension->key());
        }
    }
}
