<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\unit\Model;

use Datlechin\Placements\Model\Assignment;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Tests\unit\ConnectsModels;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CreativeTest extends TestCase
{
    use ConnectsModels;

    protected function setUp(): void
    {
        $this->connectModels();
    }

    #[Test]
    #[DataProvider('allowedDestinations')]
    public function it_allows_a_destination_a_browser_will_simply_navigate_to(string $url): void
    {
        $this->assertTrue(Creative::isAllowedDestination($url));
    }

    public static function allowedDestinations(): array
    {
        return [
            'https' => ['https://example.com/offer'],
            'http' => ['http://example.com'],
            'uppercase scheme' => ['HTTPS://example.com'],
            'with a query and fragment' => ['https://example.com/a?b=c#d'],
        ];
    }

    #[Test]
    #[DataProvider('refusedDestinations')]
    public function it_refuses_a_destination_that_is_really_code_execution(string $url): void
    {
        $this->assertFalse(Creative::isAllowedDestination($url));
    }

    public static function refusedDestinations(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript with mixed case' => ['JaVaScRiPt:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'protocol-relative, which inherits whatever we are on' => ['//example.com'],
            'schemeless' => ['example.com'],
            'a bare path' => ['/somewhere'],
            'empty' => [''],
        ];
    }

    #[Test]
    public function editing_an_approved_creative_sends_it_back_for_review(): void
    {
        // The rule everyone forgets. Without it, "approve once, edit freely"
        // is a way to get anything at all onto every page of the forum.
        $creative = new Creative();
        $creative->forceFill([
            'status' => Creative::STATUS_APPROVED,
            'reviewed_by' => 7,
            'reviewed_at' => '2026-01-01 00:00:00',
        ]);

        $creative->requireReviewAgain();

        $this->assertSame(Creative::STATUS_PENDING, $creative->status);
        $this->assertNull($creative->reviewed_by);
        $this->assertNull($creative->reviewed_at);
    }

    #[Test]
    #[DataProvider('statusesThatDoNotRevert')]
    public function a_creative_that_was_never_approved_is_left_where_it_is(string $status): void
    {
        $creative = new Creative();
        $creative->forceFill(['status' => $status]);

        $creative->requireReviewAgain();

        $this->assertSame($status, $creative->status);
    }

    public static function statusesThatDoNotRevert(): array
    {
        return [
            'draft stays a draft' => [Creative::STATUS_DRAFT],
            'pending is already pending' => [Creative::STATUS_PENDING],
            'rejected is not promoted by editing it' => [Creative::STATUS_REJECTED],
        ];
    }

    #[Test]
    public function a_creative_carries_its_own_weight_where_no_assignment_overrides_it(): void
    {
        $creative = $this->creativeWithAssignments(25, [
            ['placement_key' => 'notice', 'weight' => null],
        ]);

        $this->assertSame(25, $creative->weightIn('notice'));
    }

    #[Test]
    public function an_assignment_can_give_a_creative_a_different_share_in_one_slot(): void
    {
        // The case this exists for: one creative should dominate the sidebar
        // and barely appear in the header.
        $creative = $this->creativeWithAssignments(25, [
            ['placement_key' => 'notice', 'weight' => null],
            ['placement_key' => 'index_sidebar', 'weight' => 90],
        ]);

        $this->assertSame(25, $creative->weightIn('notice'));
        $this->assertSame(90, $creative->weightIn('index_sidebar'));
    }

    #[Test]
    public function an_unassigned_slot_falls_back_to_the_creative_weight(): void
    {
        $creative = $this->creativeWithAssignments(25, []);

        $this->assertSame(25, $creative->weightIn('header'));
    }

    private function creativeWithAssignments(int $weight, array $assignments): Creative
    {
        $creative = new Creative();
        $creative->forceFill(['weight' => $weight]);

        $creative->setRelation('assignments', new Collection(array_map(
            function (array $attributes) {
                $assignment = new Assignment();
                $assignment->forceFill($attributes);

                return $assignment;
            },
            $assignments
        )));

        return $creative;
    }
}
