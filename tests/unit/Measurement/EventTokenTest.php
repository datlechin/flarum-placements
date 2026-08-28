<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Tests\unit\Measurement;

use Datlechin\Placement\Measurement\EventToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EventTokenTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private EventToken $tokens;

    protected function setUp(): void
    {
        $this->tokens = new EventToken('a-secret-key');
    }

    /**
     * @return array{token: string, nonce: string, issued: int}
     */
    private function issue(): array
    {
        return $this->tokens->issue(7, 3, 'index_above_list', self::NOW);
    }

    private function verify(array $issued, array $claims = [], ?int $now = null): ?array
    {
        $claims += [
            'creative' => 7,
            'campaign' => 3,
            'placement' => 'index_above_list',
        ];

        return $this->tokens->verify(
            $claims['token'] ?? $issued['token'],
            $claims['creative'],
            $claims['campaign'],
            $claims['placement'],
            $claims['nonce'] ?? $issued['nonce'],
            $claims['issued'] ?? $issued['issued'],
            $now ?? self::NOW,
        );
    }

    #[Test]
    public function a_token_this_server_issued_verifies(): void
    {
        $issued = $this->issue();

        $claims = $this->verify($issued);

        $this->assertNotNull($claims);
        $this->assertSame(7, $claims['creative']);
        $this->assertSame('index_above_list', $claims['placement']);
    }

    #[Test]
    public function every_token_is_different_even_for_the_same_decision(): void
    {
        // The nonce is what stops one impression being reported a thousand
        // times by replaying the same request.
        $this->assertNotSame($this->issue()['token'], $this->issue()['token']);
        $this->assertNotSame($this->issue()['nonce'], $this->issue()['nonce']);
    }

    #[Test]
    public function a_token_from_a_different_key_does_not_verify(): void
    {
        $issued = (new EventToken('someone-elses-key'))->issue(7, 3, 'index_above_list', self::NOW);

        $this->assertNull($this->verify($issued));
    }

    #[Test]
    public function a_token_cannot_be_moved_to_a_different_creative(): void
    {
        // Which is the attack the signature exists for: claim an impression for
        // a rival's creative until their cap is exhausted and their campaign
        // comes off the forum.
        $this->assertNull($this->verify($this->issue(), ['creative' => 8]));
    }

    #[Test]
    public function a_token_cannot_be_moved_to_a_different_campaign_or_slot(): void
    {
        $this->assertNull($this->verify($this->issue(), ['campaign' => 4]));
        $this->assertNull($this->verify($this->issue(), ['placement' => 'discussion_sidebar']));
    }

    #[Test]
    public function a_token_cannot_be_reused_with_a_different_nonce(): void
    {
        $this->assertNull($this->verify($this->issue(), ['nonce' => 'deadbeefdeadbeef']));
    }

    #[Test]
    public function a_tampered_signature_does_not_verify(): void
    {
        $issued = $this->issue();

        $this->assertNull($this->verify($issued, ['token' => $issued['token'].'x']));
        $this->assertNull($this->verify($issued, ['token' => '']));
    }

    #[Test]
    public function a_token_expires(): void
    {
        $issued = $this->issue();

        $this->assertNotNull($this->verify($issued, [], self::NOW + EventToken::LIFETIME));
        $this->assertNull($this->verify($issued, [], self::NOW + EventToken::LIFETIME + 1));
    }

    #[Test]
    public function a_token_issued_in_the_future_does_not_verify(): void
    {
        // Small clock differences are tolerated; a token claiming to be from
        // next week is somebody trying their luck.
        $issued = $this->issue();

        $this->assertNotNull($this->verify($issued, [], self::NOW - 60));
        $this->assertNull($this->verify($issued, [], self::NOW - 61));
    }

    #[Test]
    public function the_issued_timestamp_is_signed_too(): void
    {
        // Otherwise expiry would be defeated by simply claiming a later one.
        $issued = $this->issue();

        $this->assertNull($this->verify($issued, ['issued' => self::NOW + 30]));
    }

    #[Test]
    public function the_device_is_not_part_of_the_signature(): void
    {
        // It cannot be: viewport class is decided in the browser, so the server
        // has nothing to sign it against. Misreporting it misfiles a count
        // between two columns; it moves no cap and invents no impression.
        $issued = $this->issue();

        $this->assertNotNull($this->verify($issued));
    }

    #[Test]
    public function a_token_carries_nothing_about_who_was_looking(): void
    {
        // Which is what keeps the measurement pipeline free of personal data:
        // there is nothing in a token to identify a reader with.
        $issued = $this->issue();

        $decoded = base64_decode(strtr($issued['token'], '-_', '+/'), true);

        $this->assertIsString($decoded);
        $this->assertSame(32, strlen($decoded), 'a token should be a bare SHA-256 HMAC and nothing else');
    }
}
