<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Api\Controller;

use Datlechin\Placements\Support\Permissions;
use Datlechin\Placements\Upload\CreativeImageUploader;
use Datlechin\Placements\Upload\CreativeImageValidator;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;

/**
 * Somewhere to put the banner a sponsor emailed you.
 *
 * Without this, every image creative needs a URL the forum owner has already
 * hosted somewhere else, and the member submission portal asks a member to
 * solve image hosting before they can submit an advert -- which is where most
 * of them stop.
 *
 * Open to whoever may manage advertising *or* submit it, because both author
 * image creatives, and refused to everybody else. What a member may do with
 * the URL afterwards is still bounded by the submission resource: it lands in
 * a payload on a creative that arrives pending.
 */
class UploadCreativeImageController implements RequestHandlerInterface
{
    public function __construct(
        protected CreativeImageValidator $validator,
        protected CreativeImageUploader $uploader,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $actor->assertRegistered();

        // Either permission is enough. `assertCan` would be wrong here: it
        // takes one ability, and holding the other is equally sufficient.
        if (! $actor->hasPermission(Permissions::MANAGE) && ! $actor->hasPermission(Permissions::SUBMIT)) {
            $actor->assertCan(Permissions::SUBMIT);
        }

        $file = $request->getUploadedFiles()['image'] ?? null;

        if (! $file instanceof UploadedFileInterface) {
            throw new BadRequestException('Send the image as a multipart field named `image`.');
        }

        // Throws a ValidationException, which Flarum turns into the same 422
        // shape the rest of the API uses, so the form can show it where the
        // field is.
        $this->validator->assertImageValid('image', $file);

        return new JsonResponse(['url' => $this->uploader->upload($file)], 201);
    }
}
