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

use Datlechin\Placements\Support\Permissions;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * The one endpoint on this extension that takes a file from a person.
 *
 * Most of what is worth testing here is what it refuses. An upload endpoint
 * that accepts anything is a way to put a file of the attacker's choosing on
 * the forum's own origin, and being able to reach it is the whole of the
 * exploit.
 */
class UploadsCreativeImagesTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    /** Temp files this test created as upload input. */
    private array $temp = [];

    /** Files the server actually stored. Kept apart from the inputs: the
     *  refusing tests assert this is empty, and it cannot be if the input
     *  cleanup shares the list. */
    private array $stored = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('datlechin-placements');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                [
                    'id' => 3,
                    'username' => 'submitter',
                    'password' => $this->normalUser()['password'],
                    'email' => 'submitter@machine.local',
                    'is_email_confirmed' => 1,
                ],
            ],
            'groups' => [['id' => 4, 'name_singular' => 'Submitter', 'name_plural' => 'Submitters']],
            'group_user' => [['user_id' => 3, 'group_id' => 4]],
            'group_permission' => [['group_id' => 4, 'permission' => Permissions::SUBMIT]],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([...$this->temp, ...$this->stored] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    /**
     * A real one-pixel PNG. A file of random bytes named `.png` is exactly
     * what the validator is supposed to reject, so it cannot be used to test
     * the accepting path.
     */
    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    private function upload(string $contents, string $name, string $type, ?int $as = 1): ResponseInterface
    {
        // A real file on disk, not `php://temp`. The validator decodes the
        // image through its stream's URI, which a memory stream does not
        // have -- so a temp stream fails every upload for the wrong reason and
        // would have made the accepting tests pass for the wrong one.
        $path = tempnam(sys_get_temp_dir(), 'placement-upload');
        file_put_contents($path, $contents);

        $this->temp[] = $path;

        $file = new UploadedFile(fopen($path, 'r'), strlen($contents), UPLOAD_ERR_OK, $name, $type);

        $request = $this->request('POST', '/api/placements/uploads', $as === null ? [] : ['authenticatedAs' => $as])
            ->withUploadedFiles(['image' => $file]);

        $response = $this->send($request);

        $body = json_decode((string) $response->getBody(), true);

        if (is_array($body) && isset($body['url']) && is_string($body['url'])) {
            $this->stored[] = $this->publicPath().'/'.basename($body['url']);
        }

        return $response;
    }

    private function publicPath(): string
    {
        return $this->app()->getContainer()->make(\Flarum\Foundation\Paths::class)->public.'/assets/placements';
    }

    #[Test]
    public function somebody_who_manages_advertising_can_upload_an_image(): void
    {
        $response = $this->upload($this->png(), 'banner.png', 'image/png');

        $this->assertSame(201, $response->getStatusCode());

        $url = json_decode((string) $response->getBody(), true)['url'];

        $this->assertStringContainsString('/assets/placements/', $url);
        $this->assertFileExists($this->publicPath().'/'.basename($url));
    }

    /**
     * A member authoring a creative needs this as much as staff do, and it is
     * where most of them would otherwise stop.
     */
    #[Test]
    public function somebody_who_may_submit_can_upload_an_image(): void
    {
        $this->assertSame(201, $this->upload($this->png(), 'banner.png', 'image/png', 3)->getStatusCode());
    }

    #[Test]
    public function an_ordinary_member_cannot(): void
    {
        $this->assertSame(403, $this->upload($this->png(), 'banner.png', 'image/png', 2)->getStatusCode());
    }

    /**
     * 400 rather than 401: an unauthenticated POST carries no CSRF token and
     * Flarum turns it away first. Either way nothing is written.
     */
    #[Test]
    public function a_guest_cannot(): void
    {
        $response = $this->upload($this->png(), 'banner.png', 'image/png', null);

        $this->assertContains($response->getStatusCode(), [400, 401]);
        $this->assertSame([], $this->stored);
    }

    /**
     * The name the browser sent says nothing about what the file is. This one
     * claims to be a PNG and is a PHP script.
     */
    #[Test]
    public function a_script_wearing_an_image_name_is_refused(): void
    {
        $response = $this->upload('<?php echo "owned";', 'banner.png', 'image/png');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([], $this->stored);
    }

    /**
     * SVG is a document that can carry script, and it would be served from
     * this forum's own origin.
     */
    #[Test]
    public function an_svg_is_refused(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->assertSame(422, $this->upload($svg, 'banner.svg', 'image/svg+xml')->getStatusCode());
        $this->assertSame([], $this->stored);
    }

    #[Test]
    public function a_request_with_no_file_is_refused(): void
    {
        $request = $this->request('POST', '/api/placements/uploads', ['authenticatedAs' => 1]);

        $this->assertSame(400, $this->send($request)->getStatusCode());
    }

    /**
     * The stored name is generated, never the one that was sent. A filename is
     * attacker-controlled, and one that keeps its own extension is how a file
     * that passed an image check gets served as something else.
     */
    #[Test]
    public function the_stored_name_comes_from_this_forum_and_not_from_the_upload(): void
    {
        $url = json_decode((string) $this->upload($this->png(), '../../evil .php.png', 'image/png')->getBody(), true)['url'];
        $name = basename($url);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{24}\.png$/', $name);
        $this->assertStringNotContainsString('evil', $url);
        $this->assertStringNotContainsString('..', $url);
    }
}
