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

use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\Selection\Diagnosis;
use Datlechin\Placements\Support\Permissions;
use Datlechin\Placements\Targeting\TargetingContext;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;

/**
 * Answers "why is my advert not showing?" for one creative in one slot.
 *
 * The answer is always about this administrator, in this slot, at this moment
 * -- targeting depends on who is asking and dayparting on when. That is a
 * limitation worth stating rather than hiding: it cannot tell you why somebody
 * else is not seeing an advert, only why you are not.
 */
class DiagnoseController implements RequestHandlerInterface
{
    public function __construct(protected Diagnosis $diagnosis)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan(Permissions::MANAGE);

        $query = $request->getQueryParams();

        $creativeId = $query['creative'] ?? null;
        $placement = $query['placement'] ?? null;

        if (! is_numeric($creativeId) || ! is_string($placement) || $placement === '') {
            throw new BadRequestException('A creative and a placement are both required.');
        }

        $creative = Creative::query()->with('assignments', 'campaign')->find((int) $creativeId);

        if ($creative === null) {
            throw new BadRequestException("There is no creative [$creativeId].");
        }

        $verdict = $this->diagnosis->of($creative, $placement, new TargetingContext($request, $actor));

        return new JsonResponse([
            'creative' => (int) $creative->id,
            'placement' => $placement,
            ...$verdict,
        ]);
    }
}
