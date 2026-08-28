<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Tests\integration;

use Datlechin\Placements\Creative\Type\RichTextType;
use Flarum\Formatter\Formatter;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * RichTextType against the real formatter.
 *
 * Everything worth checking about this type is what Flarum's own text pipeline
 * does with a payload, so a stub would only be testing the stub.
 */
class RendersRichTextTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');
    }

    private function type(): RichTextType
    {
        return new RichTextType($this->app()->getContainer()->make(Formatter::class));
    }

    #[Test]
    public function it_keeps_the_source_beside_the_markup(): void
    {
        $payload = $this->type()->normalize(['source' => 'Buy things.']);

        $this->assertSame('Buy things.', $payload['source']);
        $this->assertStringContainsString('Buy things.', $payload['html']);
    }

    /**
     * The syntax is the forum's, which is the point of going through its
     * formatter rather than shipping a markdown library. Bare core does line
     * breaks and autolinks; a forum with flarum/markdown or flarum/bbcode
     * enabled gets those too, in the same box, without this type knowing.
     */
    #[Test]
    public function it_renders_the_forums_own_formatting(): void
    {
        $payload = $this->type()->normalize(['source' => "Buy things.\nCheaply."]);

        $this->assertStringContainsString('<br>', $payload['html']);
    }

    /**
     * `configureDefaultsOnLinks()` assigns `rel` with `??=`, so setting it
     * before rendering is what makes it stick. Its own default, `ugc nofollow`,
     * is right for a post and wrong for a paid link: Google wants `sponsored`
     * on those, and a pattern of paid links passing PageRank earns an
     * unnatural-outbound-links action against the whole forum.
     */
    #[Test]
    public function it_marks_every_link_as_paid(): void
    {
        $payload = $this->type()->normalize(['source' => 'Go to https://acme.example now.']);

        $this->assertStringContainsString('rel="sponsored nofollow noopener"', $payload['html']);
        $this->assertStringNotContainsString('ugc', $payload['html']);
        $this->assertStringContainsString('target="_blank"', $payload['html']);
    }

    /**
     * A link to the forum itself is still a link in a paid placement, so it is
     * marked the same way rather than picking up the internal-link defaults.
     */
    #[Test]
    public function it_marks_an_internal_link_as_paid_too(): void
    {
        $payload = $this->type()->normalize(['source' => 'See '.$this->app()->getContainer()->make('flarum.config')['url'].'/d/1 for details.']);

        $this->assertStringContainsString('rel="sponsored nofollow noopener"', $payload['html']);
        $this->assertStringNotContainsString('target="_self"', $payload['html']);
    }

    /**
     * Copy that already contains a link must not be wrapped in the campaign's
     * own: nested anchors are invalid, and browsers close the outer one
     * wherever they like.
     */
    #[Test]
    public function it_reports_copy_with_a_link_as_not_wrappable(): void
    {
        $this->assertFalse($this->type()->normalize(['source' => 'Go to https://acme.example now.'])['wrappable']);
    }

    #[Test]
    public function it_reports_copy_without_a_link_as_wrappable(): void
    {
        $this->assertTrue($this->type()->normalize(['source' => 'Buy [b]things[/b].'])['wrappable']);
    }

    /**
     * normalize() runs before validation, so without the guard a megabyte
     * pasted into the box would go through the formatter on its way to being
     * rejected.
     */
    #[Test]
    public function it_does_not_render_a_source_the_rules_will_reject(): void
    {
        $payload = $this->type()->normalize(['source' => str_repeat('a', RichTextType::MAX_SOURCE + 1)]);

        $this->assertArrayNotHasKey('html', $payload);
        $this->assertArrayNotHasKey('wrappable', $payload);
        // Still returned, so the validator has something to fail on and can
        // say what was wrong rather than "source is required".
        $this->assertSame(RichTextType::MAX_SOURCE + 1, mb_strlen($payload['source']));
    }

    #[Test]
    public function it_renders_a_source_at_exactly_the_limit(): void
    {
        $payload = $this->type()->normalize(['source' => str_repeat('a', RichTextType::MAX_SOURCE)]);

        $this->assertArrayHasKey('html', $payload);
    }

    #[Test]
    public function it_renders_nothing_for_an_empty_source(): void
    {
        foreach (['', '   ', null] as $source) {
            $payload = $this->type()->normalize(['source' => $source]);

            $this->assertSame('', $payload['source']);
            $this->assertArrayNotHasKey('html', $payload);
        }
    }

    /**
     * The source is text, not markup. Anything that looks like a tag comes
     * back as the characters that were typed, or the type would be
     * RawHtmlType without the sandbox.
     */
    #[Test]
    public function it_does_not_pass_markup_through(): void
    {
        $payload = $this->type()->normalize(['source' => '<script>alert(1)</script><img src=x onerror=alert(1)>']);

        // No element was created. `onerror` still appears in the output, as the
        // eight escaped characters somebody typed inside a text node -- which
        // is the correct result and is why this checks for the tag rather than
        // for the word.
        $this->assertStringNotContainsString('<script', $payload['html']);
        $this->assertStringNotContainsString('<img', $payload['html']);
        $this->assertStringContainsString('&lt;script&gt;', $payload['html']);
    }
}
