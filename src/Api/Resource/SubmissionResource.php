<?php

/*
 * This file is part of datlechin/flarum-placement.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placement\Api\Resource;

use Datlechin\Placement\Creative\CreativeTypeRegistry;
use Datlechin\Placement\Model\Creative;
use Datlechin\Placement\Model\Submission;
use Datlechin\Placement\Submission\MemberInventory;
use Datlechin\Placement\Support\Permissions;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;

/**
 * What a member may do with an advert of their own.
 *
 * The same rows as CreativeResource, deliberately behind a second, much
 * narrower door. Widening the administrative resource instead would have meant
 * one permission check standing between a member and every campaign on the
 * forum -- including what each advertiser is paying, which is the one thing in
 * here nobody outside the staff should ever read.
 *
 * So this resource exposes a member's own submissions and nothing else. It
 * cannot reach a campaign, a rate, an advertiser, a weight, or a slot: where
 * an advert runs and how much of the rotation it takes are the forum's
 * decisions, made in the admin panel, on a creative somebody already looked at.
 *
 * @extends AbstractDatabaseResource<Submission>
 */
class SubmissionResource extends AbstractDatabaseResource
{
    /**
     * How many undecided submissions one member may have at once.
     *
     * Not a rate limit -- it is a queue limit. Somebody has to read each of
     * these, and a member who can put a hundred in front of them has made the
     * queue useless for everybody else.
     */
    public const PENDING_LIMIT = 10;

    public function __construct(
        protected CreativeTypeRegistry $types,
        protected MemberInventory $inventory,
        protected Validation $validation,
    ) {
    }

    public function type(): string
    {
        return 'placement-submissions';
    }

    public function model(): string
    {
        // Submission, not Creative, and the distinction is load-bearing: see
        // the class docblock on Submission.
        return Submission::class;
    }

    /**
     * A member sees their own submissions and no others.
     *
     * The guest branch is unreachable through the endpoints below, which are
     * all authenticated. It is here because getting it wrong the other way --
     * `where('user_id', null)` -- matches every advertiser the staff created by
     * hand, and would hand a guest the lot.
     */
    public function scope(Builder $query, Context $context): void
    {
        $actor = $context->getActor();

        if (! $actor->exists) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('campaign.advertiser', fn (Builder $advertisers) => $advertisers->where('user_id', $actor->id));
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()->authenticated()->can(Permissions::SUBMIT),
            Endpoint\Index::make()->authenticated()->can(Permissions::SUBMIT)->paginate(50),
            Endpoint\Create::make()->authenticated()->can(Permissions::SUBMIT),
            Endpoint\Update::make()->authenticated()->can(Permissions::SUBMIT),
            Endpoint\Delete::make()->authenticated()->can(Permissions::SUBMIT),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')->requiredOnCreate()->writable()->maxLength(150),

            Schema\Str::make('type')->requiredOnCreate()->writable()->maxLength(20),

            Schema\Arr::make('payload')->writable(),

            Schema\Str::make('destinationUrl')
                ->property('destination_url')
                ->writable()
                ->nullable()
                ->maxLength(2000)
                // One call per rule: `rule()` takes a single rule, and a
                // pipe-separated string reaches the validator as one unknown
                // rule name and throws rather than validating anything.
                ->rule('nullable')
                ->rule('url')
                ->rule('starts_with:http://,https://'),

            // Read-only, all of them. A member says what their advert is; the
            // forum says whether it runs.
            Schema\Str::make('status'),
            Schema\Str::make('reviewReason')->property('review_reason'),
            Schema\DateTime::make('reviewedAt')->property('reviewed_at'),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('updatedAt')->property('updated_at'),
        ];
    }

    public function saving(object $model, Context $context): ?object
    {
        $creating = $context->creating(self::class);

        if ($creating) {
            $this->attachToTheirCampaign($model, $context);
        }

        $this->checkType($model);
        $this->checkPayload($model);

        // Every write leaves it pending, including an edit to something
        // already approved. Nothing a member sends may set its own status, so
        // there is no path from here to a creative on the forum that nobody
        // read.
        $model->status = Creative::STATUS_PENDING;
        $model->reviewed_by = null;
        $model->reviewed_at = null;
        $model->review_reason = null;

        return $model;
    }

    /**
     * @throws ForbiddenException
     */
    protected function attachToTheirCampaign(Creative $creative, Context $context): void
    {
        $actor = $context->getActor();

        $campaign = $this->inventory->campaignFor($actor);

        $pending = Creative::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', Creative::STATUS_PENDING)
            ->count();

        if ($pending >= self::PENDING_LIMIT) {
            throw new ForbiddenException(
                'You already have '.self::PENDING_LIMIT.' adverts waiting to be reviewed. '
                .'Wait for one of those before submitting another.'
            );
        }

        $creative->campaign_id = $campaign->id;

        // Defaults the member never sends, because where an advert runs and
        // how much of the rotation it takes are not theirs to set.
        $creative->weight = Creative::MIN_WEIGHT;
        $creative->impressions = 0;
        $creative->viewable_impressions = 0;
        $creative->clicks = 0;
    }

    /**
     * @throws BadRequestException
     */
    protected function checkType(Creative $creative): void
    {
        $type = $this->types->get($creative->type);

        if ($type === null) {
            throw new BadRequestException("There is no creative type named [{$creative->type}].");
        }

        // A type that needs a permission of its own is one that runs code on
        // every page of the forum. Those are authored in the admin panel by
        // somebody holding that permission, never submitted -- so this refuses
        // them outright rather than checking whether the member happens to
        // hold it.
        if ($type->requiredPermission() !== null) {
            throw new BadRequestException("Creatives of type [{$creative->type}] cannot be submitted.");
        }
    }

    /**
     * Normalise, then validate, then store what the type says rather than what
     * arrived -- the same order and the same reasons as the administrative
     * resource, because a member's payload is the less trusted of the two.
     */
    protected function checkPayload(Creative $creative): void
    {
        $type = $this->types->get($creative->type);

        if ($type === null) {
            return;
        }

        $payload = $type->normalize(is_array($creative->payload) ? $creative->payload : []);
        $rules = $type->rules();

        if ($rules !== []) {
            $this->validation->make($payload, $rules)->validate();
        }

        $creative->payload = $payload;
    }
}
