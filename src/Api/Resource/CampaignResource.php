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

use Datlechin\Placements\Model\Campaign;
use Datlechin\Placements\Model\CampaignRule;
use Datlechin\Placements\Targeting\DimensionInterface;
use Datlechin\Placements\Support\Permissions;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Tobyz\JsonApiServer\Context;

/**
 * @extends AbstractDatabaseResource<Campaign>
 */
class CampaignResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'placement-campaigns';
    }

    public function model(): string
    {
        return Campaign::class;
    }

    public function endpoints(): array
    {
        // Every endpoint is gated by the same permission. No policy is
        // registered for it on purpose: with no policy deciding, Flarum's gate
        // falls back to `isAdmin() || hasPermission($ability)`, which is
        // exactly the rule wanted, and a policy broad enough to cover the
        // listing endpoints — where there is no model to check against — would
        // have had to answer `allow` to every ability asked of these models.
        //
        // Nothing here is ever readable by an ordinary member: a campaign row
        // carries what an advertiser is paying and who they are.
        return [
            Endpoint\Show::make()->authenticated()->can(Permissions::MANAGE),
            Endpoint\Index::make()->authenticated()->can(Permissions::MANAGE)->defaultInclude(['advertiser'])->paginate(50),
            Endpoint\Create::make()->authenticated()->can(Permissions::MANAGE),
            Endpoint\Update::make()->authenticated()->can(Permissions::MANAGE),
            Endpoint\Delete::make()->authenticated()->can(Permissions::MANAGE),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(150),

            Schema\Str::make('status')
                ->writable()
                ->in([
                    Campaign::STATUS_DRAFT,
                    Campaign::STATUS_SCHEDULED,
                    Campaign::STATUS_ACTIVE,
                    Campaign::STATUS_PAUSED,
                    Campaign::STATUS_ARCHIVED,
                ]),

            Schema\Integer::make('tier')
                ->writable()
                ->in([
                    Campaign::TIER_SPONSORSHIP,
                    Campaign::TIER_GUARANTEED,
                    Campaign::TIER_STANDARD,
                    Campaign::TIER_REMNANT,
                    Campaign::TIER_HOUSE,
                ]),

            Schema\Boolean::make('isHouse')
                ->property('is_house')
                ->writable(),

            Schema\DateTime::make('startsAt')
                ->property('starts_at')
                ->writable()
                ->nullable(),

            Schema\DateTime::make('endsAt')
                ->property('ends_at')
                ->writable()
                ->nullable(),

            Schema\Integer::make('maxImpressions')
                ->property('max_impressions')
                ->writable()
                ->nullable()
                ->min(1),

            Schema\Integer::make('maxClicks')
                ->property('max_clicks')
                ->writable()
                ->nullable()
                ->min(1),

            // 42 hex characters: 168 bits, one per hour of the week, read
            // left to right from Monday midnight in the forum's timezone.
            Schema\Str::make('daypartMask')
                ->property('daypart_mask')
                ->writable()
                ->nullable()
                ->regex('/^[0-9a-f]{42}$/i'),

            Schema\Str::make('pacing')
                ->writable()
                ->in([Campaign::PACING_ASAP, Campaign::PACING_EVEN]),

            Schema\Integer::make('frequencyCap')
                ->property('frequency_cap')
                ->writable()
                ->nullable()
                ->min(1),

            Schema\Str::make('frequencyWindow')
                ->property('frequency_window')
                ->writable()
                ->in([Campaign::WINDOW_SESSION, Campaign::WINDOW_HOUR, Campaign::WINDOW_DAY]),

            // Recorded so a report can state what was contracted. Nothing in
            // this extension ever charges, invoices or converts a currency,
            // and the admin help text says so.
            Schema\Str::make('rateType')->property('rate_type')->writable()->nullable(),
            Schema\Str::make('rateAmount')->property('rate_amount')->writable()->nullable(),
            Schema\Str::make('rateCurrency')->property('rate_currency')->writable()->nullable()->maxLength(3),
            Schema\Str::make('contractNotes')->property('contract_notes')->writable()->nullable(),

            // Counters are read-only over the API. They are moved by the
            // measurement pipeline, and letting a client set them would make
            // the caps trivially defeatable.
            Schema\Integer::make('impressions'),
            Schema\Integer::make('clicks'),

            // Computed rather than stored, because `status` is only an
            // administrator's stated intent: a campaign whose end date has
            // passed is not live however active the row says it is.
            Schema\Boolean::make('isLive')
                ->get(fn (Campaign $campaign) => $campaign->isLive()),

            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('updatedAt')->property('updated_at'),

            Schema\Relationship\ToOne::make('advertiser')
                ->type('placement-advertisers')
                ->includable()
                ->writable()
                ->nullable(),

            Schema\Relationship\ToMany::make('creatives')
                ->type('placement-creatives')
                ->includable(),

            // Targeting rides on the campaign rather than being its own
            // resource. An administrator edits the rules on the campaign form,
            // never on their own, and a second resource would mean the client
            // reconciling two collections to save one screen.
            //
            // Written after the model is saved, because a new campaign has no
            // id to attach rows to until then.
            Schema\Arr::make('rules')
                ->writable()
                ->get(fn (Campaign $campaign) => array_map(
                    fn (CampaignRule $rule) => [
                        'dimension' => $rule->dimension,
                        'operator' => $rule->operator,
                        'value' => $rule->value,
                    ],
                    $campaign->rules->all()
                ))
                ->set(fn () => null)
                ->save(function (Campaign $campaign, mixed $rules) {
                    if (! is_array($rules)) {
                        return;
                    }

                    $campaign->rules()->delete();

                    foreach ($this->cleanRules($rules) as $rule) {
                        // Built explicitly rather than mass-assigned: Flarum's
                        // AbstractModel keeps Eloquent's guard on.
                        $row = new CampaignRule();
                        $row->forceFill($rule);

                        $campaign->rules()->save($row);
                    }

                    $campaign->unsetRelation('rules');
                }),
        ];
    }

    /**
     * Keep only rows that name a dimension, an operator we know, and a value.
     *
     * A malformed rule is dropped rather than rejected: the alternative is a
     * campaign that cannot be saved at all because of one stale row in a form,
     * and a rule that reaches the evaluator half-written would match nothing
     * and be much harder to notice.
     *
     * @param  array<mixed>  $rules
     * @return list<array{dimension: string, operator: string, value: string}>
     */
    protected function cleanRules(array $rules): array
    {
        $operators = [
            DimensionInterface::IS,
            DimensionInterface::IS_NOT,
            DimensionInterface::GTE,
            DimensionInterface::LTE,
        ];

        $clean = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $dimension = $rule['dimension'] ?? null;
            $operator = $rule['operator'] ?? null;
            $value = $rule['value'] ?? null;

            if (! is_string($dimension) || $dimension === '' || ! is_scalar($value) || $value === '') {
                continue;
            }

            if (! is_string($operator) || ! in_array($operator, $operators, true)) {
                continue;
            }

            $clean[] = [
                'dimension' => $dimension,
                'operator' => $operator,
                // Truncated to the column, so an over-long value is stored as
                // something that will simply never match rather than throwing
                // on save.
                'value' => mb_substr((string) $value, 0, 191),
            ];
        }

        return $clean;
    }

    public function creating(object $model, Context $context): ?object
    {
        $actor = $context->getActor();

        if ($actor->exists) {
            $model->created_by = $actor->id;
        }

        return $model;
    }
}
