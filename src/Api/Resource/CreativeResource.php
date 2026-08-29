<?php

/*
 * This file is part of datlechin/flarum-placements.
 *
 * Copyright (c) 2026 Ngo Quoc Dat.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Datlechin\Placements\Api\Resource;

use Carbon\Carbon;
use Datlechin\Placements\Creative\CreativeTypeRegistry;
use Datlechin\Placements\Creative\Type\RawHtmlType;
use Datlechin\Placements\Model\Assignment;
use Datlechin\Placements\Model\Creative;
use Datlechin\Placements\PlacementRegistry;
use Datlechin\Placements\Support\Permissions;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Illuminate\Contracts\Validation\Factory as Validation;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;

/**
 * @extends AbstractDatabaseResource<Creative>
 */
class CreativeResource extends AbstractDatabaseResource
{
    public function __construct(
        protected PlacementRegistry $registry,
        protected CreativeTypeRegistry $types,
        protected Validation $validation,
    ) {
    }

    public function type(): string
    {
        return 'placement-creatives';
    }

    public function model(): string
    {
        return Creative::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()->authenticated()->can(Permissions::MANAGE),
            Endpoint\Index::make()->authenticated()->can(Permissions::MANAGE)->paginate(50),
            Endpoint\Create::make()->authenticated()->can(Permissions::MANAGE),
            Endpoint\Update::make()->authenticated()->can(Permissions::MANAGE),
            Endpoint\Delete::make()->authenticated()->can(Permissions::MANAGE),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')->requiredOnCreate()->writable()->maxLength(150),

            // Checked against what is registered rather than against a list
            // written here, so a type added by another extension works and a
            // typo does not reach the database.
            Schema\Str::make('type')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(20),

            Schema\Str::make('status')
                ->writable()
                ->in([
                    Creative::STATUS_DRAFT,
                    Creative::STATUS_PENDING,
                    Creative::STATUS_APPROVED,
                    Creative::STATUS_REJECTED,
                ]),

            Schema\Integer::make('weight')
                ->writable()
                ->min(Creative::MIN_WEIGHT)
                ->max(Creative::MAX_WEIGHT),

            Schema\Str::make('destinationUrl')
                ->property('destination_url')
                ->writable()
                ->nullable()
                ->maxLength(2000)
                // One call per rule: `rule()` takes a single rule, and a
                // pipe-separated string reaches the validator as one unknown
                // rule name and throws rather than validating anything.
                //
                // The scheme is what turns a link into script execution, so it
                // is refused here rather than sanitised away silently.
                ->rule('nullable')
                ->rule('url')
                ->rule('starts_with:http://,https://'),

            // Why a creative was rejected. Written by whoever rejected it, and
            // shown to whoever submitted it — a rejection with no reason is a
            // rejection somebody will simply resubmit unchanged.
            Schema\Str::make('reviewReason')
                ->property('review_reason')
                ->writable()
                ->nullable()
                ->maxLength(255),

            Schema\DateTime::make('reviewedAt')->property('reviewed_at'),

            Schema\Relationship\ToOne::make('reviewer')
                ->type('users')
                ->includable(),

            Schema\Str::make('labelOverride')->property('label_override')->writable()->nullable()->maxLength(50),
            Schema\Str::make('variantGroup')->property('variant_group')->writable()->nullable()->maxLength(50),

            Schema\Arr::make('payload')->writable(),

            Schema\Integer::make('impressions'),
            Schema\Integer::make('viewableImpressions')->property('viewable_impressions'),
            Schema\Integer::make('clicks'),

            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('updatedAt')->property('updated_at'),

            Schema\Relationship\ToOne::make('campaign')
                ->type('placement-campaigns')
                ->requiredOnCreate()
                ->writable()
                ->includable(),

            // Assignments as a map of placement key to weight, rather than as
            // their own resource: an administrator ticks slots on the creative
            // form, and a separate resource would make saving one screen a
            // reconciliation of two collections.
            //
            // `null` as a weight means "use the creative's own".
            Schema\Arr::make('placements')
                ->writable()
                ->get(function (Creative $creative) {
                    $map = [];

                    foreach ($creative->assignments as $assignment) {
                        if ($assignment->enabled) {
                            $map[$assignment->placement_key] = $assignment->weight;
                        }
                    }

                    return $map;
                })
                ->set(fn () => null)
                ->save(function (Creative $creative, mixed $placements) {
                    if (! is_array($placements)) {
                        return;
                    }

                    $creative->assignments()->delete();

                    foreach ($this->cleanPlacements($placements) as $key => $weight) {
                        // Built explicitly rather than mass-assigned: Flarum's
                        // AbstractModel keeps Eloquent's guard on, so
                        // `create()` throws on a model with no `$fillable`.
                        $assignment = new Assignment();
                        $assignment->forceFill([
                            'placement_key' => $key,
                            'weight' => $weight,
                            'enabled' => true,
                        ]);

                        $creative->assignments()->save($assignment);
                    }

                    $creative->unsetRelation('assignments');
                }),
        ];
    }

    /**
     * `createdAt` ascending is what the review queue asks for: whoever has
     * waited longest is served first.
     */
    public function sorts(): array
    {
        return [
            SortColumn::make('name'),
            SortColumn::make('weight'),
            SortColumn::make('impressions'),
            SortColumn::make('clicks'),
            SortColumn::make('createdAt'),
            SortColumn::make('updatedAt'),
        ];
    }

    /**
     * Drop assignments to slots that do not exist.
     *
     * A key nothing renders would be an assignment that silently never shows,
     * and the administrator would have no way to tell it apart from a
     * targeting problem.
     *
     * @param  array<mixed>  $placements
     * @return array<string, int|null>
     */
    protected function cleanPlacements(array $placements): array
    {
        $clean = [];

        foreach ($placements as $key => $weight) {
            if (! is_string($key) || ! $this->registry->has($key)) {
                continue;
            }

            $clean[$key] = is_numeric($weight)
                ? max(Creative::MIN_WEIGHT, min(Creative::MAX_WEIGHT, (int) $weight))
                : null;
        }

        return $clean;
    }

    public function saving(object $model, Context $context): ?object
    {
        $this->checkType($model, $context);
        $this->checkPayload($model);
        $this->recordReview($model, $context);

        // Editing an approved creative sends it back for review. Skipping this
        // is how "approve once, edit freely" becomes a way to put anything at
        // all on every page of the forum.
        if (! $context->creating(self::class) && $model->isDirty(['payload', 'destination_url', 'type'])) {
            $model->requireReviewAgain();
        }

        return $model;
    }

    /**
     * Note who decided, and when.
     *
     * Without it a queue of approved creatives says nothing about who let each
     * one onto the forum, which is exactly what somebody asks after the one
     * that should not have been.
     */
    protected function recordReview(Creative $creative, Context $context): void
    {
        if (! $creative->isDirty('status')) {
            return;
        }

        $decided = in_array($creative->status, [Creative::STATUS_APPROVED, Creative::STATUS_REJECTED], true);

        if (! $decided) {
            $creative->reviewed_by = null;
            $creative->reviewed_at = null;

            return;
        }

        $actor = $context->getActor();

        $creative->reviewed_by = $actor->exists ? $actor->id : null;
        $creative->reviewed_at = Carbon::now();

        // A reason belongs to a rejection. Carrying one onto an approval would
        // leave a stale explanation attached to something that was accepted.
        if ($creative->status === Creative::STATUS_APPROVED) {
            $creative->review_reason = null;
        }
    }

    /**
     * @throws BadRequestException
     */
    protected function checkType(Creative $creative, Context $context): void
    {
        $type = $this->types->get($creative->type);

        if ($type === null) {
            throw new BadRequestException("There is no creative type named [{$creative->type}].");
        }

        // Raw HTML needs two keys turned at once: a permission of its own, and
        // a flag in config.php that no web request can set. A compromised or
        // careless administrator account can turn one of them.
        if ($type instanceof RawHtmlType && ! $type->isEnabled()) {
            throw new BadRequestException(
                'Raw HTML creatives are switched off. Set '
                .RawHtmlType::CONFIG_KEY.'.'.RawHtmlType::CONFIG_FLAG.' in config.php to allow them.'
            );
        }

        $permission = $type->requiredPermission();

        if ($permission !== null) {
            $context->getActor()->assertCan($permission);
        }
    }

    /**
     * Validate the payload against its own type, and store what the type says
     * it should be rather than what arrived.
     *
     * Without this the payload column would accept anything at all, and the
     * renderer would be the first thing to find out.
     */
    protected function checkPayload(Creative $creative): void
    {
        $type = $this->types->get($creative->type);

        if ($type === null) {
            return;
        }

        $raw = is_array($creative->payload) ? $creative->payload : [];

        // Normalised first, then validated. The other order rejects a URL
        // somebody pasted with a trailing space, which is exactly the sort of
        // thing normalising is for — and it means the rules describe what is
        // actually stored rather than what happened to arrive.
        $payload = $type->normalize($raw);
        $rules = $type->rules();

        if ($rules !== []) {
            $this->validation->make($payload, $rules)->validate();
        }

        $creative->payload = $payload;
    }
}
