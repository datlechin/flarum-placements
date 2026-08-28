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

use Datlechin\Placements\Model\PlacementSetting;
use Datlechin\Placements\PlacementRegistry;
use Datlechin\Placements\Support\Permissions;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;

/**
 * What an administrator may change about a slot the code declared.
 *
 * The id is the placement key, not a number: there can only ever be one row
 * per slot, and a surrogate id would let two rows claim the same slot.
 *
 * There is no create endpoint. A slot exists because a component renders it,
 * so the only legal keys are the ones already in the registry — and a row is
 * written for a key the first time somebody changes something about it.
 *
 * @extends AbstractDatabaseResource<PlacementSetting>
 */
class PlacementSettingResource extends AbstractDatabaseResource
{
    public function __construct(protected PlacementRegistry $registry)
    {
    }

    public function type(): string
    {
        return 'placement-settings';
    }

    public function model(): string
    {
        return PlacementSetting::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()->authenticated()->can(Permissions::MANAGE),
            Endpoint\Index::make()->authenticated()->can(Permissions::MANAGE),
            // Update only: an administrator configures a slot, never invents
            // one. The row is created on first write by `find()` below.
            Endpoint\Update::make()->authenticated()->can(Permissions::MANAGE),
        ];
    }

    /**
     * Settings are configured lazily, so the first edit of a slot has no row
     * to update yet. Rather than making the client create one — and having to
     * stop it creating rows for keys that render nothing — an unsaved model is
     * handed back for a key the registry knows.
     *
     * @throws BadRequestException
     */
    public function find(string $id, Context $context): ?object
    {
        // The model query rather than the resource's: this resource applies no
        // scope, and `query()` is typed as returning a bare object.
        $existing = PlacementSetting::query()->find($id);

        if ($existing !== null) {
            return $existing;
        }

        if (! $this->registry->has($id)) {
            throw new BadRequestException("There is no placement named [$id].");
        }

        $setting = new PlacementSetting();
        $setting->forceFill(['key' => $id]);

        return $setting;
    }

    public function fields(): array
    {
        return [
            Schema\Boolean::make('enabled')->writable(),

            Schema\Integer::make('maxFill')->property('max_fill')->writable()->min(1)->max(10),

            Schema\Str::make('fallback')
                ->writable()
                ->in([
                    PlacementSetting::FALLBACK_NEXT_TIER,
                    PlacementSetting::FALLBACK_HOUSE,
                    PlacementSetting::FALLBACK_PASSBACK,
                    PlacementSetting::FALLBACK_COLLAPSE,
                ]),

            Schema\Str::make('labelMode')
                ->property('label_mode')
                ->writable()
                ->in([
                    PlacementSetting::LABEL_INHERIT,
                    PlacementSetting::LABEL_ALWAYS,
                    // Never labelling is offered because a house advert for the
                    // forum's own features is not a paid placement — but it is
                    // the administrator's call to make, and the help text says
                    // what they are turning off.
                    PlacementSetting::LABEL_NEVER,
                ]),

            Schema\Str::make('rotation')
                ->writable()
                ->in([PlacementSetting::ROTATION_RANDOM, PlacementSetting::ROTATION_STICKY]),

            Schema\Integer::make('reservePhone')->property('reserve_phone')->writable()->nullable()->min(0),
            Schema\Integer::make('reserveTablet')->property('reserve_tablet')->writable()->nullable()->min(0),
            Schema\Integer::make('reserveDesktop')->property('reserve_desktop')->writable()->nullable()->min(0),

            // Only meaningful on a repeating slot; the client hides them
            // elsewhere, and the resolver drops them regardless.
            Schema\Integer::make('everyN')->property('every_n')->writable()->nullable()->min(1),
            Schema\Integer::make('repeatLimit')->property('repeat_limit')->writable()->nullable()->min(1),

            Schema\Integer::make('sortOrder')->property('sort_order')->writable(),
        ];
    }
}
