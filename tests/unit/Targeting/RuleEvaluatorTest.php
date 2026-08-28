<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\unit\Targeting;

use Datlechin\Placement\Targeting\DimensionInterface as D;
use Datlechin\Placement\Targeting\RuleEvaluator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleEvaluatorTest extends TestCase
{
    /**
     * @return array{dimension: string, operator: string, value: string}
     */
    private function rule(string $dimension, string $operator, string $value): array
    {
        return ['dimension' => $dimension, 'operator' => $operator, 'value' => $value];
    }

    #[Test]
    public function a_campaign_with_no_rules_matches_everybody(): void
    {
        $this->assertTrue(RuleEvaluator::matches([], []));
        $this->assertTrue(RuleEvaluator::matches([], ['tag' => ['support']]));
    }

    #[Test]
    public function a_dimension_with_no_rules_does_not_constrain(): void
    {
        $rules = [$this->rule('tag', D::IS, 'support')];

        // The viewer is in a group nobody targeted, which is not a reason to
        // reject them.
        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => ['support'], 'group' => ['3']]));
    }

    #[Test]
    public function an_inclusion_matches_when_the_viewer_has_the_value(): void
    {
        $rules = [$this->rule('tag', D::IS, 'support')];

        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => ['support', 'billing']]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => ['general']]));
    }

    #[Test]
    public function several_values_on_one_dimension_are_an_or(): void
    {
        $rules = [
            $this->rule('tag', D::IS, 'support'),
            $this->rule('tag', D::IS, 'billing'),
        ];

        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => ['billing']]));
        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => ['support']]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => ['general']]));
    }

    #[Test]
    public function separate_dimensions_are_an_and(): void
    {
        $rules = [
            $this->rule('tag', D::IS, 'support'),
            $this->rule('group', D::IS, '3'),
        ];

        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => ['support'], 'group' => ['3']]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => ['support'], 'group' => ['4']]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => ['general'], 'group' => ['3']]));
    }

    #[Test]
    public function an_exclusion_rejects_the_viewer(): void
    {
        $rules = [$this->rule('tag', D::IS_NOT, 'nsfw')];

        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => ['nsfw']]));
        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => ['general']]));
    }

    #[Test]
    public function an_exclusion_beats_an_inclusion_on_the_same_dimension(): void
    {
        // Somebody will eventually target a tag and exclude it, and the result
        // has to be predictable rather than dependent on row order.
        $rules = [
            $this->rule('tag', D::IS, 'support'),
            $this->rule('tag', D::IS_NOT, 'support'),
        ];

        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => ['support']]));
    }

    #[Test]
    public function an_exclusion_beats_an_inclusion_whatever_order_the_rows_are_in(): void
    {
        $rules = [
            $this->rule('tag', D::IS_NOT, 'support'),
            $this->rule('tag', D::IS, 'support'),
        ];

        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => ['support']]));
    }

    #[Test]
    public function an_inclusion_cannot_be_confirmed_for_a_viewer_we_know_nothing_about(): void
    {
        $rules = [$this->rule('tag', D::IS, 'support')];

        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => null]));
        $this->assertFalse(RuleEvaluator::matches($rules, []));
        $this->assertFalse(RuleEvaluator::matches($rules, ['tag' => []]));
    }

    #[Test]
    public function an_exclusion_does_not_fire_for_a_viewer_we_know_nothing_about(): void
    {
        // The case that makes this the right reading: a campaign excluded from
        // the "nsfw" tag should still run on a user profile, which carries no
        // tags at all. Treating unknown as excluded would silently stop most
        // campaigns on most pages.
        $rules = [$this->rule('tag', D::IS_NOT, 'nsfw')];

        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => null]));
        $this->assertTrue(RuleEvaluator::matches($rules, []));
        $this->assertTrue(RuleEvaluator::matches($rules, ['tag' => []]));
    }

    #[Test]
    public function a_scalar_viewer_value_works_like_a_single_element_list(): void
    {
        $rules = [$this->rule('route', D::IS, 'index')];

        $this->assertTrue(RuleEvaluator::matches($rules, ['route' => 'index']));
        $this->assertFalse(RuleEvaluator::matches($rules, ['route' => 'discussion']));
    }

    #[Test]
    public function values_are_compared_as_strings_so_a_group_id_matches_either_way(): void
    {
        // Rule values come out of a varchar column; a group id arrives as an
        // int. Comparing them strictly without normalising would make every
        // group rule silently never match.
        $rules = [$this->rule('group', D::IS, '3')];

        $this->assertTrue(RuleEvaluator::matches($rules, ['group' => [3]]));
        $this->assertTrue(RuleEvaluator::matches($rules, ['group' => 3]));
    }

    #[Test]
    public function at_least_compares_quantities(): void
    {
        $rules = [$this->rule('min_posts', D::GTE, '50')];

        $this->assertTrue(RuleEvaluator::matches($rules, ['min_posts' => 50]));
        $this->assertTrue(RuleEvaluator::matches($rules, ['min_posts' => 500]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['min_posts' => 49]));
    }

    #[Test]
    public function at_most_compares_quantities(): void
    {
        $rules = [$this->rule('account_age_days', D::LTE, '30')];

        $this->assertTrue(RuleEvaluator::matches($rules, ['account_age_days' => 30]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['account_age_days' => 31]));
    }

    #[Test]
    public function a_range_is_two_rules_on_one_dimension(): void
    {
        $rules = [
            $this->rule('min_posts', D::GTE, '10'),
            $this->rule('min_posts', D::LTE, '100'),
        ];

        $this->assertTrue(RuleEvaluator::matches($rules, ['min_posts' => 55]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['min_posts' => 5]));
        $this->assertFalse(RuleEvaluator::matches($rules, ['min_posts' => 500]));
    }

    #[Test]
    public function a_quantity_cannot_be_compared_when_it_is_unknown(): void
    {
        $rules = [$this->rule('min_posts', D::GTE, '50')];

        $this->assertFalse(RuleEvaluator::matches($rules, ['min_posts' => null]));
        $this->assertFalse(RuleEvaluator::matches($rules, []));
        $this->assertFalse(RuleEvaluator::matches($rules, ['min_posts' => 'lots']));
        $this->assertFalse(RuleEvaluator::matches($rules, ['min_posts' => ['50']]));
    }

    #[Test]
    public function it_names_the_dimension_that_stopped_the_match(): void
    {
        // What the "why is my ad not showing?" overlay reports.
        $rules = [
            $this->rule('tag', D::IS, 'support'),
            $this->rule('group', D::IS, '3'),
        ];

        $this->assertSame('group', RuleEvaluator::firstFailure($rules, ['tag' => ['support'], 'group' => ['4']]));
        $this->assertSame('tag', RuleEvaluator::firstFailure($rules, ['tag' => ['general'], 'group' => ['3']]));
        $this->assertNull(RuleEvaluator::firstFailure($rules, ['tag' => ['support'], 'group' => ['3']]));
    }

    #[Test]
    public function a_zero_quantity_is_a_real_value_and_not_an_absent_one(): void
    {
        // `0 >= 0` is true, and a brand new member really does have zero
        // posts. Treating the value as missing would exclude exactly the
        // people a "new members" campaign is for.
        $rules = [$this->rule('min_posts', D::LTE, '0')];

        $this->assertTrue(RuleEvaluator::matches($rules, ['min_posts' => 0]));
    }
}
