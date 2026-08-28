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

use Datlechin\Placement\Model\Advertiser;
use Datlechin\Placement\Support\Permissions;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Http\UrlGenerator;

/**
 * @extends AbstractDatabaseResource<Advertiser>
 */
class AdvertiserResource extends AbstractDatabaseResource
{
    public function __construct(protected UrlGenerator $url)
    {
    }

    public function type(): string
    {
        return 'placement-advertisers';
    }

    public function model(): string
    {
        return Advertiser::class;
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
            Schema\Str::make('contactEmail')
                ->property('contact_email')
                ->writable()
                ->nullable()
                ->maxLength(150)
                ->rule('nullable')
                ->rule('email'),
            Schema\Str::make('notes')->writable()->nullable(),

            // Whether a link exists, never the token itself. A report token is
            // the one credential in this extension that reaches somebody
            // outside the forum, so it leaves the server exactly once — in the
            // response to the request that created it.
            Schema\Boolean::make('hasReportToken')
                ->get(fn (Advertiser $advertiser) => $advertiser->hasUsableReportToken()),

            Schema\DateTime::make('reportTokenExpiresAt')->property('report_token_expires_at'),

            // Write-only, and the link comes back exactly once: in the response
            // to the request that asked for it. Send `true` to issue a new one
            // (invalidating any previous), `false` to revoke.
            Schema\Boolean::make('regenerateReportToken')
                ->writable()
                ->get(fn () => false)
                ->set(function (Advertiser $advertiser, mixed $value) {
                    if ($value === true) {
                        $advertiser->regenerateReportToken();
                    } elseif ($value === false) {
                        $advertiser->revokeReportToken();
                    }
                }),

            // Present only on the response to the request that created it, so
            // a link cannot be recovered by listing advertisers later.
            Schema\Str::make('reportUrl')
                ->get(fn (Advertiser $advertiser) => $advertiser->wasRecentlyIssuedToken
                    ? $this->url->to('forum')->route('datlechin-placement.report_link', ['token' => $advertiser->report_token])
                    : null)
                ->nullable(),
            Schema\DateTime::make('createdAt')->property('created_at'),
            Schema\DateTime::make('updatedAt')->property('updated_at'),

            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->writable()
                ->nullable()
                ->includable(),

            Schema\Relationship\ToMany::make('campaigns')
                ->type('placement-campaigns')
                ->includable(),
        ];
    }
}
